<?php
namespace App\Http\Controllers;

use App\Models\ViajeroComision;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComisionesRrhhController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $viajeros = ViajeroComision::with(['empleado', 'solicitudViaticos.solicitud'])
            // Solo comisiones cuya solicitud ya fue cerrada (informe enviado a RR. HH.).
            ->whereHas('solicitudViaticos.solicitud', fn ($q) => $q->where('estado', 'cerrada'))
            // Solapamiento con el rango: esta fuera si sale antes del "hasta" y regresa despues del "desde".
            ->when($desde, fn ($q) => $q->where('fecha_regreso', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha_salida', '<=', $hasta))
            ->orderBy('fecha_salida')
            ->get();

        $comisionados = $viajeros->map(function ($v) {
            $comision = $v->solicitudViaticos;
            $solicitud = $comision?->solicitud;

            return [
                'id'             => $v->id,
                'empleado'       => trim(($v->empleado->nombres ?? '').' '.($v->empleado->apellidos ?? '')),
                'identificacion' => $v->empleado->identificacion ?? null,
                'comision'       => $comision->nombre_comision ?? null,
                'destino'        => $comision->municipio_destino ?? null,
                'radicado'       => $solicitud->radicado ?? null,
                'estado'         => $solicitud->estado ?? null,
                'fecha_salida'   => optional($v->fecha_salida)->toDateString() ?? $v->fecha_salida,
                'hora_salida'    => $v->hora_salida,
                'fecha_regreso'  => optional($v->fecha_regreso)->toDateString() ?? $v->fecha_regreso,
                'hora_regreso'   => $v->hora_regreso,
            ];
        })->values();

        return Inertia::render('Rrhh/Comisiones', [
            'comisionados' => $comisionados,
            'filtros'      => ['desde' => $desde, 'hasta' => $hasta],
        ]);
    }
}
