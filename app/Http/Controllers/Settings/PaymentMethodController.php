<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Métodos de pago de la barbería.
 *
 * La decisión de fondo está en «cuenta como efectivo»: solo lo marcado entra en
 * el arqueo, porque es lo único que de verdad está en el cajón. Un cobro por
 * tarjeta o por billetera está en el banco, y meterlo ahí haría que el turno
 * cuadre mal todos los días.
 */
class PaymentMethodController extends Controller
{
    public function index()
    {
        return view('settings.payment-methods.index', [
            'methods' => PaymentMethod::ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('settings.payment-methods.create', ['method' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        PaymentMethod::create($data + [
            'company_id' => $this->targetCompanyId(),
            'slug' => PaymentMethod::generateSlug($data['name'], $this->targetCompanyId()),
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
        ]);

        return redirect()->route('payment-methods.index')
            ->with('success', "Método de pago «{$data['name']}» creado.");
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('settings.payment-methods.edit', ['method' => $paymentMethod]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $this->validated($request, $paymentMethod);

        // El slug NO se toca al renombrar: es lo que guardan las ventas y los
        // movimientos de caja, y cambiarlo desharía el historial.
        $paymentMethod->update($data);

        return redirect()->route('payment-methods.index')
            ->with('success', "Método de pago «{$paymentMethod->name}» actualizado.");
    }

    /**
     * Dar de baja. Nunca se borra de verdad si se usó alguna vez: el historial
     * de ventas y arqueos seguiría apuntando a ese método.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->counts_as_cash && PaymentMethod::cash()->count() === 1) {
            return back()->withErrors([
                'error' => 'Tiene que quedar al menos un método que cuente como '
                         . 'efectivo: sin él, el arqueo de caja no tendría contra qué comparar.',
            ]);
        }

        if ($paymentMethod->isInUse()) {
            $paymentMethod->update(['active' => false]);

            return redirect()->route('payment-methods.index')->with(
                'success',
                "«{$paymentMethod->name}» se dio de baja. No se elimina porque hay "
                . 'ventas y movimientos que lo usaron.',
            );
        }

        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')
            ->with('success', "Método de pago «{$paymentMethod->name}» eliminado.");
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    protected function validated(Request $request, ?PaymentMethod $method = null): array
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
