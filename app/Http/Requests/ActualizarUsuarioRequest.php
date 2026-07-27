<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La proteccion de acceso vive en el middleware role:admin de la ruta.
        return true;
    }

    public function rules(): array
    {
        $usuarioId = $this->route('usuario')->id;

        return [
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($usuarioId)],
            'password' => 'nullable|string|min:8|confirmed',
            'roles'    => 'required|array|min:1',
            'roles.*'  => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Debes asignar al menos un rol.',
            'roles.*.exists' => 'Uno de los roles seleccionados no existe.',
        ];
    }
}
