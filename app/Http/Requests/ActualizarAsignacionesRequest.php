<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarAsignacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asignaciones'                       => 'required|array',
            'asignaciones.*.viajero_comision_id' => 'required|exists:viajeros_comision,id',
            'asignaciones.*.rubro'               => 'required|exists:tarifas_viaticos,rubro',
            'asignaciones.*.valor_unitario'      => 'required|numeric|min:0',
            'asignaciones.*.dias'                => 'required|integer|min:1',
            'pagos'                              => 'required|array',
            'pagos.*.viajero_comision_id'        => 'required|exists:viajeros_comision,id',
            'pagos.*.tipo_pago'                  => 'required|in:efectivo,transferencia',
        ];
    }
}
