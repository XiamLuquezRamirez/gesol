<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarAjusteRubroRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'viajero_comision_id' => ['required', 'integer', 'exists:viajeros_comision,id'],
            'rubro'               => ['required', 'in:gasolina,transporte'],
            'cantidad'            => ['required', 'integer'], // puede ser negativa (disminuir)
            'motivo'              => ['required', 'string', 'max:2000'],
        ];
    }
}
