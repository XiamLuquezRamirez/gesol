<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarSolicitudOficinaRequest;
use App\Models\{Area, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OficinaController extends Controller
{
    public function create()
    {
        $this->authorize('create', Solicitud::class);
        return Inertia::render('Oficina/Crear', [
            'areas'    => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios' => Usuario::orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(GuardarSolicitudOficinaRequest $request)
    {

       
        $this->authorize('create', Solicitud::class);
        $tipo = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cabecera = SolicitudOficina::create([
                'beneficiario' => $request->beneficiario,
                'urgencia'     => $request->urgencia,
                'justificacion'=> $request->justificacion,
            ]);

            $solicitud = Solicitud::create([
                'tipo_solicitud_id' => $tipo->id,
                'solicitante_id'    => auth()->id(),
                'area_id'           => $request->area_id,
                'solicitable_type'  => SolicitudOficina::class,
                'solicitable_id'    => $cabecera->id,
                'estado'            => $tipo->estado_inicial,
                'radicado'          => Solicitud::generarRadicado($tipo),
            ]);

            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($item, ['solicitud_oficina_id' => $cabecera->id, 'subtotal' => 0]));
            }

            return $solicitud;
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud creada: '.$solicitud->radicado);
    }

    public function edit(Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $solicitud->load('solicitable.items');
        return Inertia::render('Oficina/Crear', [
            'solicitud' => $solicitud,
            'areas'     => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'editar'    => true,
        ]);
    }

    public function update(GuardarSolicitudOficinaRequest $request, Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $cabecera = $solicitud->solicitable;

        DB::transaction(function () use ($request, $cabecera) {
            $cabecera->update([
                'beneficiario' => $request->beneficiario,
                'urgencia'        => $request->urgencia,
                'justificacion'   => $request->justificacion,
            ]);
            $cabecera->items()->delete();
            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($item, ['solicitud_oficina_id' => $cabecera->id, 'subtotal' => 0]));
            }
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud actualizada.');
    }
}
