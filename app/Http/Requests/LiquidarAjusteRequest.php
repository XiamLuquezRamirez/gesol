<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiquidarAjusteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'asignaciones'                  => ['present', 'array'],
            'asignaciones.*.rubro'          => ['required', 'exists:tarifas_viaticos,rubro'],
            'asignaciones.*.valor_unitario' => ['required', 'numeric', 'min:0'],
            'asignaciones.*.dias'           => ['required', 'integer'], // puede ser negativo (resta)
        ];
    }
}
