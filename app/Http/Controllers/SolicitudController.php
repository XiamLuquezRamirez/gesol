<?php
namespace App\Http\Controllers;

use App\Exceptions\TransicionNoPermitidaException;
use App\Http\Requests\EjecutarTransicionRequest;
use App\Http\Resources\{SolicitudDetalleResource, SolicitudResource};
use App\Models\Solicitud;
use App\Services\MotorWorkflow;
use Inertia\Inertia;

class SolicitudController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function index()
    {
        $usuario = auth()->user();
        $tab     = request('tab', 'mias');

        if ($tab === 'pendientes') {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->get()
                ->filter(fn($s) => !empty($this->motor->accionesDisponibles($s, $usuario)))
                ->values();
        } else {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->where('solicitante_id', $usuario->id)
                ->latest()
                ->get();
        }

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => SolicitudResource::collection($solicitudes),
            'filtros'     => ['tab' => $tab],
        ]);
    }

    public function show(Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        $solicitud->load(['tipoSolicitud','solicitante','area','solicitable','transiciones.usuario']);

        return Inertia::render('Solicitudes/Detalle', [
            'solicitud' => new SolicitudDetalleResource($solicitud),
            'acciones'  => $this->motor->accionesDisponibles($solicitud, auth()->user()),
        ]);
    }

    public function transicion(EjecutarTransicionRequest $request, Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        try {
            $this->motor->aplicarTransicion(
                $solicitud,
                $request->accion,
                auth()->user(),
                $request->comentario,
                $request->metadatos ?? []
            );
        } catch (TransicionNoPermitidaException $e) {
            return back()->withErrors(['accion' => $e->getMessage()]);
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Transición aplicada correctamente.');
    }
}
