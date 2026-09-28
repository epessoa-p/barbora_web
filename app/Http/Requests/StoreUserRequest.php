<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('users.create', auth()->user()->getCurrentCompany());
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^\S+$/',
                Rule::unique('users', 'name')->ignore($userId),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'phone' => 'nullable|string|max:20',
            'is_super_admin' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.regex' => 'El nombre de usuario no puede contener espacios',
            'email.required' => 'El email es requerido',
            'email.unique' => 'Este email ya está registrado',
            'name.unique' => 'Este nombre de usuario ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ];
    }
}
