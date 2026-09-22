<?php
namespace App\Http\Controllers;

use App\Exports\ReporteExport;
use App\Models\{AbonoOficina, AjusteComision, Solicitud, SolicitudViaticos, ViajeroComision};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reportes consolidados para contabilidad/admin. El panel (index) lista los informes
 * como tarjetas; cada informe tiene su propia vista y su propio filtro de rango.
 * El rango se aplica sobre la fecha natural de cada informe:
 *  - Viaticos, personal y reajustes: fecha de comision (fecha_salida del viajero).
 *  - Oficina/pagos: fecha del abono (fecha_pago).
 */
class ReporteController extends Controller
{
    /** Rango efectivo: por defecto el mes en curso. */
    private function rango(Request $request): array
    {
        return [
            $request->query('desde') ?: now()->startOfMonth()->toDateString(),
            $request->query('hasta') ?: now()->endOfMonth()->toDateString(),
        ];
    }

    /** Panel de tarjetas con un indicador rapido por informe (del mes en curso). */
    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        return Inertia::render('Reportes/Panel', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'resumen' => [
                'viaticos'    => $this->totalViaticos($desde, $hasta),
                'oficina'     => $this->totalPagadoOficina($desde, $hasta),
                'personal'    => $this->numPersonal($desde, $hasta),
                'pendientes'  => count($this->comisionesSinComprobante($desde, $hasta)),
                'reajustes'   => $this->datosReajustes($desde, $hasta)['num'],
            ],
        ]);
    }

    // ── Vistas por informe ──────────────────────────────────────────────

    public function viaticos(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $this->detalleViaticos($desde, $hasta);

        // Exportacion (xlsx/pdf): mismas cifras que la vista, en archivo descargable.
        if ($export = $this->formatoExport($request)) {
            [$encabezados, $filas] = $this->filasViaticos($reporte);
            return $this->exportar($export, 'viaticos', 'Reporte de viáticos', $desde, $hasta, $encabezados, $filas);
        }

        return Inertia::render('Reportes/Viaticos', [
            'filtros'  => ['desde' => $desde, 'hasta' => $hasta],
            'reporte'  => $reporte,
        ]);
    }

    public function porViajero(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $this->viaticosPorViajero($desde, $hasta);

        if ($export = $this->formatoExport($request)) {
            [$encabezados, $filas] = $this->filasPorViajero($reporte);
            return $this->exportar($export, 'gasto-por-viajero', 'Gasto por viajero', $desde, $hasta, $encabezados, $filas);
        }

        return Inertia::render('Reportes/PorViajero', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'reporte' => $reporte,
        ]);
    }

    public function oficina(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $this->reporteOficina($desde, $hasta);

        if ($export = $this->formatoExport($request)) {
            [$encabezados, $filas] = $this->filasOficina($reporte);
            return $this->exportar($export, 'oficina', 'Reporte de oficina', $desde, $hasta, $encabezados, $filas);
        }

        return Inertia::render('Reportes/Oficina', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'reporte' => $reporte,
        ]);
    }

    public function personal(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $this->reportePersonal($desde, $hasta);

        if ($export = $this->formatoExport($request)) {
            [$encabezados, $filas] = $this->filasPersonal($reporte);
            return $this->exportar($export, 'personal', 'Personal en comisión', $desde, $hasta, $encabezados, $filas);
        }

        return Inertia::render('Reportes/Personal', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'reporte' => $reporte,
        ]);
    }

    public function comprobantesPendientes(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        return Inertia::render('Reportes/ComprobantesPendientes', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'reporte' => ['comisiones' => $this->comisionesSinComprobante($desde, $hasta)],
        ]);
    }

    public function reajustes(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        return Inertia::render('Reportes/Reajustes', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'reporte' => $this->datosReajustes($desde, $hasta),
        ]);
    }

    // ── Calculo de cada informe ─────────────────────────────────────────

    /**
     * Detalle de viaticos: por cada comision del rango, sus viajeros con rubros
     * (total) y sus comprobantes de pago (nombre + a que empleado corresponde +
     * enlace de descarga). Total por comision y total general.
     */
    private function detalleViaticos(string $desde, string $hasta): array
    {
        $viajeros = ViajeroComision::with([
                'empleado',
                'asignaciones',
                'archivos' => fn ($q) => $q->where('tipo', 'comprobante'),
                'solicitudViaticos.solicitud',
            ])
            ->whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud',
                fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada', 'cancelada']))
            ->get();

        // Agrupar por comision (solicitud). Cada comision totaliza sus viajeros.
        $porComision = [];
        $totalGeneral = 0.0;

        foreach ($viajeros as $v) {
            $solicitud = $v->solicitudViaticos?->solicitud;
            if (! $solicitud) {
                continue;
            }
            $sid = $solicitud->id;

            if (! isset($porComision[$sid])) {
                $porComision[$sid] = [
                    'solicitud_id' => $sid,
                    'radicado'     => $solicitud->radicado,
                    'nombre'       => $v->solicitudViaticos->nombre_comision,
                    'estado'       => $solicitud->estado,
                    'total'        => 0.0,
                    'viajeros'     => [],
                ];
            }

            $totalViajero = (float) $v->asignaciones->sum('subtotal');
            $porComision[$sid]['total'] += $totalViajero;
            $totalGeneral += $totalViajero;

            $porComision[$sid]['viajeros'][] = [
                'nombre'       => $v->nombreMostrado,
                'total'        => round($totalViajero, 2),
                'rubros'       => $v->asignaciones->map(fn ($a) => [
                    'rubro'    => $a->rubro instanceof \BackedEnum ? $a->rubro->value : (string) $a->rubro,
                    'subtotal' => (float) $a->subtotal,
                ])->values(),
                'comprobantes' => $v->archivos->map(fn ($ar) => [
                    'id'        => $ar->id,
                    'nombre'    => $ar->nombre,
                    'url'       => route('viaticos.archivos.descargar', [$solicitud->id, $v->id, $ar->id], false),
                ])->values(),
            ];
        }

        $comisiones = collect($porComision)
            ->map(function ($c) {
                $c['total'] = round($c['total'], 2);
                return $c;
            })
            ->sortByDesc('total')
            ->values();

        return [
            'total'          => round($totalGeneral, 2),
            'num_comisiones' => $comisiones->count(),
            'num_viajeros'   => $viajeros->count(),
            'comisiones'     => $comisiones,
        ];
    }

    /**
     * Mismo universo que detalleViaticos, pero PIVOTEADO POR EMPLEADO: una entrada
     * por viajero con el total de TODAS sus comisiones; cada comision desglosa sus
     * rubros y sus comprobantes de pago descargables.
     */
    private function viaticosPorViajero(string $desde, string $hasta): array
    {
        $viajeros = ViajeroComision::with([
                'empleado.area',
                'asignaciones',
                'archivos' => fn ($q) => $q->where('tipo', 'comprobante'),
                'solicitudViaticos.solicitud',
            ])
            ->whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud',
                fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada', 'cancelada']))
            ->get();

        $porEmpleado = [];
        $totalGeneral = 0.0;
        $numComisiones = 0;

        foreach ($viajeros as $v) {
            $solicitud = $v->solicitudViaticos?->solicitud;
            if (! $solicitud) {
                continue;
            }

            $nombre = $v->nombreMostrado;
            $clave  = $v->empleado_id ? 'e'.$v->empleado_id : 'x'.mb_strtolower($nombre);

            if (! isset($porEmpleado[$clave])) {
                $porEmpleado[$clave] = [
                    'empleado'       => $nombre,
                    'identificacion' => $v->identificacionMostrada,
                    'area'           => $v->empleado?->area?->nombre ?? '—',
                    'total'          => 0.0,
                    'num_comisiones' => 0,
                    'comisiones'     => [],
                ];
            }

            $totalViajero = (float) $v->asignaciones->sum('subtotal');
            $porEmpleado[$clave]['total'] += $totalViajero;
            $porEmpleado[$clave]['num_comisiones']++;
            $totalGeneral += $totalViajero;
            $numComisiones++;

            $porEmpleado[$clave]['comisiones'][] = [
                'solicitud_id' => $solicitud->id,
                'radicado'     => $solicitud->radicado,
                'nombre'       => $v->solicitudViaticos->nombre_comision,
                'estado'       => $solicitud->estado,
                'total'        => round($totalViajero, 2),
                'rubros'       => $v->asignaciones->map(fn ($a) => [
                    'rubro'    => $a->rubro instanceof \BackedEnum ? $a->rubro->value : (string) $a->rubro,
                    'subtotal' => (float) $a->subtotal,
                ])->values(),
                'comprobantes' => $v->archivos->map(fn ($ar) => [
                    'id'     => $ar->id,
                    'nombre' => $ar->nombre,
                    'url'    => route('viaticos.archivos.descargar', [$solicitud->id, $v->id, $ar->id], false),
                ])->values(),
            ];
        }

        $viajerosSalida = collect($porEmpleado)
            ->map(function ($e) {
                $e['total'] = round($e['total'], 2);
                return $e;
            })
            ->sortByDesc('total')
            ->values();

        return [
            'total'          => round($totalGeneral, 2),
            'num_empleados'  => $viajerosSalida->count(),
            'num_comisiones' => $numComisiones,
            'viajeros'       => $viajerosSalida,
        ];
    }

    private function totalViaticos(string $desde, string $hasta): float
    {
        return round((float) ViajeroComision::whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud',
                fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada', 'cancelada']))
            ->withSum('asignaciones', 'subtotal')
            ->get()
            ->sum('asignaciones_sum_subtotal'), 2);
    }

    /** Oficina: pagos (abonos) del rango por solicitud, con total/pagado/saldo. */
    private function reporteOficina(string $desde, string $hasta): array
    {
        $abonos = AbonoOficina::with(['solicitudOficina.solicitud.solicitante', 'solicitudOficina.solicitud.area'])
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->get();

        $porSolicitud = [];
        $totalPagado = 0.0;

        foreach ($abonos as $ab) {
            $cab = $ab->solicitudOficina;
            $sol = $cab?->solicitud;
            if (! $sol) {
                continue;
            }
            $id = $sol->id;
            if (! isset($porSolicitud[$id])) {
                $porSolicitud[$id] = [
                    'solicitud_id' => $id,
                    'radicado'     => $sol->radicado,
                    'solicitante'  => $sol->solicitante->name ?? '—',
                    'area'         => $sol->area->nombre ?? '—',
                    'estado'       => $sol->estado,
                    'total'        => $cab->total_a_pagar !== null ? (float) $cab->total_a_pagar : null,
                    'pagado_rango' => 0.0,
                    'saldo'        => $cab->saldoPendiente(),
                ];
            }
            $porSolicitud[$id]['pagado_rango'] += (float) $ab->monto;
            $totalPagado += (float) $ab->monto;
        }

        $filas = collect($porSolicitud)->map(function ($f) {
            $f['pagado_rango'] = round($f['pagado_rango'], 2);
            return $f;
        })->values();

        return [
            'total_aprobado'  => round($filas->sum(fn ($f) => $f['total'] ?? 0), 2),
            'total_pagado'    => round($totalPagado, 2),
            'saldo_total'     => round($filas->sum('saldo'), 2),
            'num_solicitudes' => $filas->count(),
            'solicitudes'     => $filas,
        ];
    }

    private function totalPagadoOficina(string $desde, string $hasta): float
    {
        return round((float) AbonoOficina::whereBetween('fecha_pago', [$desde, $hasta])->sum('monto'), 2);
    }

    /** Personal en comision en el rango: por empleado, comisiones y dias-persona. */
    private function reportePersonal(string $desde, string $hasta): array
    {
        $viajeros = ViajeroComision::with(['empleado.area', 'solicitudViaticos.solicitud'])
            ->whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud',
                fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada', 'cancelada']))
            ->get();

        $porEmpleado = [];
        $totalDias = 0;

        foreach ($viajeros as $v) {
            $nombre = $v->nombreMostrado;
            $area   = $v->empleado?->area?->nombre ?? '—';
            $clave  = $v->empleado_id ? 'e'.$v->empleado_id : 'x'.mb_strtolower($nombre);

            $dias = 1;
            if ($v->fecha_salida && $v->fecha_regreso) {
                $dias = $v->fecha_salida->diffInDays($v->fecha_regreso) + 1;
            }
            $totalDias += $dias;

            if (! isset($porEmpleado[$clave])) {
                $porEmpleado[$clave] = ['empleado' => $nombre, 'area' => $area, 'num_comisiones' => 0, 'dias' => 0];
            }
            $porEmpleado[$clave]['num_comisiones']++;
            $porEmpleado[$clave]['dias'] += $dias;
        }

        return [
            'num_empleados' => count($porEmpleado),
            'total_dias'    => $totalDias,
            'empleados'     => collect($porEmpleado)->sortByDesc('dias')->values(),
        ];
    }

    private function numPersonal(string $desde, string $hasta): int
    {
        return ViajeroComision::whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud',
                fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada', 'cancelada']))
            ->distinct('empleado_id')
            ->count('empleado_id');
    }

    /**
     * Comisiones cerradas dentro del rango cuyos viajeros no tienen ningun
     * comprobante de transferencia adjunto (alerta para auditoria).
     */
    private function comisionesSinComprobante(string $desde, string $hasta): array
    {
        $viajeros = ViajeroComision::with(['empleado', 'solicitudViaticos.solicitud', 'archivos'])
            ->whereBetween('fecha_salida', [$desde, $hasta])
            ->whereHas('solicitudViaticos.solicitud', fn ($q) => $q->where('estado', 'cerrada'))
            ->get();

        $porComision = [];
        foreach ($viajeros as $v) {
            $sol = $v->solicitudViaticos?->solicitud;
            if (! $sol) {
                continue;
            }
            $tieneComprobante = $v->archivos->where('tipo', 'comprobante')->isNotEmpty();
            if ($tieneComprobante) {
                continue;
            }
            $sid = $sol->id;
            if (! isset($porComision[$sid])) {
                $porComision[$sid] = [
                    'solicitud_id' => $sid,
                    'radicado'     => $sol->radicado,
                    'nombre'       => $v->solicitudViaticos->nombre_comision,
                    'sin_comprobante' => [],
                ];
            }
            $porComision[$sid]['sin_comprobante'][] = $v->nombreMostrado;
        }

        return collect($porComision)->values()->all();
    }

    /** Reajustes (ajustes post-cierre) del rango, con su delta y estado. */
    private function datosReajustes(string $desde, string $hasta): array
    {
        $ajustes = AjusteComision::with(['viajero.empleado', 'solicitud'])
            ->whereHas('viajero', fn ($q) => $q->whereBetween('fecha_salida', [$desde, $hasta]))
            ->orderByDesc('created_at')
            ->get();

        $filas = $ajustes->map(fn ($a) => [
            'id'        => $a->id,
            'radicado'  => $a->solicitud->radicado ?? '—',
            'solicitud_id' => $a->solicitud_id,
            'viajero'   => $a->viajero?->nombreMostrado ?? '—',
            'tipo'      => $a->tipo,
            'estado'    => $a->estado,
            'delta'     => (float) $a->total_delta,
        ])->values();

        return [
            'num'         => $filas->count(),
            'total_delta' => round($filas->sum('delta'), 2),
            'ajustes'     => $filas,
        ];
    }

    // ── Exportacion (Excel / PDF) ───────────────────────────────────────

    /** Formato de exportacion pedido ('xlsx' | 'pdf') o null si no se pidio. */
    private function formatoExport(Request $request): ?string
    {
        $f = strtolower((string) $request->query('export'));
        return in_array($f, ['xlsx', 'pdf'], true) ? $f : null;
    }

    /** Formatea un numero como moneda para las celdas de texto (Excel/PDF). */
    private function money($n): string
    {
        return '$ '.number_format((float) $n, 0, ',', '.');
    }

    /**
     * Genera la descarga en el formato pedido. Excel usa ReporteExport (filas planas);
     * PDF usa una plantilla Blade generica que pinta encabezados + filas + titulo.
     */
    private function exportar(string $formato, string $slug, string $titulo, string $desde, string $hasta, array $encabezados, array $filas)
    {
        $archivo = "reporte-{$slug}-{$desde}_a_{$hasta}";

        if ($formato === 'xlsx') {
            return Excel::download(new ReporteExport($titulo, $encabezados, $filas), "{$archivo}.xlsx");
        }

        // PDF (dompdf) con una plantilla generica de tabla.
        return Pdf::loadView('pdf.reporte', [
            'titulo'      => $titulo,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'encabezados' => $encabezados,
            'filas'       => $filas,
        ])->download("{$archivo}.pdf");
    }

    /** Viaticos aplanado: una fila por viajero (comision, viajero, total, comprobantes). */
    private function filasViaticos(array $reporte): array
    {
        $encabezados = ['Radicado', 'Comisión', 'Viajero', 'Total viajero', 'Comprobantes'];
        $filas = [];
        foreach ($reporte['comisiones'] as $c) {
            foreach ($c['viajeros'] as $v) {
                $filas[] = [
                    $c['radicado'],
                    $c['nombre'],
                    $v['nombre'],
                    $this->money($v['total']),
                    collect($v['comprobantes'])->pluck('nombre')->implode(', ') ?: 'Sin comprobante',
                ];
            }
        }
        // Fila total al final.
        $filas[] = ['', '', '', 'TOTAL: '.$this->money($reporte['total']), ''];
        return [$encabezados, $filas];
    }

    /** Por viajero aplanado: una fila por comision de cada viajero. */
    private function filasPorViajero(array $reporte): array
    {
        $encabezados = ['Empleado', 'Área', 'Comisión', 'Radicado', 'Total comisión', 'Comprobantes'];
        $filas = [];
        foreach ($reporte['viajeros'] as $v) {
            foreach ($v['comisiones'] as $c) {
                $filas[] = [
                    $v['empleado'],
                    $v['area'],
                    $c['nombre'],
                    $c['radicado'],
                    $this->money($c['total']),
                    collect($c['comprobantes'])->pluck('nombre')->implode(', ') ?: 'Sin comprobante',
                ];
            }
        }
        $filas[] = ['', '', '', '', 'TOTAL: '.$this->money($reporte['total']), ''];
        return [$encabezados, $filas];
    }

    /** Oficina aplanado: una fila por solicitud. */
    private function filasOficina(array $reporte): array
    {
        $encabezados = ['Radicado', 'Solicitante', 'Área', 'Total a pagar', 'Pagado (rango)', 'Saldo'];
        $filas = [];
        foreach ($reporte['solicitudes'] as $s) {
            $filas[] = [
                $s['radicado'],
                $s['solicitante'],
                $s['area'],
                $s['total'] !== null ? $this->money($s['total']) : '—',
                $this->money($s['pagado_rango']),
                $this->money($s['saldo']),
            ];
        }
        $filas[] = ['', '', 'TOTALES', $this->money($reporte['total_aprobado']), $this->money($reporte['total_pagado']), $this->money($reporte['saldo_total'])];
        return [$encabezados, $filas];
    }

    /** Personal aplanado: una fila por empleado. */
    private function filasPersonal(array $reporte): array
    {
        $encabezados = ['Empleado', 'Área', 'Comisiones', 'Días'];
        $filas = [];
        foreach ($reporte['empleados'] as $e) {
            $filas[] = [$e['empleado'], $e['area'], $e['num_comisiones'], $e['dias']];
        }
        $filas[] = ['TOTAL', '', '', $reporte['total_dias']];
        return [$encabezados, $filas];
    }
}
