<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustarComisionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motivo'                          => 'required|string|max:2000',
            'viajeros'                        => 'required|array|min:1',
            'viajeros.*.viajero_comision_id'  => 'required|exists:viajeros_comision,id',
            'viajeros.*.fecha_salida'         => 'required|date',
            'viajeros.*.hora_salida'          => 'required|string|max:5',
            'viajeros.*.fecha_regreso'        => 'required|date',
            'viajeros.*.hora_regreso'         => 'required|string|max:5',
        ];
    }

    public function messages(): array
    {
        return ['motivo.required' => 'Indique el motivo del ajuste.'];
    }
}
