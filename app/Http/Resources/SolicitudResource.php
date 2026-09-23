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
            // Datos de la card de viáticos: municipios destino y contratos únicos
            // de los viajeros (para "Viáticos - Municipios" y el contrato relacionado).
            'viaticos'   => $this->when($this->tipoSolicitud->clave === 'VIA', fn () => $this->datosViaticos()),
            // Datos de la card de oficina: justificación (para identificar el gasto
            // sin abrir el detalle). Es columna directa, sin relaciones extra.
            'oficina'    => $this->when($this->tipoSolicitud->clave === 'OFI', fn () => $this->datosOficina()),
            // Datos de la card de obra: solicitante y contrato relacionado (si ya
            // se relacionó); permite mostrar algo sensato para OBR en el listado.
            'obra'       => $this->when($this->tipoSolicitud->clave === 'OBR', fn () => $this->datosObra()),
        ];
    }

    /** Justificación de la solicitud de oficina (columna directa). */
    private function datosOficina(): array
    {
        return [
            'justificacion' => $this->solicitable?->justificacion,
        ];
    }

    /** Solicitante, contrato relacionado, observación y fecha de entrega de la obra. */
    private function datosObra(): array
    {
        return [
            'nombre_solicitante' => $this->solicitable?->nombre_solicitante,
            'contrato'           => $this->solicitable?->contrato?->descripcion,
            'observacion'        => $this->solicitable?->observacion,
            'fecha_entrega'      => $this->solicitable?->fecha_entrega
                ? $this->solicitable->fecha_entrega->format('Y-m-d') : null,
        ];
    }

    /**
     * Datos de la comisión de viáticos para la card: nombre de la comisión,
     * municipios destino, contratos únicos, nombres de viajeros y el rango de
     * fechas (min salida -> max regreso entre viajeros).
     */
    private function datosViaticos(): array
    {
        $municipios = $this->solicitable?->municipios->pluck('nombre')->values()->all() ?? [];
        $viajerosCol = $this->solicitable?->viajeros ?? collect();

        $contratos = $viajerosCol->pluck('contrato')->filter()
            ->pluck('descripcion')->unique()->values()->all();

        $viajeros = $viajerosCol->map(fn ($v) => $v->nombreMostrado)->filter()->values()->all();

        // Rango de fechas de la comision: min salida -> max regreso entre viajeros.
        $salidas  = $viajerosCol->pluck('fecha_salida')->filter();
        $regresos = $viajerosCol->pluck('fecha_regreso')->filter();
        $fechaSalida  = $salidas->min();
        $fechaRegreso = $regresos->max();

        return [
            'nombre_comision' => $this->solicitable?->nombre_comision,
            'municipios'      => $municipios,
            'contratos'       => $contratos,
            'viajeros'        => $viajeros,
            'num_viajeros'    => $viajerosCol->count(),
            'fecha_salida'    => $fechaSalida ? $fechaSalida->format('Y-m-d') : null,
            'fecha_regreso'   => $fechaRegreso ? $fechaRegreso->format('Y-m-d') : null,
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
