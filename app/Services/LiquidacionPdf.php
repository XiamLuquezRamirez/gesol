<?php
namespace App\Services;

use App\Models\{Solicitud, ViajeroComision};
use Barryvdh\DomPDF\Facade\Pdf;

class LiquidacionPdf
{
    /**
     * Arma los datos del formato de liquidación para un viajero concreto.
     */
    public function datos(Solicitud $solicitud, ViajeroComision $viajero): array
    {
        $comision = $solicitud->solicitable;
        $viajero->loadMissing(['empleado', 'asignaciones']);

        $rubros = $viajero->asignaciones->map(fn ($a) => [
            'rubro'              => $a->rubro instanceof \BackedEnum ? $a->rubro->value : (string) $a->rubro,
            'valor_unitario'     => $a->valor_unitario,
            'valor_unitario_fmt' => $this->moneda($a->valor_unitario),
            'dias'               => $a->dias,
            'subtotal'           => $a->subtotal,
            'subtotal_fmt'       => $this->moneda($a->subtotal),
        ])->all();

        $total = $viajero->asignaciones->sum('subtotal');

        // "Aprobado por": el usuario que ejecutó la transición de aprobación.
        $aprobacion = $solicitud->transiciones
            ->firstWhere('accion', 'aprobar');

        return [
            'empleado'         => $viajero->nombreMostrado,
            'lugar'            => $comision->municipio_destino ?? '—',
            'fecha_comision'   => optional($viajero->fecha_salida)->format('d/m/Y') ?? $viajero->fecha_salida,
            'fecha_documento'  => now()->format('d/m/Y'),
            'rubros'           => $rubros,
            'total_fmt'        => $this->moneda($total),
            'es_efectivo'      => $viajero->tipo_pago === 'efectivo',
            'es_transferencia' => $viajero->tipo_pago === 'transferencia',
            'realizado_por'    => 'DEPARTAMENTO DE CONTABILIDAD',
            'aprobado_por'     => strtoupper('LIBANIS ARGUELLES DAZA'),
            'radicado'         => $solicitud->radicado,
        ];
    }

    /**
     * Genera el PDF del formato de liquidación de un viajero.
     */
    public function generar(Solicitud $solicitud, ViajeroComision $viajero): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.liquidacion_viajero', $this->datos($solicitud, $viajero));
    }

    public function nombreArchivo(Solicitud $solicitud, ViajeroComision $viajero): string
    {
        $empleado = str_replace(' ', '_', $viajero->nombreMostrado ?: 'viajero');
        return 'liquidacion_'.$solicitud->radicado.'_'.$empleado.'.pdf';
    }

    private function moneda($valor): string
    {
        return '$ '.number_format((float) $valor, 2, ',', '.');
    }
}
