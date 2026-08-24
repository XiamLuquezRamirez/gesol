<?php
namespace App\Services;

use App\Models\{AjusteComision, Solicitud};
use Barryvdh\DomPDF\Facade\Pdf;

class AnexoAjustePdf
{
    /**
     * Arma los datos del anexo (delta) de un ajuste ya aprobado. A diferencia de
     * la liquidacion original, aqui los dias pueden ser negativos y el total es el
     * total_delta del ajuste.
     */
    public function datos(Solicitud $solicitud, AjusteComision $ajuste): array
    {
        $ajuste->loadMissing(['viajero.empleado', 'asignaciones']);
        $comision = $solicitud->solicitable;

        $rubros = $ajuste->asignaciones->map(fn ($a) => [
            'rubro'              => $a->rubro instanceof \BackedEnum ? $a->rubro->value : (string) $a->rubro,
            'valor_unitario'     => $a->valor_unitario,
            'valor_unitario_fmt' => $this->moneda($a->valor_unitario),
            'dias'               => $a->dias,
            'subtotal'           => $a->subtotal,
            'subtotal_fmt'       => $this->moneda($a->subtotal),
        ])->all();

        $total_delta = $ajuste->asignaciones->sum('subtotal');

        return [
            'empleado'         => $ajuste->viajero->nombreMostrado,
            'lugar'            => $comision->municipio_destino ?? '—',
            'fecha_documento'  => now()->format('d/m/Y'),
            'rubros'           => $rubros,
            'total_delta_fmt'  => $this->moneda($total_delta),
            'motivo'           => $ajuste->motivo,
            'radicado'         => $solicitud->radicado,
            'tipo_ajuste'      => $ajuste->tipo,
            'realizado_por'    => 'DEPARTAMENTO DE CONTABILIDAD',
        ];
    }

    /** Genera el PDF del anexo de ajuste. */
    public function generar(Solicitud $solicitud, AjusteComision $ajuste): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.anexo_ajuste', $this->datos($solicitud, $ajuste));
    }

    public function nombreArchivo(Solicitud $solicitud, AjusteComision $ajuste): string
    {
        $empleado = str_replace(' ', '_', $ajuste->viajero->nombreMostrado ?: 'viajero');
        return 'anexo_ajuste_'.$solicitud->radicado.'_'.$empleado.'.pdf';
    }

    private function moneda($valor): string
    {
        return '$ '.number_format((float) $valor, 2, ',', '.');
    }
}
