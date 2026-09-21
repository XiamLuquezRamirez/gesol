<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        $esOficina = $this->tipoSolicitud->clave === 'OFI';
        $esObra    = $this->tipoSolicitud->clave === 'OBR';
        $usuario   = $request->user();

        return [
            'id'          => $this->id,
            'radicado'    => $this->radicado,
            'estado'      => $this->estado,
            'total'       => $this->total,
            'tipo'        => ['clave' => $this->tipoSolicitud->clave, 'nombre' => $this->tipoSolicitud->nombre],
            'solicitante' => ['id' => $this->solicitante->id, 'name' => $this->solicitante->name],
            'area'        => $this->area ? ['id' => $this->area->id, 'nombre' => $this->area->nombre] : null,
            'solicitable' => $this->solicitable,
            'transiciones' => TransicionResource::collection($this->transiciones),
            'beneficiarios' => $this->when($esOficina, fn () => $this->solicitable->beneficiarios->map(fn ($e) => [
                'id'             => $e->id,
                'nombre'         => trim($e->nombres.' '.$e->apellidos),
                'identificacion' => $e->identificacion,
            ])->values()),
            'institucional' => $this->when($esOficina, fn () => (bool) ($this->area?->es_general)),
            'cotizacion'  => $this->when($esOficina, fn () => [
                'comentario'   => $this->solicitable->comentario_contador,
                'archivos'     => $this->solicitable->cotizaciones->map(fn ($c) => [
                    'id'              => $c->id,
                    'nombre'          => $c->nombre_original,
                    'autor'           => $c->usuario?->name,
                    'puede_gestionar' => $usuario?->can('gestionarCotizacion', [$this->resource, $c]) ?? false,
                ])->values(),
                'puede_anexar' => $usuario?->can('anexarCotizacion', $this->resource) ?? false,
            ]),
            'pagos'       => $this->when($esOficina, fn () => [
                'total_a_pagar'   => $this->solicitable->total_a_pagar !== null ? (float) $this->solicitable->total_a_pagar : null,
                'total_estimado'  => (float) $this->solicitable->total,
                'tiene_total'     => $this->solicitable->total_a_pagar !== null,
                'pagado'          => $this->solicitable->totalPagado(),
                'saldo'           => $this->solicitable->saldoPendiente(),
                'puede_registrar' => $usuario?->can('registrarAbono', $this->resource) ?? false,
                'abonos'          => $this->solicitable->abonos->map(fn ($a) => [
                    'id'          => $a->id,
                    'monto'       => (float) $a->monto,
                    'fecha_pago'  => optional($a->fecha_pago)->toDateString(),
                    'autor'       => $a->usuario?->name,
                    'observacion' => $a->observacion,
                ])->values(),
            ]),
            'obra'        => $this->when($esObra, fn () => [
                'items' => $this->solicitable->items->map(fn ($i) => [
                    'id'             => $i->id,
                    'especificacion' => $i->especificacion,
                    'unidad'         => $i->unidad,
                    'cantidad'       => (float) $i->cantidad,
                    'sede'           => $i->sede,
                    'valor_unitario' => $i->valor_unitario !== null ? (float) $i->valor_unitario : null,
                    'subtotal'       => $i->subtotal !== null ? (float) $i->subtotal : null,
                ])->values(),
                'cotizaciones' => $this->solicitable->cotizaciones->map(fn ($c) => [
                    'id'     => $c->id,
                    'nombre' => $c->nombre_original,
                    'tipo'   => $c->tipo,
                    'autor'  => $c->usuario?->name,
                ])->values(),
                'contrato' => $this->solicitable->contrato
                    ? ['id' => $this->solicitable->contrato->id, 'descripcion' => $this->solicitable->contrato->descripcion]
                    : null,
                'pagos' => [
                    'total_a_pagar' => $this->solicitable->total_a_pagar !== null ? (float) $this->solicitable->total_a_pagar : null,
                    'pagado'        => $this->solicitable->totalPagado(),
                    'saldo'         => $this->solicitable->saldoPendiente(),
                    'abonos'        => $this->solicitable->abonos->map(fn ($a) => [
                        'id'              => $a->id,
                        'monto'           => (float) $a->monto,
                        'retencion_tipo'  => $a->retencion_tipo,
                        'retencion_valor' => $a->retencion_valor !== null ? (float) $a->retencion_valor : null,
                        'retencion_monto' => $a->retencion_monto !== null ? (float) $a->retencion_monto : null,
                        'retenedor'       => $a->retenedor?->name,
                        'fecha_pago'      => optional($a->fecha_pago)->toDateString(),
                        'soporte'         => $a->soporte_nombre,
                        'soporte_url'     => $a->soporte_path ? route('obra.abono.soporte', [$this->id, $a->id], false) : null,
                        'autor'           => $a->usuario?->name,
                        'observacion'     => $a->observacion,
                    ])->values(),
                ],
            ]),
            'created_at'  => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
