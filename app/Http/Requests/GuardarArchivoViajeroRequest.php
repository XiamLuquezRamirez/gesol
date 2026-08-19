<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarArchivoViajeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorizacion real la hace la policy editarLiquidacion en el controlador.
    }

    public function rules(): array
    {
        return [
            'tipo'       => 'required|in:comprobante,soporte',
            'archivos'   => 'required|array|min:1',
            'archivos.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'       => 'Falta el tipo de archivo.',
            'tipo.in'             => 'Tipo de archivo no válido.',
            'archivos.required'   => 'Adjunte al menos un archivo.',
            'archivos.*.mimes'    => 'Solo se permiten PDF o imágenes (jpg, png).',
            'archivos.*.max'      => 'Cada archivo no puede superar 5 MB.',
        ];
    }
}
