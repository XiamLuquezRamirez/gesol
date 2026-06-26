<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudViaticosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_comision'   => 'required|string|max:255',
            'municipio_destino' => 'required|string|max:255',
            'motivo'            => 'required|string|max:2000',
            'fecha_salida'      => 'required|date|after_or_equal:today',
            'fecha_regreso'     => 'required|date|after_or_equal:fecha_salida',
            'viajeros'          => 'required|array|min:1',
            'viajeros.*'        => 'required|exists:usuarios,id',
        ];
    }
}
