<?php
namespace App\Http\Controllers;

use App\Http\Requests\{GuardarSolicitudViaticosRequest, ActualizarAsignacionesRequest, AjustarComisionRequest, ReajustarRubroRequest, LiquidarAjusteRequest};
use App\Models\{AjusteComision, AsignacionViatico, Municipio, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, TransicionSolicitud, Usuario, ViajeroComision, Empleados};
use App\Notifications\AvisoTransicionNotification;
use App\Services\CalculadoraRubrosViaticos;
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

        // Post-cierre: el ajuste se vuelve un anexo con estado propio (no reabre la comision).
        if ($solicitud->estado === 'cerrada') {
            return $this->crearAjusteAnexoFechas($request, $solicitud);
        }

        $cabecera = $solicitud->solicitable;
        $origen   = $solicitud->estado;
        // La comision ya paso por el contador si esta liquidada o mas avanzada: el
        // ajuste la devuelve a 'liquidada' para recalcular y exige re-liquidar.
        $yaLiquidada = in_array($origen, ['liquidada', 'revisada', 'en_gerencia']);
        $destino  = $yaLiquidada ? 'liquidada' : $origen;

        $detalle = [];
        DB::transaction(function () use ($request, $solicitud, $cabecera, $origen, $destino, $yaLiquidada, &$detalle) {
            foreach ($request->viajeros as $datos) {
                $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->first();
                if (! $viajero) continue;
                $detalle[] = [
                    'viajero_comision_id' => $viajero->id,
                    'nombre' => $viajero->nombreMostrado,
                    'antes' => [
                        'fecha_salida'  => optional($viajero->fecha_salida)->toDateString() ?? $viajero->fecha_salida,
                        'hora_salida'   => $viajero->hora_salida,
                        'fecha_regreso' => optional($viajero->fecha_regreso)->toDateString() ?? $viajero->fecha_regreso,
                        'hora_regreso'  => $viajero->hora_regreso,
                    ],
                    'despues' => [
                        'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                        'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                    ],
                ];
                $viajero->update([
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ]);
            }
            if ($destino !== $origen) {
                $solicitud->update(['estado' => $destino]);
            }
            if ($yaLiquidada) {
                $cabecera->updateQuietly(['requiere_reliquidacion' => true]);
            }
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $origen,
                'estado_destino' => $destino, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
                'metadatos' => ['tipo' => 'fechas', 'viajeros' => $detalle],
            ]);
        });

        $this->avisarAjuste($solicitud->fresh(), $request->motivo, $destino !== $origen);
        $mensaje = $destino !== $origen
            ? 'Comisión ajustada. Regresó al contador para recalcular; se notificó a contabilidad y RR. HH.'
            : 'Comisión ajustada. Se notificó a contabilidad y RR. HH.';
        return back()->with('success', $mensaje);
    }

    /**
     * El solicitante lider solicita un REAJUSTE de rubro (gasolina o transporte) por
     * viajero: indica viajero + rubro + cantidad + motivo. No edita montos; el contador
     * aplicara el valor al liquidar. Se registra como transicion 'ajustar' con
     * metadatos={tipo:'rubro', viajero_comision_id, nombre, rubro, cantidad}. Si la
     * comision ya paso por el contador (liquidada/revisada/en_gerencia) regresa a
     * 'liquidada' y enciende requiere_reliquidacion; si esta cerrada queda como anexo
     * (sin flag, sin cambio de estado); si esta enviada no cambia estado.
     */
    public function reajustarRubro(ReajustarRubroRequest $request, Solicitud $solicitud)
    {
        $this->authorize('ajustar', $solicitud);

        // Post-cierre: el reajuste de rubro se vuelve un anexo con estado propio.
        if ($solicitud->estado === 'cerrada') {
            return $this->crearAjusteAnexoRubro($request, $solicitud);
        }

        $cabecera = $solicitud->solicitable;
        $viajero = $cabecera->viajeros()->where('id', $request->viajero_comision_id)->firstOrFail();

        $origen = $solicitud->estado;
        $yaLiquidada = in_array($origen, ['liquidada', 'revisada', 'en_gerencia']);
        $destino = $yaLiquidada ? 'liquidada' : $origen;

        DB::transaction(function () use ($request, $solicitud, $cabecera, $viajero, $origen, $destino, $yaLiquidada) {
            if ($destino !== $origen) {
                $solicitud->update(['estado' => $destino]);
            }
            if ($yaLiquidada) {
                $cabecera->updateQuietly(['requiere_reliquidacion' => true]);
            }
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $origen,
                'estado_destino' => $destino, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
                'metadatos' => [
                    'tipo' => 'rubro',
                    'viajero_comision_id' => $viajero->id,
                    'nombre' => $viajero->nombreMostrado,
                    'rubro' => $request->rubro,
                    'cantidad' => (int) $request->cantidad,
                ],
            ]);
        });

        $this->avisarAjuste($solicitud->fresh(), $request->motivo, $yaLiquidada);
        return back()->with('success', 'Reajuste de rubro registrado. El contador lo aplicará en la liquidación.');
    }

    /**
     * Crea un ajuste-anexo de fechas sobre una comision cerrada. No modifica las fechas
     * reales del viajero (preserva la comision cerrada): solo guarda el snapshot ANTES
     * (fechas actuales) y DESPUES (request) para que el contador liquide el delta.
     */
    private function crearAjusteAnexoFechas(AjustarComisionRequest $request, Solicitud $solicitud)
    {
        $cabecera = $solicitud->solicitable;

        // AjustarComisionRequest valida 'viajeros' como array; para el anexo se ajusta
        // un viajero a la vez. Tomar el primero (el frontend post-cierre envia uno).
        $datos = $request->viajeros[0];
        $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->firstOrFail();

        $ajuste = null;
        DB::transaction(function () use ($request, $solicitud, $viajero, $datos, &$ajuste) {
            $ajuste = AjusteComision::create([
                'solicitud_id'        => $solicitud->id,
                'viajero_comision_id' => $viajero->id,
                'solicitado_por'      => auth()->id(),
                'tipo'                => 'fechas',
                'motivo'              => $request->motivo,
                'estado'              => 'pendiente_liquidacion',
                'fechas_antes' => [
                    'fecha_salida'  => optional($viajero->fecha_salida)->toDateString() ?? $viajero->fecha_salida,
                    'hora_salida'   => $viajero->hora_salida,
                    'fecha_regreso' => optional($viajero->fecha_regreso)->toDateString() ?? $viajero->fecha_regreso,
                    'hora_regreso'  => $viajero->hora_regreso,
                ],
                'fechas_despues' => [
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ],
            ]);
        });

        $this->avisarAjustePendiente($ajuste->fresh(), 'accion_requerida');
        return back()->with('success', 'Ajuste solicitado. Queda pendiente de liquidacion por el contador.');
    }

    /**
     * Crea un ajuste-anexo de rubro (gasolina/transporte) sobre una comision cerrada.
     * No altera la comision cerrada; queda pendiente de liquidacion por el contador.
     */
    private function crearAjusteAnexoRubro(ReajustarRubroRequest $request, Solicitud $solicitud)
    {
        $cabecera = $solicitud->solicitable;
        $viajero = $cabecera->viajeros()->where('id', $request->viajero_comision_id)->firstOrFail();

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => auth()->id(),
            'tipo'                => 'rubro',
            'motivo'              => $request->motivo,
            'estado'              => 'pendiente_liquidacion',
            'rubro'               => $request->rubro,
            'cantidad'            => (int) $request->cantidad,
        ]);

        $this->avisarAjustePendiente($ajuste->fresh(), 'accion_requerida');
        return back()->with('success', 'Reajuste de rubro solicitado. Queda pendiente de liquidacion por el contador.');
    }

    /** Notifica un ajuste-anexo pendiente. $tipoContador: 'accion_requerida'. */
    private function avisarAjustePendiente(AjusteComision $ajuste, string $tipoContador): void
    {
        $solicitud = $ajuste->solicitud;
        $actor = auth()->user()->name;
        foreach (Usuario::role('contador')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, $tipoContador, 'ajustar', $ajuste->motivo, $actor));
        }
        foreach (Usuario::role('rrhh')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, 'ajustada', 'ajustar', $ajuste->motivo, $actor));
        }
    }

    /**
     * Pantalla de liquidacion de un ajuste-anexo. Propone el delta de rubros (calculado
     * por fechas o por rubro/cantidad) con el valor unitario tomado de la liquidacion
     * original del viajero, para que el contador lo edite/confirme.
     */
    public function liquidacionAjuste(Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('liquidarAjuste', [$solicitud, $ajuste]);
        $ajuste->load('asignaciones', 'viajero.empleado');

        $delta = $this->deltaPropuesto($solicitud, $ajuste);

        return Inertia::render('Viaticos/LiquidacionAjuste', [
            'solicitud' => $solicitud->only('id', 'radicado', 'estado'),
            'ajuste'    => $ajuste,
            'delta'     => $delta,          // [{rubro, dias, valor_unitario, subtotal}]
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => TarifaViatico::orderBy('id')->pluck('rubro')->toArray(),
        ]);
    }

    /**
     * Construye el delta de rubros propuesto para el ajuste:
     * - tipo 'fechas': usa CalculadoraRubrosViaticos::calcularDelta(antes, despues).
     * - tipo 'rubro': un solo rubro con dias = cantidad.
     * Cada fila trae valor_unitario = el de la liquidacion original del viajero para ese
     * rubro; si el rubro no existia en la original, cae a la tarifa vigente.
     * Si el ajuste ya fue liquidado antes (devuelto y re-liquidando), precarga sus asignaciones.
     */
    private function deltaPropuesto(Solicitud $solicitud, AjusteComision $ajuste): array
    {
        // Si ya tiene asignaciones (re-liquidacion tras devolucion), devolver esas.
        if ($ajuste->asignaciones->isNotEmpty()) {
            return $ajuste->asignaciones->map(fn ($a) => [
                'rubro' => $a->rubro->value ?? $a->rubro,
                'dias' => $a->dias, 'valor_unitario' => (float) $a->valor_unitario,
                'subtotal' => (float) $a->subtotal,
            ])->values()->all();
        }

        $viajero = $ajuste->viajero;
        // Valor unitario original por rubro (asignaciones sin ajuste)
        $originales = AsignacionViatico::where('viajero_comision_id', $viajero->id)
            ->whereNull('ajuste_comision_id')->get()
            ->mapWithKeys(fn ($a) => [($a->rubro->value ?? $a->rubro) => (float) $a->valor_unitario]);
        $tarifas = TarifaViatico::all()->keyBy('rubro');

        $valorDe = fn (string $rubro) => $originales[$rubro]
            ?? (float) ($tarifas[$rubro]->valor_sugerido ?? 0);

        if ($ajuste->tipo === 'rubro') {
            $rubro = $ajuste->rubro;
            $dias = (int) $ajuste->cantidad;
            $vu = $valorDe($rubro);
            return [[
                'rubro' => $rubro, 'dias' => $dias, 'valor_unitario' => $vu,
                'subtotal' => $vu * $dias,
            ]];
        }

        // tipo 'fechas'
        $calc = app(CalculadoraRubrosViaticos::class);
        $delta = $calc->calcularDelta($ajuste->fechas_antes, $ajuste->fechas_despues);
        $filas = [];
        foreach ($delta as $rubro => $dias) {
            $vu = $valorDe($rubro);
            $filas[] = ['rubro' => $rubro, 'dias' => $dias, 'valor_unitario' => $vu, 'subtotal' => $vu * $dias];
        }
        return $filas;
    }

    /**
     * Persiste las asignaciones anexas del ajuste (delta liquidado por el contador),
     * recalcula el total_delta, marca el ajuste como liquidado y avisa al lider de
     * contabilidad. No toca la comision cerrada (los anexos llevan ajuste_comision_id).
     */
    public function updateAjuste(LiquidarAjusteRequest $request, Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('liquidarAjuste', [$solicitud, $ajuste]);

        DB::transaction(function () use ($request, $ajuste) {
            $ajuste->asignaciones()->delete(); // recrear
            foreach ($request->asignaciones as $data) {
                AsignacionViatico::create([
                    'viajero_comision_id' => $ajuste->viajero_comision_id,
                    'ajuste_comision_id'  => $ajuste->id,
                    'rubro'               => $data['rubro'],
                    'valor_unitario'      => $data['valor_unitario'],
                    'dias'                => $data['dias'],
                    'subtotal'            => $data['valor_unitario'] * $data['dias'],
                ]);
            }
            $ajuste->recalcularTotalDelta();
            $ajuste->update([
                'estado' => 'liquidado',
                'liquidado_por' => auth()->id(),
                'liquidado_en' => now(),
            ]);
        });

        $this->avisarAjusteLiquidado($ajuste->fresh());
        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Ajuste liquidado. Queda pendiente de aprobacion del lider de contabilidad.');
    }

    /** Notifica al lider de contabilidad que un ajuste quedo liquidado (accion requerida). */
    private function avisarAjusteLiquidado(AjusteComision $ajuste): void
    {
        $actor = auth()->user()->name;
        foreach (Usuario::role('contabilidad_lider')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($ajuste->solicitud, 'accion_requerida', 'aprobar', $ajuste->motivo, $actor));
        }
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
