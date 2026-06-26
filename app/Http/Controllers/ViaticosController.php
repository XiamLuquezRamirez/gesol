<?php
namespace App\Http\Controllers;

use App\Http\Requests\{GuardarSolicitudViaticosRequest, ActualizarAsignacionesRequest};
use App\Models\{AsignacionViatico, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViaticosController extends Controller
{
    public function create()
    {
        return Inertia::render('Viaticos/Crear', [
            'usuarios' => Usuario::orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(GuardarSolicitudViaticosRequest $request)
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cabecera = SolicitudViaticos::create($request->only([
                'nombre_comision','municipio_destino','motivo','fecha_salida','fecha_regreso',
            ]));
            foreach ($request->viajeros as $userId) {
                ViajeroComision::create(['solicitud_viaticos_id'=>$cabecera->id,'usuario_id'=>$userId]);
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

    public function liquidacion(Solicitud $solicitud)
    {
        $solicitud->load(['solicitable.viajeros.usuario','solicitable.viajeros.asignaciones']);
        return Inertia::render('Viaticos/Liquidacion', [
            'solicitud' => $solicitud,
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => ['desayuno','almuerzo','cena','merienda','gasolina'],
        ]);
    }

    public function updateAllocations(ActualizarAsignacionesRequest $request, Solicitud $solicitud)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->asignaciones as $data) {
                AsignacionViatico::updateOrCreate(
                    ['viajero_comision_id'=>$data['viajero_comision_id'],'rubro'=>$data['rubro']],
                    ['valor_unitario'=>$data['valor_unitario'],'dias'=>$data['dias'],'subtotal'=>0]
                );
            }
        });

        return back()->with('success', 'Asignaciones guardadas.');
    }
}
