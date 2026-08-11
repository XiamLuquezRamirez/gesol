<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        $esOficina = $this->tipoSolicitud->clave === 'OFI';
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
                'total'           => (float) $this->solicitable->total,
                'pagado'          => $this->solicitable->totalPagado(),
                'saldo'           => $this->solicitable->saldo(),
                'puede_registrar' => $usuario?->can('registrarAbono', $this->resource) ?? false,
                'abonos'          => $this->solicitable->abonos->map(fn ($a) => [
                    'id'          => $a->id,
                    'monto'       => (float) $a->monto,
                    'fecha_pago'  => optional($a->fecha_pago)->toDateString(),
                    'autor'       => $a->usuario?->name,
                    'observacion' => $a->observacion,
                ])->values(),
            ]),
            'created_at'  => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
