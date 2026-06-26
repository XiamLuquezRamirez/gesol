<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiario_id'        => 'required|exists:usuarios,id',
            'area_id'                => 'required|exists:areas,id',
            'urgencia'               => 'required|in:baja,media,alta',
            'justificacion'          => 'required|string|max:2000',
            'items'                  => 'required|array|min:1',
            'items.*.nombre'         => 'required|string|max:255',
            'items.*.categoria'      => 'required|in:producto,servicio',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.costo_estimado' => 'required|numeric|min:0',
            'items.*.notas'          => 'nullable|string|max:500',
        ];
    }
}
