<?php
namespace App\Http\Controllers;

use App\Http\Requests\{GuardarSolicitudViaticosRequest, ActualizarAsignacionesRequest, AjustarComisionRequest};
use App\Models\{AsignacionViatico, Municipio, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, TransicionSolicitud, Usuario, ViajeroComision, Empleados};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Http\Request;
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

        $solicitud->load(['solicitable.viajeros.empleado','solicitable.viajeros.asignaciones','solicitable.viajeros.archivos.usuario']);
        return Inertia::render('Viaticos/Liquidacion', [
            'solicitud' => $solicitud,
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => TarifaViatico::orderBy('id')->pluck('rubro')->toArray(),
            'puedeGestionarComprobante' => auth()->user()->can('gestionarComprobante', $solicitud),
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

            // Al guardar la liquidacion se da por reincorporado cualquier ajuste
            // pendiente, lo que habilita de nuevo "Enviar a lider de contabilidad".
            $solicitud->solicitable->updateQuietly(['requiere_reliquidacion' => false]);

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

    /**
     * El solicitante cancela su comision. Guarda el estado actual en estado_previo
     * para poder reactivarla despues, registra la transicion y avisa a RR. HH./contabilidad.
     */
    public function cancelar(Request $request, Solicitud $solicitud)
    {
        $this->authorize('cancelar', $solicitud);
        $motivo = $request->input('motivo');
        $anterior = $solicitud->estado;

        DB::transaction(function () use ($solicitud, $anterior, $motivo) {
            $solicitud->update(['estado_previo' => $anterior, 'estado' => 'cancelada']);
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $anterior,
                'estado_destino' => 'cancelada', 'accion' => 'cancelar',
                'usuario_id' => auth()->id(), 'comentario' => $motivo,
            ]);
        });

        $this->avisarCambioComision($solicitud->fresh(), 'cancelada', $motivo);
        return back()->with('success', 'Comisión cancelada.');
    }

    /**
     * El solicitante reactiva una comision cancelada: vuelve al estado_previo
     * (o 'enviada' si no habia), registra la transicion y avisa a RR. HH./contabilidad.
     */
    public function reactivar(Solicitud $solicitud)
    {
        $this->authorize('reactivar', $solicitud);
        $destino = $solicitud->estado_previo ?: 'enviada';

        DB::transaction(function () use ($solicitud, $destino) {
            $solicitud->update(['estado' => $destino, 'estado_previo' => null]);
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => 'cancelada',
                'estado_destino' => $destino, 'accion' => 'reactivar',
                'usuario_id' => auth()->id(),
            ]);
        });

        $this->avisarCambioComision($solicitud->fresh(), 'reactivada', null);
        return back()->with('success', 'Comisión reactivada.');
    }

    /**
     * El solicitante lider ajusta las fechas/horas de salida/regreso de cada viajero.
     * No recalcula rubros; el ajuste debe revisarlo el contador. Si la comision ya
     * paso al contador (liquidada/revisada/en_gerencia), regresa a 'liquidada' para
     * que el contador recalcule y re-presente el informe antes de que el lider contable
     * apruebe. Si aun esta 'enviada' (sin liquidar), solo se ajustan las fechas.
     * Registra la transicion 'ajustar' con el motivo y avisa (contador: accion; RR. HH.: info).
     */
    public function ajustar(AjustarComisionRequest $request, Solicitud $solicitud)
    {
        $this->authorize('ajustar', $solicitud);
        $cabecera = $solicitud->solicitable;
        $origen   = $solicitud->estado;
        // La comision ya paso por el contador si esta liquidada o mas avanzada: el
        // ajuste la devuelve a 'liquidada' para recalcular y exige re-liquidar.
        $yaLiquidada = in_array($origen, ['liquidada', 'revisada', 'en_gerencia']);
        $destino  = $yaLiquidada ? 'liquidada' : $origen;

        DB::transaction(function () use ($request, $solicitud, $cabecera, $origen, $destino, $yaLiquidada) {
            foreach ($request->viajeros as $datos) {
                $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->first();
                if (! $viajero) continue; // ignora ids ajenos a la comision
                $viajero->update([
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ]);
            }
            if ($destino !== $origen) {
                $solicitud->update(['estado' => $destino]);
            }
            if ($yaLiquidada) {
                // El contador debe recalcular la liquidacion con las nuevas fechas antes
                // de poder enviar a revision; el front recalcula los dias al abrirla.
                $cabecera->updateQuietly(['requiere_reliquidacion' => true]);
            }
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $origen,
                'estado_destino' => $destino, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
            ]);
        });

        $this->avisarAjuste($solicitud->fresh(), $request->motivo, $destino !== $origen);
        $mensaje = $destino !== $origen
            ? 'Comisión ajustada. Regresó al contador para recalcular; se notificó a contabilidad y RR. HH.'
            : 'Comisión ajustada. Se notificó a contabilidad y RR. HH.';
        return back()->with('success', $mensaje);
    }

    /**
     * Avisa del ajuste: al contador con accion requerida (debe recalcular) cuando la
     * comision regresa a liquidada, y a RR. HH. de forma informativa. Cuando no regresa
     * (aun en 'enviada'), avisa a todos de forma informativa.
     */
    private function avisarAjuste(Solicitud $solicitud, ?string $motivo, bool $regresoAlContador): void
    {
        $actor = auth()->user()->name;

        if ($regresoAlContador) {
            foreach (Usuario::role('contador')->get() as $u) {
                $u->notify(new AvisoTransicionNotification($solicitud, 'accion_requerida', 'ajustar', $motivo, $actor));
            }
            foreach (Usuario::role(['rrhh', 'contabilidad_lider'])->get() as $u) {
                $u->notify(new AvisoTransicionNotification($solicitud, 'ajustada', 'ajustar', $motivo, $actor));
            }
            return;
        }

        $this->avisarCambioComision($solicitud, 'ajustada', $motivo);
    }

    /** Notifica a RR.HH. y contabilidad de una cancelacion/reactivacion/ajuste de comision. */
    private function avisarCambioComision(Solicitud $solicitud, string $tipo, ?string $comentario): void
    {
        $usuarios = Usuario::role(['rrhh', 'contador', 'contabilidad_lider'])->get();
        foreach ($usuarios as $u) {
            $u->notify(new AvisoTransicionNotification(
                $solicitud, $tipo, $tipo, $comentario, auth()->user()->name
            ));
        }
    }
}
