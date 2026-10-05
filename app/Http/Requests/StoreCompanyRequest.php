<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->is_super_admin;
    }

    public function rules(): array
    {
        // El mismo request sirve para crear y editar: al editar hay que
        // ignorar la propia empresa en la regla de unicidad.
        $companyId = $this->route('company')?->id;

        return [
            'name' => 'required|string|max:255',
            'tax_id' => ['nullable', 'string', 'max:32', Rule::unique('companies', 'tax_id')->ignore($companyId)],
            'tax_id_label' => 'nullable|string|max:20',
            'country' => ['required', Rule::in(array_keys(config('barbora.countries')))],
            'currency' => ['required', Rule::in(array_keys(config('barbora.currencies')))],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
            // El logo lo guarda el controlador (no se mass-assignea el archivo).
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es requerido',
            'tax_id.unique' => 'Este identificador fiscal ya está registrado',
            'country.required' => 'Selecciona el país de la empresa',
            'country.in' => 'El país seleccionado no está disponible',
            'currency.required' => 'Selecciona la moneda de la empresa',
            'currency.in' => 'La moneda seleccionada no está disponible',
            'timezone.required' => 'Selecciona la zona horaria de la empresa',
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
            'logo.mimes' => 'El logo tiene que ser PNG, JPG o WEBP.',
        ];
    }
}
