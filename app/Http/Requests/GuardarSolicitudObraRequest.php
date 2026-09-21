<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarSolicitudObraRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre_solicitante' => 'required|string|max:255',
            'fecha_solicitud'    => 'required|date',
            'fecha_entrega'      => 'nullable|date',
            'observacion'        => 'nullable|string',
            'items'                 => 'array',
            'items.*.especificacion'=> 'required_with:items|string|max:255',
            'items.*.unidad'        => 'nullable|string|max:50',
            'items.*.cantidad'      => 'required_with:items|numeric|min:0',
            'items.*.sede'          => 'nullable|string|max:255',
            'cotizacion'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls|max:5120',
            'enviar'             => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $items = $this->input('items', []);
            if (empty($items) && ! $this->hasFile('cotizacion')) {
                $v->errors()->add('items', 'Agregue al menos un elemento o adjunte una cotización.');
            }
        }];
    }
}
