<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudViaticosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_comision'                    => 'required|string|max:255',
            'municipios'                         => 'required|array|min:1',
            'municipios.*'                       => 'exists:municipios,id',
            'observacion'                        => 'nullable|string|max:2000',
            'viajeros'                           => 'required|array|min:1',
            'viajeros.*.es_externo'              => 'nullable|boolean',
            'viajeros.*.contrato_id'             => 'nullable|exists:contratos,id',
            'viajeros.*.empleado_id'             => 'nullable|exists:empleados,id',
            'viajeros.*.nombre_externo'          => 'nullable|string|max:255',
            'viajeros.*.identificacion_externo'  => 'nullable|string|max:50',
            'viajeros.*.motivo'                  => 'required|string|max:2000',
            'viajeros.*.fecha_salida'            => 'required|date',
            'viajeros.*.hora_salida'             => 'required|string|max:5',
            'viajeros.*.fecha_regreso'           => 'required|date',
            'viajeros.*.hora_regreso'            => 'required|string|max:5',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('viajeros', []) as $i => $v) {
                $externo = filter_var($v['es_externo'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($externo) {
                    // El nombre es obligatorio; la identificación es opcional.
                    if (empty($v['nombre_externo'])) {
                        $validator->errors()->add("viajeros.$i.nombre_externo", 'Ingrese el nombre del viajero externo.');
                    }
                } elseif (empty($v['empleado_id'])) {
                    $validator->errors()->add("viajeros.$i.empleado_id", 'Seleccione el empleado o marque viajero externo.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'municipios.required'             => 'Seleccione al menos un municipio.',
            'municipios.min'                  => 'Seleccione al menos un municipio.',
            'municipios.*.exists'             => 'Uno de los municipios seleccionados no es válido.',
            'viajeros.required'               => 'Debe agregar al menos un viajero.',
            'viajeros.min'                    => 'Debe agregar al menos un viajero.',
            'viajeros.*.empleado_id.required' => 'Seleccione el empleado.',
            'viajeros.*.empleado_id.exists'   => 'El empleado seleccionado no es válido.',
            'viajeros.*.motivo.required'      => 'El motivo es obligatorio para cada viajero.',
            'viajeros.*.fecha_salida.required'=> 'La fecha de salida es obligatoria.',
            'viajeros.*.fecha_regreso.required'=> 'La fecha de regreso es obligatoria.',
            'viajeros.*.hora_salida.required' => 'La hora de salida es obligatoria.',
            'viajeros.*.hora_regreso.required'=> 'La hora de regreso es obligatoria.',
        ];
    }
}
