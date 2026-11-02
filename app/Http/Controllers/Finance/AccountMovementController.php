<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\FinanceMovement;
use App\Support\Money;
use App\Support\Treasury;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Movimientos manuales de una cuenta: un ingreso (aporte de capital, un
 * depósito), un egreso (un retiro) o una transferencia a otra cuenta. Los
 * gastos y las compras mueven las cuentas desde su propia pantalla.
 */
class AccountMovementController extends Controller
{
    public function __construct(protected Treasury $treasury)
    {
    }

    public function store(Request $request, FinanceAccount $account)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FinanceMovement::TYPES))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['required', 'string', 'max:255'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ], [
            'concept.required' => 'Escribe un concepto: sin él, el saldo no se puede explicar después.',
            'amount.min' => 'El importe tiene que ser mayor que cero.',
        ]);

        $movement = $this->treasury->move($account, $data['type'], (float) $data['amount'], trim($data['concept']), [
            'occurred_on' => $data['occurred_on'],
            'reference' => $data['reference'] ?? null,
        ]);

        return back()->with('success', $movement->isIncome()
            ? 'Ingreso de '.Money::format($movement->amount, $request->attributes->get('tenant_company')).' registrado.'
            : 'Egreso de '.Money::format($movement->amount, $request->attributes->get('tenant_company')).' registrado.');
    }

    public function transfer(Request $request, FinanceAccount $account)
    {
        $data = $request->validate([
            'to_account_id' => [
                'required',
                Rule::exists('finance_accounts', 'id')->where('company_id', $account->company_id)->where('active', true)->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['nullable', 'string', 'max:255'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'to_account_id.required' => 'Elige a qué cuenta pasa el dinero.',
        ]);

        $to = FinanceAccount::findOrFail($data['to_account_id']);
        $this->treasury->transfer($account, $to, (float) $data['amount'], $data['concept'] ?? null, $data['occurred_on']);

        return back()->with('success', 'Transferencia a «'.$to->name.'» registrada.');
    }

    /** Solo lo manual: un gasto o una compra se anulan desde su ficha. */
    public function cancel(Request $request, FinanceMovement $movement)
    {
        if (! $movement->isManual()) {
            return back()->withErrors(['error' => 'Este movimiento viene de un gasto o una compra: anúlalo desde allí.']);
        }

        $this->treasury->cancel($movement, $request->input('reason'));

        return back()->with('success', 'Movimiento anulado: el saldo se corrigió.');
    }
}
