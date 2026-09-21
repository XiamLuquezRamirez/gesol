<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoObraRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'monto'      => 'required|numeric|min:0.01',
            'fecha_pago' => 'required|date',
            'soporte'    => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion'=> 'nullable|string',
        ];
    }
}
