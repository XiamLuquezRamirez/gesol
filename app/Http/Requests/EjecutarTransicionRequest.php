<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EjecutarTransicionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accionesConRazon = ['rechazar', 'devolver'];
        $requiereRazon    = in_array($this->input('accion'), $accionesConRazon);

        return [
            'accion'      => 'required|string',
            'comentario'  => $requiereRazon ? 'required|string|min:5|max:1000' : 'nullable|string|max:1000',
            'metadatos'   => 'nullable|array',
            'metadatos.valor_pagado'  => 'nullable|numeric|min:0',
            'metadatos.fecha_pago'    => 'nullable|date',
            'metadatos.comprobante'   => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'comentario.required' => 'Debe indicar la razón para esta acción.',
            'comentario.min'      => 'La razón debe tener al menos 5 caracteres.',
        ];
    }
}
