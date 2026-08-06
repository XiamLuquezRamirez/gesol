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
            'beneficiarios'          => 'required|array|min:1',
            'beneficiarios.*'        => 'exists:empleados,id',
            'area_id'                => 'required|exists:areas,id',
            'urgencia'               => 'required|in:baja,media,alta',
            'justificacion'          => 'required|string|max:2000',
            'items'                  => 'required|array|min:1',
            'items.*.nombre'         => 'required|string|max:255',
            'items.*.categoria'      => 'required|in:producto,servicio',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.costo_estimado' => 'nullable|numeric|min:0',
            'items.*.notas'          => 'nullable|string|max:500',
        ];
    }

    /**
     * Nombres legibles de los campos para los mensajes de error,
     * asi el usuario ve "Departamento" y no "area id".
     */
    public function attributes(): array
    {
        return [
            'beneficiarios'          => 'beneficiarios',
            'beneficiarios.*'        => 'beneficiario',
            'area_id'                => 'departamento',
            'urgencia'               => 'urgencia',
            'justificacion'          => 'justificación',
            'items'                  => 'ítems',
            'items.*.nombre'         => 'nombre del ítem',
            'items.*.categoria'      => 'categoría del ítem',
            'items.*.cantidad'       => 'cantidad del ítem',
            'items.*.costo_estimado' => 'costo estimado',
        ];
    }
}
