<?php
namespace App\Http\Controllers;

use App\Http\Requests\{GuardarSolicitudViaticosRequest, ActualizarAsignacionesRequest};
use App\Models\{AsignacionViatico, Municipio, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision, Empleados};
use App\Services\MotorWorkflow;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViaticosController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}
    public function create()
    {
        return Inertia::render('Viaticos/Crear', [
            'empleados'  => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion']),
            'municipios' => Municipio::orderBy('nombre')->get(['id','nombre']),
            'contratos'  => \App\Models\Contrato::orderBy('descripcion')->get(['id','descripcion','objeto']),
        ]);
    }

    public function store(GuardarSolicitudViaticosRequest $request)
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            // Datos generales de la comisión. municipio_destino se deriva de los
            // municipios seleccionados (texto legible) para que correo, notificación,
            // PDF y demás consumidores del campo sigan mostrando el destino.
            $cabecera = SolicitudViaticos::create([
                'nombre_comision'   => $request->nombre_comision,
                'municipio_destino' => $this->municipiosComoTexto($request->municipios),
                'observacion'       => $request->observacion,
            ]);
            $cabecera->municipios()->sync($request->municipios);

            // Datos individuales por empleado comisionado
            foreach ($request->viajeros as $v) {
                ViajeroComision::create($this->atributosViajero($cabecera->id, $v));
            }

            return Solicitud::create([
                'tipo_solicitud_id' => $tipo->id,
                'solicitante_id'    => auth()->id(),
                'solicitable_type'  => SolicitudViaticos::class,
                'solicitable_id'    => $cabecera->id,
                'estado'            => $tipo->estado_inicial,
                'radicado'          => Solicitud::generarRadicado($tipo),
            ]);
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud creada: '.$solicitud->radicado);
    }

    public function edit(Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $solicitud->load(['solicitable.viajeros.empleado', 'solicitable.viajeros.contrato', 'solicitable.municipios']);
        return Inertia::render('Viaticos/Crear', [
            'solicitud'  => $solicitud,
            'empleados'  => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion']),
            'municipios' => Municipio::orderBy('nombre')->get(['id','nombre']),
            'contratos'  => \App\Models\Contrato::orderBy('descripcion')->get(['id','descripcion','objeto']),
            'editar'     => true,
        ]);
    }

    public function update(GuardarSolicitudViaticosRequest $request, Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $cabecera = $solicitud->solicitable;

        DB::transaction(function () use ($request, $cabecera) {
            $cabecera->update([
                'nombre_comision'   => $request->nombre_comision,
                'municipio_destino' => $this->municipiosComoTexto($request->municipios),
                'observacion'       => $request->observacion,
            ]);
            $cabecera->municipios()->sync($request->municipios);
            // Borrado uno a uno (no ->delete() masivo) para que el evento deleting
            // de ViajeroComision limpie del disco los archivos de cada viajero.
            $cabecera->viajeros()->get()->each->delete();
            foreach ($request->viajeros as $v) {
                ViajeroComision::create($this->atributosViajero($cabecera->id, $v));
            }
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud actualizada.');
    }

    /**
     * Texto legible de los municipios seleccionados (nombres separados por coma),
     * que se guarda en municipio_destino para los consumidores que leen ese campo
     * (panel RR. HH., correo al viajero, notificación de comisión, PDF de liquidación).
     */
    private function municipiosComoTexto(array $municipioIds): string
    {
        return Municipio::whereIn('id', $municipioIds)->orderBy('nombre')->pluck('nombre')->implode(', ');
    }

    /** Mapea un viajero del request a los atributos persistibles, resolviendo empleado vs externo. */
    private function atributosViajero(int $cabeceraId, array $v): array
    {
        $externo = filter_var($v['es_externo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return [
            'solicitud_viaticos_id'  => $cabeceraId,
            'empleado_id'            => $externo ? null : ($v['empleado_id'] ?? null),
            'contrato_id'            => $v['contrato_id'] ?? null,
            'nombre_externo'         => $externo ? ($v['nombre_externo'] ?? null) : null,
            'identificacion_externo' => $externo ? ($v['identificacion_externo'] ?? null) : null,
            'motivo'                 => $v['motivo'],
            'fecha_salida'           => $v['fecha_salida'],
            'hora_salida'            => $v['hora_salida'],
            'fecha_regreso'          => $v['fecha_regreso'],
            'hora_regreso'           => $v['hora_regreso'],
        ];
    }

    public function liquidacion(Solicitud $solicitud)
    {
        $this->authorize('editarLiquidacion', $solicitud);

        $solicitud->load(['solicitable.viajeros.empleado','solicitable.viajeros.asignaciones','solicitable.viajeros.archivos']);
        return Inertia::render('Viaticos/Liquidacion', [
            'solicitud' => $solicitud,
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => TarifaViatico::orderBy('id')->pluck('rubro')->toArray(),
        ]);
    }

    public function updateAllocations(ActualizarAsignacionesRequest $request, Solicitud $solicitud)
    {
        $this->authorize('editarLiquidacion', $solicitud);

        $usuario = auth()->user();
        $eraLiquidada = $solicitud->estado === 'liquidada';

        DB::transaction(function () use ($request, $solicitud, $usuario) {
            // Eliminar y recrear para respetar los rubros que el usuario quitó
            $viajeroIds = collect($request->asignaciones)->pluck('viajero_comision_id')->unique();
            AsignacionViatico::whereIn('viajero_comision_id', $viajeroIds)->delete();

            foreach ($request->asignaciones as $data) {
                AsignacionViatico::create([
                    'viajero_comision_id' => $data['viajero_comision_id'],
                    'rubro'               => $data['rubro'],
                    'valor_unitario'      => $data['valor_unitario'],
                    'dias'                => $data['dias'],
                    'subtotal'            => $data['valor_unitario'] * $data['dias'],
                ]);
            }

            foreach ($request->pagos as $pago) {
                ViajeroComision::where('id', $pago['viajero_comision_id'])
                    ->update(['tipo_pago' => $pago['tipo_pago']]);
            }

            $solicitud->solicitable->recalcularTotal();

            // El contador presenta el informe: la comision pasa de enviada a liquidada.
            if ($solicitud->estado === 'enviada' && $this->motor->puede($solicitud, 'liquidar', $usuario)) {
                $this->motor->aplicarTransicion($solicitud, 'liquidar', $usuario);
            }
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', $eraLiquidada
                ? 'Liquidación actualizada.'
                : 'Informe de comisión guardado.');
    }
}
