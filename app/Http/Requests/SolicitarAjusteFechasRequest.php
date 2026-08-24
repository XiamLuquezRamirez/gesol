<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarAjusteFechasRequest extends FormRequest
{
    public function authorize(): bool { return true; } // autoriza el controlador via policy 'ajustar'

    public function rules(): array
    {
        return [
            'viajero_comision_id' => ['required', 'integer', 'exists:viajeros_comision,id'],
            'fecha_salida'        => ['required', 'date'],
            'hora_salida'         => ['required', 'string'],
            'fecha_regreso'       => ['required', 'date', 'after_or_equal:fecha_salida'],
            'hora_regreso'        => ['required', 'string'],
            'motivo'              => ['required', 'string', 'max:2000'],
        ];
    }
}
