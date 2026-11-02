<?php

namespace App\Support;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de un método de pago, compartidas por la web y la API.
 */
class PaymentMethodForm
{
    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?PaymentMethod $method = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'counts_as_cash' => ['nullable', 'boolean'],
            'requires_reference' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Ponle un nombre al método de pago.',
        ]);

        $countsAsCash = $request->boolean('counts_as_cash');

        // Dejar la barbería sin ningún método en efectivo rompería el arqueo.
        if ($method && $method->counts_as_cash && ! $countsAsCash
            && PaymentMethod::cash()->count() === 1) {
            throw ValidationException::withMessages([
                'counts_as_cash' => 'Es el único método que cuenta como efectivo. '
                    . 'Marca otro antes de quitarle esto.',
            ]);
        }

        return [
            'name' => trim($data['name']),
            'counts_as_cash' => $countsAsCash,
            'requires_reference' => $request->boolean('requires_reference'),
            'active' => $request->boolean('active', true),
        ];
    }
}
