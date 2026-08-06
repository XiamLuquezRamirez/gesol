<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorizacion real la hace la policy en el controlador
    }

    public function rules(): array
    {
        return [
            'monto'       => 'required|numeric|min:0.01',
            'fecha_pago'  => 'required|date',
            'soporte'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'monto'       => 'monto',
            'fecha_pago'  => 'fecha de pago',
            'soporte'     => 'soporte de pago',
            'observacion' => 'observación',
        ];
    }
}
