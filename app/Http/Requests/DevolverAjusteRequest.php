<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DevolverAjusteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['motivo_devolucion' => ['required', 'string', 'max:2000']];
    }
}
