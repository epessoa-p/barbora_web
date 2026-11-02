<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FinanceAccount;
use App\Support\Treasury;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Las cuentas de la barbería: capital, caja chica, banco… El dinero que no
 * está en el cajón. El saldo se mueve solo con movimientos (Treasury); aquí se
 * administran los datos de la cuenta.
 */
class AccountController extends Controller
{
    public function index()
    {
        $accounts = FinanceAccount::with('branch')->orderByDesc('active')->orderBy('name')->get();

        return view('finance.accounts.index', [
            'accounts' => $accounts,
            'total' => (float) $accounts->where('active', true)->sum('balance'),
        ]);
    }

    public function create()
    {
        return view('finance.accounts.create', ['account' => null, 'branches' => $this->branches()]);
    }

    public function store(Request $request, Treasury $treasury)
    {
        $companyId = $this->targetCompanyId();
        $data = $this->validated($request, $companyId);
        $opening = round((float) ($request->input('opening_balance') ?: 0), 2);

        $account = DB::transaction(function () use ($data, $companyId, $opening, $treasury) {
            $account = FinanceAccount::create($data + ['company_id' => $companyId, 'opening_balance' => $opening, 'balance' => 0, 'active' => true]);

            // El saldo inicial entra como movimiento: así el historial explica el saldo.
            if ($opening > 0) {
                $treasury->move($account, 'ingreso', $opening, 'Saldo inicial', ['origin' => 'apertura']);
            }

            return $account;
        });

        return redirect()->route('accounts.show', $account)->with('success', "Cuenta «{$account->name}» creada.");
    }

    public function show(Request $request, FinanceAccount $account)
    {
        $movements = $account->movements()->with('creator')
            ->latest('occurred_on')->latest('id')
            ->paginate(25)->withQueryString();

        return view('finance.accounts.show', [
            'account' => $account->load('branch'),
            'movements' => $movements,
            'otherAccounts' => FinanceAccount::active()->whereKeyNot($account->id)->orderBy('name')->get(),
        ]);
    }

    public function edit(FinanceAccount $account)
    {
        return view('finance.accounts.edit', ['account' => $account, 'branches' => $this->branches()]);
    }

    public function update(Request $request, FinanceAccount $account)
    {
        $account->update($this->validated($request, $account->company_id) + ['active' => $request->boolean('active')]);

        return redirect()->route('accounts.show', $account)->with('success', 'Cuenta actualizada.');
    }

    /**
     * Dar de baja. Con movimientos no se borra (el historial apunta a ella):
     * se desactiva. Con saldo, ni eso: primero hay que vaciarla.
     */
    public function destroy(FinanceAccount $account)
    {
        if (abs((float) $account->balance) >= 0.01) {
            return back()->withErrors(['error' => 'La cuenta tiene saldo. Transfiérelo a otra cuenta antes de darla de baja.']);
        }

        if ($account->movements()->exists()) {
            $account->update(['active' => false]);

            return redirect()->route('accounts.index')->with('success', "«{$account->name}» se dio de baja. Su historial se conserva.");
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', "Cuenta «{$account->name}» eliminada.");
    }

    protected function validated(Request $request, ?int $companyId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(FinanceAccount::TYPES))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Ponle un nombre a la cuenta.',
        ]);

        unset($data['opening_balance']);
        $data['name'] = trim($data['name']);

        return $data;
    }

    protected function branches()
    {
        return Branch::where('active', true)->orderBy('name')->get();
    }
}
