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
        $nombre = $request->query('nombre');
        $comision = $request->query('comision');

        $viajeros = ViajeroComision::with(['empleado', 'asignaciones', 'solicitudViaticos.solicitud'])
            // Comisiones ya reportadas a RR. HH. (desde que el lider las envia), en cualquier
            // estado activo o cerrado. Se excluyen las que aun estan en borrador o fueron rechazadas.
            ->whereHas('solicitudViaticos.solicitud', fn ($q) => $q->whereNotIn('estado', ['borrador', 'rechazada']))
            // Solapamiento con el rango: esta fuera si sale antes del "hasta" y regresa despues del "desde".
            ->when($desde, fn ($q) => $q->where('fecha_regreso', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha_salida', '<=', $hasta))
            ->when($nombre, fn ($q) => $q->whereHas('empleado', fn ($q) => $q->where('nombres', 'like', '%'.$nombre.'%')->orWhere('apellidos', 'like', '%'.$nombre.'%')))
            // Filtro por nombre de la comision.
            ->when($comision, fn ($q) => $q->whereHas('solicitudViaticos', fn ($q) => $q->where('nombre_comision', 'like', '%'.$comision.'%')))
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
                'tipo_pago'      => $v->tipo_pago,
                'rubros'         => $v->asignaciones->map(fn ($a) => [
                    'rubro'          => $a->rubro instanceof \BackedEnum ? $a->rubro->value : (string) $a->rubro,
                    'valor_unitario' => (float) $a->valor_unitario,
                    'dias'           => (int) $a->dias,
                    'subtotal'       => (float) $a->subtotal,
                ])->values(),
                'total'          => (float) $v->asignaciones->sum('subtotal'),
            ];
        })->values();

        return Inertia::render('Rrhh/Comisiones', [
            'comisionados' => $comisionados,
            'filtros'      => ['desde' => $desde, 'hasta' => $hasta, 'nombre' => $nombre, 'comision' => $comision],
        ]);
    }
}
