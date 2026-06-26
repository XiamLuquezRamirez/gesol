<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransicionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'estado_origen'  => $this->estado_origen,
            'estado_destino' => $this->estado_destino,
            'accion'         => $this->accion,
            'comentario'     => $this->comentario,
            'metadatos'      => $this->metadatos,
            'created_at'     => $this->created_at?->format('d/m/Y H:i'),
            'usuario'        => ['id' => $this->usuario->id, 'name' => $this->usuario->name],
        ];
    }
}
