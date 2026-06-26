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
        return [
            'accion'      => 'required|string',
            'comentario'  => 'nullable|string|max:1000',
            'metadatos'   => 'nullable|array',
            'metadatos.valor_pagado'  => 'nullable|numeric|min:0',
            'metadatos.fecha_pago'    => 'nullable|date',
            'metadatos.comprobante'   => 'nullable|string|max:255',
        ];
    }
}
