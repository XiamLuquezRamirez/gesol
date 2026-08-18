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
            'total'      => $this->valorMostrado(),
            'tipo'       => ['clave' => $this->tipoSolicitud->clave, 'nombre' => $this->tipoSolicitud->nombre],
            'solicitante' => ['id' => $this->solicitante->id, 'name' => $this->solicitante->name],
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Valor a mostrar en la lista. En oficina el valor real lo asigna contabilidad
     * en el primer pago (total_a_pagar); si aun no existe, null (la UI muestra "—").
     * En viaticos y demas, es el total de la solicitud (suma de asignaciones).
     */
    private function valorMostrado()
    {
        if ($this->tipoSolicitud->clave === 'OFI') {
            $tap = $this->solicitable?->total_a_pagar;
            return $tap !== null ? (float) $tap : null;
        }
        return $this->total;
    }
}
