<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\Empleados;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudOficinaRequest extends FormRequest
{
    /** Memoiza el resultado para consultar el area una sola vez por request. */
    private ?bool $esGeneral = null;

    public function authorize(): bool
    {
        return true;
    }

    /** El area elegida es la institucional (General). */
    private function areaEsGeneral(): bool
    {
        if ($this->esGeneral === null) {
            $area = Area::find($this->input('area_id'));
            $this->esGeneral = (bool) ($area?->es_general);
        }
        return $this->esGeneral;
    }

    public function rules(): array
    {
        $reglas = [
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

        if ($this->areaEsGeneral()) {
            // Institucional: sin beneficiarios (se ignora lo que venga).
            $reglas['beneficiarios']   = 'nullable|array';
        } else {
            // Area normal: al menos un beneficiario, todos del area elegida.
            $reglas['beneficiarios']   = 'required|array|min:1';
            $reglas['beneficiarios.*'] = 'exists:empleados,id';
        }

        return $reglas;
    }

    /**
     * Regla estricta: en un area normal, cada beneficiario debe pertenecer al
     * area elegida. Un empleado sin area, o de otra area, se rechaza.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->areaEsGeneral()) {
                return;
            }
            $ids = (array) $this->input('beneficiarios', []);
            if (empty($ids)) {
                return; // ya lo cubre required|min:1
            }
            $ajenos = Empleados::whereIn('id', $ids)
                ->where(fn ($q) => $q->where('area_id', '!=', $this->input('area_id'))->orWhereNull('area_id'))
                ->exists();
            if ($ajenos) {
                $validator->errors()->add('beneficiarios', 'Todos los beneficiarios deben pertenecer al departamento seleccionado.');
            }
        });
    }

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
