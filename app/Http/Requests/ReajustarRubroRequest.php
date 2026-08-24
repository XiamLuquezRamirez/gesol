<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReajustarRubroRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'viajero_comision_id' => 'required|exists:viajeros_comision,id',
            'rubro'               => 'required|in:gasolina,transporte',
            'cantidad'            => 'required|integer|min:1',
            'motivo'              => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rubro.in'        => 'El reajuste de rubro solo aplica a gasolina o transporte.',
            'motivo.required' => 'Indique el motivo del reajuste.',
        ];
    }
}
