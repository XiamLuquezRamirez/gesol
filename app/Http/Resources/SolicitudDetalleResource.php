<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
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
            'cotizacion'  => $this->when($this->tipoSolicitud->clave === 'OFI', fn () => [
                'comentario'   => $this->solicitable->comentario_contador,
                'archivos'     => $this->solicitable->cotizaciones->map(fn ($c) => [
                    'id'     => $c->id,
                    'nombre' => $c->nombre_original,
                ])->values(),
                'puede_anexar' => $request->user()?->can('anexarCotizacion', $this->resource) ?? false,
            ]),
            'created_at'  => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
