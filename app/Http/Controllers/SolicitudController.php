<?php
namespace App\Http\Controllers;

use App\Exceptions\TransicionNoPermitidaException;
use App\Http\Requests\EjecutarTransicionRequest;
use App\Http\Resources\{SolicitudDetalleResource, SolicitudResource};
use App\Models\{Solicitud, SolicitudOficina, SolicitudViaticos, Usuario};
use App\Notifications\ComisionCerradaNotification;
use App\Services\MotorWorkflow;
use Illuminate\Support\Facades\Notification;
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
        } elseif ($tab === 'revisadas') {
            // Solicitudes donde el usuario ejecuto al menos una transicion:
            // conserva la trazabilidad de lo que reviso, en cualquier estado.
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->whereHas('transiciones', fn($q) => $q->where('usuario_id', $usuario->id))
                ->latest()
                ->get();
        } else {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->where('solicitante_id', $usuario->id)
                ->latest()
                ->get();
        }


        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => ['data' => SolicitudResource::collection($solicitudes)->resolve()],
            'filtros'     => ['tab' => $tab],
        ]);
    }

    public function show(Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        $solicitud->load([
            'tipoSolicitud',
            'solicitante',
            'area',
            'solicitable' => fn ($morphTo) => $morphTo->morphWith([
                SolicitudOficina::class  => ['items'],
                SolicitudViaticos::class => ['viajeros.empleado', 'viajeros.asignaciones'],
            ]),
            'transiciones.usuario',
        ]);

        $usuario    = auth()->user();
        $rutaEditar = null;
        if ($usuario->can('editar', $solicitud)) {
            $clave = $solicitud->tipoSolicitud->clave;
            if ($clave === 'OFI') $rutaEditar = route('oficina.editar', $solicitud);
            if ($clave === 'VIA') $rutaEditar = route('viaticos.editar', $solicitud);
        }

        return Inertia::render('Solicitudes/Detalle', [
            'solicitud'   => (new SolicitudDetalleResource($solicitud))->resolve(),
            'acciones'    => $this->motor->accionesDisponibles($solicitud, $usuario),
            'rutaEditar'  => $rutaEditar,
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

        // Al cerrar una comisión de viáticos, enviar el informe a RR. HH.
        if ($solicitud->fresh()->estado === 'cerrada' && $solicitud->tipoSolicitud->clave === 'VIA') {
            $rrhh = Usuario::role('rrhh')->get();
            if ($rrhh->isNotEmpty()) {
                // 1) La campana (database) siempre queda registrada.
                Notification::send($rrhh, new ComisionCerradaNotification($solicitud->fresh(), ['database']));
                // 2) El correo se intenta aparte; un fallo de SMTP no debe romper el cierre.
                try {
                    Notification::send($rrhh, new ComisionCerradaNotification($solicitud->fresh(), ['mail']));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Transición aplicada correctamente.');
    }
}
