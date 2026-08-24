<?php
namespace App\Http\Controllers;

use App\Exceptions\TransicionNoPermitidaException;
use App\Http\Requests\EjecutarTransicionRequest;
use App\Http\Resources\{SolicitudDetalleResource, SolicitudResource};
use App\Models\{AjusteComision, Solicitud, SolicitudOficina, SolicitudViaticos, Usuario};
use App\Notifications\ComisionCerradaNotification;
use App\Services\MotorWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class SolicitudController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function index()
    {
        $usuario = auth()->user();
        $tab     = request('tab', 'mias');

        // "Pendientes de accion" se resuelve en PHP (recorre el motor por solicitud),
        // asi que se computa una sola vez y se reutiliza para los datos y el conteo.
        $pendientes = $this->colaPendientes($usuario);

        if ($tab === 'pendientes') {
            $solicitudes = $pendientes;
        } elseif ($tab === 'pendientes_cierre') {
            $q = $this->queryPendientesCierre($usuario);
            $solicitudes = $q ? $q->latest()->get() : collect();
        } elseif ($tab === 'pendientes_lider') {
            $q = $this->queryPendientesLider($usuario);
            $solicitudes = $q ? $q->oldest()->get() : collect();
        } elseif ($tab === 'revisadas') {
            // Solicitudes donde el usuario ejecuto al menos una transicion:
            // conserva la trazabilidad de lo que reviso, en cualquier estado.
            $solicitudes = Solicitud::with($this->relacionesListado())
                ->whereHas('transiciones', fn($q) => $q->where('usuario_id', $usuario->id))
                ->latest()
                ->get();
        } else {
            $solicitudes = Solicitud::with($this->relacionesListado())
                ->where('solicitante_id', $usuario->id)
                ->latest()
                ->get();
        }

        $conteos = [
            'pendientes'        => $pendientes->count(),
            'pendientes_cierre' => optional($this->queryPendientesCierre($usuario))->count() ?? 0,
            'pendientes_lider'  => optional($this->queryPendientesLider($usuario))->count() ?? 0,
        ];

        // Conjunto de solicitudes con un ajuste de comisión pendiente relevante al rol
        // del usuario (contador: pendiente_liquidacion/devuelto; contabilidad_lider:
        // liquidado). Una sola consulta para evitar N+1; luego se anexa el flag por item.
        $conAjuste = $this->solicitudesConAjustePendiente($usuario, $solicitudes);

        $data = collect(SolicitudResource::collection($solicitudes)->resolve())
            ->map(function ($item) use ($conAjuste) {
                $item['ajuste_pendiente'] = $conAjuste->has($item['id']);
                return $item;
            })
            ->all();

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => ['data' => $data],
            'filtros'     => ['tab' => $tab],
            'conteos'     => $conteos,
        ]);
    }

    /**
     * Eager-load del listado. Para viáticos carga municipios y el contrato de cada
     * viajero (la card muestra "Viáticos - Municipios" y los contratos relacionados);
     * oficina no necesita esas relaciones y el morphWith las omite para su tipo.
     */
    private function relacionesListado(): array
    {
        return [
            'tipoSolicitud',
            'solicitante',
            'solicitable' => fn ($m) => $m->morphWith([
                SolicitudViaticos::class => ['municipios', 'viajeros.contrato'],
            ]),
        ];
    }

    /**
     * Solicitudes donde el usuario tiene alguna accion disponible. Se resuelve en
     * PHP porque "accion disponible" depende del motor de workflow, no de SQL.
     */
    private function colaPendientes(Usuario $usuario): Collection
    {
        return Solicitud::with($this->relacionesListado())
            ->get()
            ->filter(fn ($s) => !empty($this->motor->accionesDisponibles($s, $usuario)))
            ->values();
    }

    /**
     * Ids de las solicitudes listadas que tienen un ajuste de comisión pendiente
     * relevante al rol del usuario. Devuelve un mapa (id => true) para lookups O(1).
     * Una sola consulta acotada a los ids visibles: sin N+1.
     */
    private function solicitudesConAjustePendiente(Usuario $usuario, \Illuminate\Support\Collection $solicitudes)
    {
        $estados = $usuario->hasRole('contabilidad_lider')
            ? ['liquidado']
            : ($usuario->hasRole('contador') ? ['pendiente_liquidacion', 'devuelto'] : []);

        if (empty($estados) || $solicitudes->isEmpty()) {
            return collect();
        }

        return AjusteComision::whereIn('estado', $estados)
            ->whereIn('solicitud_id', $solicitudes->pluck('id'))
            ->pluck('solicitud_id')
            ->unique()
            ->flip();
    }

    /**
     * Query de la cola "pendientes por cerrar" (OFI en pendiente_cierre), o null si
     * el usuario no puede verla. Devolver el query permite contar o listar sin repetir el gate.
     */
    private function queryPendientesCierre(Usuario $usuario): ?Builder
    {
        if (! $usuario->hasAnyRole(['contabilidad_lider', 'lider_area'])) {
            return null;
        }
        return Solicitud::with($this->relacionesListado())
            ->whereHas('tipoSolicitud', fn ($q) => $q->where('clave', 'OFI'))
            ->where('estado', 'pendiente_cierre');
    }

    /**
     * Query de la cola "pendientes del lider" (OFI verificada u VIA revisada), o null
     * si el usuario no es contador.
     */
    private function queryPendientesLider(Usuario $usuario): ?Builder
    {
        if (! $usuario->hasRole('contador')) {
            return null;
        }
        return Solicitud::with($this->relacionesListado())
            ->where(function ($q) {
                $q->where(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'OFI'))
                        ->where('estado', 'verificada'))
                  ->orWhere(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'VIA'))
                        ->where('estado', 'revisada'));
            });
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
                SolicitudViaticos::class => ['viajeros.empleado', 'viajeros.asignaciones', 'viajeros.contrato', 'viajeros.archivos.usuario', 'municipios'],
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

        // Ajustes-anexo (AjusteComision): solo aplican a comisiones de viáticos. Para OFI
        // se deja la lista vacía y los flags en false para no romper ese detalle.
        $ajustes = [];
        $permisosAjuste = ['liquidar' => false, 'aprobar' => false];
        if ($solicitud->tipoSolicitud->clave === 'VIA') {
            $ajustes = \App\Models\AjusteComision::where('solicitud_id', $solicitud->id)
                ->with(['viajero.empleado', 'viajero.archivos.usuario', 'solicitante:id,name', 'asignaciones'])
                ->orderByDesc('created_at')
                ->get();
            // Flags de rol para mostrar/ocultar botones; el servidor sigue aplicando la
            // policy por-ajuste en los endpoints reales.
            $permisosAjuste = [
                'liquidar' => $usuario->hasRole('contador'),
                'aprobar'  => $usuario->hasRole('contabilidad_lider'),
            ];
        }

        return Inertia::render('Solicitudes/Detalle', [
            'solicitud'       => (new SolicitudDetalleResource($solicitud))->resolve(),
            'acciones'        => $this->motor->accionesDisponibles($solicitud, $usuario),
            'rutaEditar'      => $rutaEditar,
            'rutaLiquidacion' => $rutaLiquidacion,
            'puedeGestionarComprobante' => $usuario->can('gestionarComprobante', $solicitud),
            'puedeCancelar'  => $usuario->can('cancelar', $solicitud),
            'puedeReactivar' => $usuario->can('reactivar', $solicitud),
            'puedeAjustar'   => $usuario->can('ajustar', $solicitud),
            // Ajuste pendiente de reincorporar en la liquidacion: bloquea "enviar a revision".
            'requiereReliquidacion' => $solicitud->tipoSolicitud->clave === 'VIA'
                && (bool) $solicitud->solicitable?->requiere_reliquidacion,
            'ajustes'        => $ajustes,
            'permisosAjuste' => $permisosAjuste,
        ]);
    }

    public function transicion(EjecutarTransicionRequest $request, Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        // No se puede enviar a revision una comision de viaticos con un ajuste
        // pendiente: el contador debe volver a guardar la liquidacion primero.
        if ($request->accion === 'enviar_revision'
            && $solicitud->tipoSolicitud->clave === 'VIA'
            && $solicitud->solicitable?->requiere_reliquidacion
        ) {
            return back()->withErrors(['accion' => 'Hay un ajuste pendiente: vuelve a guardar la liquidación antes de enviar a revisión.']);
        }

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
