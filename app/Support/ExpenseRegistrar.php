<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registrar y anular gastos. Lo usa la web (y lo usará la API del móvil): las
 * reglas y el movimiento del dinero viven aquí, no en los controladores.
 */
class ExpenseRegistrar
{
    public function __construct(protected PaymentSource $payments)
    {
    }

    public function register(Request $request, Company $company): Expense
    {
        $data = $request->validate([
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('company_id', $company->id)->whereNull('deleted_at')],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $company->id)],
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $company->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], [
            'expense_category_id.required' => 'Elige en qué se gastó.',
            'description.required' => 'Describe el gasto: sin él, después no se entiende.',
            'amount.min' => 'El importe tiene que ser mayor que cero.',
            'spent_on.before_or_equal' => 'Un gasto no puede tener fecha futura.',
        ]);

        $source = $this->payments->validate($request, $company);
        $category = ExpenseCategory::withoutGlobalScopes()->findOrFail($data['expense_category_id']);

        // Sin sucursal indicada: la de la caja de la que sale, la de la cuenta,
        // o la del usuario.
        $branchId = $data['branch_id']
            ?? $source[2]?->branch_id
            ?? $source[1]?->branch_id
            ?? Branch::preferredIdFor($request->user(), $company->id);

        return $this->record($company, $category, $source, [
            'branch_id' => $branchId,
            // Solo se guarda el destinatario que corresponde a la categoría.
            'supplier_id' => $category->payee === 'proveedor' ? ($data['supplier_id'] ?? null) : null,
            'personal_id' => $category->payee === 'personal' ? ($data['personal_id'] ?? null) : null,
            'description' => trim($data['description']),
            'amount' => round((float) $data['amount'], 2),
            'spent_on' => $data['spent_on'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $request->user()->id);
    }

    /**
     * Crea el gasto y saca el dinero de su origen, en una transacción. Lo usa
     * el alta normal y también el sistema (el pago de una liquidación).
     *
     * @param  array{0: string, 1: mixed, 2: mixed}  $source  de PaymentSource::validate()
     */
    public function record(Company $company, ExpenseCategory $category, array $source, array $attributes, int $userId): Expense
    {
        return DB::transaction(function () use ($company, $category, $source, $attributes, $userId) {
            $expense = Expense::withoutGlobalScopes()->create($attributes + [
                'company_id' => $company->id,
                'branch_id' => $source[2]?->branch_id ?? $source[1]?->branch_id,
                'expense_category_id' => $category->id,
                'source' => $source[0],
                'status' => 'registrado',
                'created_by' => $userId,
            ]);

            $expense->update($this->payments->charge(
                $source,
                (float) $expense->amount,
                "Gasto {$expense->number}: {$category->name} · {$expense->description}",
                'gasto',
                $expense->reference,
                $expense->spent_on->toDateString(),
            ));

            return $expense;
        });
    }

    /** Anular: el dinero vuelve a la cuenta o a la caja. El gasto no se borra. */
    public function cancel(Expense $expense, ?string $reason, int $userId): void
    {
        if ($expense->isCancelled()) {
            throw ValidationException::withMessages(['error' => 'Este gasto ya está anulado.']);
        }

        // Es el pago de una liquidación: sus comisiones ya quedaron pagadas.
        if ($expense->commission_settlement_id) {
            throw ValidationException::withMessages([
                'error' => 'Este gasto es el pago de una liquidación de comisiones: no se anula por separado.',
            ]);
        }

        DB::transaction(function () use ($expense, $reason, $userId) {
            $this->payments->refund($expense, "Anulación gasto {$expense->number}");

            $expense->update([
                'status' => 'anulado',
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
            ]);
        });
    }
}
