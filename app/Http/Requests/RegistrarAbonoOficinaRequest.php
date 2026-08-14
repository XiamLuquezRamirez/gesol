<?php

namespace App\Http\Requests;

use App\Models\Solicitud;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorizacion real la hace la policy en el controlador
    }

    /** La solicitud del route binding; su cabecera de oficina tiene el estado de pago. */
    private function cabecera()
    {
        $solicitud = $this->route('solicitud');
        return $solicitud instanceof Solicitud ? $solicitud->solicitable : null;
    }

    /** ¿Es el primer abono? (la cabecera aun no tiene total_a_pagar) */
    private function esPrimerAbono(): bool
    {
        return $this->cabecera()?->total_a_pagar === null;
    }

    public function rules(): array
    {
        $reglas = [
            'monto'       => 'required|numeric|min:0.01',
            'fecha_pago'  => 'required|date',
            'soporte'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion' => 'nullable|string|max:500',
        ];

        if ($this->esPrimerAbono()) {
            // En el primer pago se define el total real a pagar.
            $reglas['total_a_pagar'] = 'required|numeric|min:0.01';
        }

        return $reglas;
    }

    /**
     * Regla de saldo: el monto no puede exceder el total (primer pago) ni el saldo
     * pendiente (abonos siguientes). Mensajes claros con el limite concreto.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $monto = (float) $this->input('monto', 0);
            if ($monto <= 0) {
                return; // ya lo cubre min:0.01
            }

            if ($this->esPrimerAbono()) {
                $total = (float) $this->input('total_a_pagar', 0);
                if ($total > 0 && $monto > $total) {
                    $validator->errors()->add('monto',
                        'El monto no puede superar el total a pagar de $'.number_format($total, 2).'.');
                }
            } else {
                $saldo = $this->cabecera()?->saldoPendiente() ?? 0.0;
                if ($monto > $saldo) {
                    $validator->errors()->add('monto',
                        'El monto no puede superar el saldo pendiente de $'.number_format($saldo, 2).'.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'monto'         => 'monto',
            'total_a_pagar' => 'total a pagar',
            'fecha_pago'    => 'fecha de pago',
            'soporte'       => 'soporte de pago',
            'observacion'   => 'observación',
        ];
    }
}
