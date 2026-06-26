<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'radicado'   => $this->radicado,
            'estado'     => $this->estado,
            'total'      => $this->total,
            'tipo'       => ['clave' => $this->tipoSolicitud->clave, 'nombre' => $this->tipoSolicitud->nombre],
            'solicitante' => ['id' => $this->solicitante->id, 'name' => $this->solicitante->name],
            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
