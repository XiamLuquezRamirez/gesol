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
        } elseif ($tab === 'pendientes_cierre') {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->whereHas('tipoSolicitud', fn($q) => $q->where('clave', 'OFI'))
                ->where('estado', 'pendiente_cierre')
                ->latest()
                ->get();
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
                SolicitudOficina::class  => ['items', 'cotizaciones.usuario', 'beneficiarios', 'abonos.usuario'],
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

        // Corregir la liquidación mientras la comisión sigue liquidada (solo contador).
        $rutaLiquidacion = null;
        if ($solicitud->estado === 'liquidada' && $usuario->can('editarLiquidacion', $solicitud)) {
            $rutaLiquidacion = route('viaticos.liquidacion', $solicitud);
        }

        return Inertia::render('Solicitudes/Detalle', [
            'solicitud'       => (new SolicitudDetalleResource($solicitud))->resolve(),
            'acciones'        => $this->motor->accionesDisponibles($solicitud, $usuario),
            'rutaEditar'      => $rutaEditar,
            'rutaLiquidacion' => $rutaLiquidacion,
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

        // Cuando el lider de area envia la comision de viaticos, avisar a RR. HH.
        // de inmediato (el informe de quien esta por fuera), sin esperar al cierre contable.
        if ($request->accion === 'enviar'
            && $solicitud->fresh()->estado === 'enviada'
            && $solicitud->tipoSolicitud->clave === 'VIA'
        ) {
            $rrhh = Usuario::role('rrhh')->get();
            if ($rrhh->isNotEmpty()) {
                // 1) La campana (database) siempre queda registrada.
                Notification::send($rrhh, new ComisionCerradaNotification($solicitud->fresh(), ['database']));
                // 2) El correo se intenta aparte; un fallo de SMTP no debe romper el envio.
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
