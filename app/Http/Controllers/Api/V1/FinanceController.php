<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceAccount;
use App\Models\FinanceMovement;
use App\Models\Personal;
use App\Models\Supplier;
use App\Support\BasicServicesMonth;
use App\Support\ExpenseRegistrar;
use App\Support\PaymentSource;
use App\Support\ShopTime;
use App\Support\Treasury;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finanzas desde el móvil: cuentas, sus movimientos, gastos y categorías.
 * Mismas reglas que la web (Treasury, PaymentSource, ExpenseRegistrar) y
 * mismos permisos, puestos en las rutas.
 */
class FinanceController extends ApiController
{
    public function __construct(
        protected Treasury $treasury,
        protected ExpenseRegistrar $expenses,
    ) {
    }

    /* ── Opciones para los formularios ───────────────────────────────────── */

    /** Lo que necesita el alta de un gasto: categorías, a quién, y de dónde sale el dinero. */
    public function options(Request $request): JsonResponse
    {
        $company = $this->company($request);
        ExpenseCategory::ensureDefaults($company->id);

        $group = $request->query('group');

        return $this->json([
            // Desde una pestaña de Gastos (general, proveedor, personal, básicos), solo las de ese grupo.
            'categories' => ExpenseCategory::active()->inGroup($group)->ordered()->get()
                ->map($this->categoryPayload(...))->all(),
            // A proveedor o a personal no se elige categoría: va esta.
            'default_category_id' => in_array($group, ['proveedor', 'personal'], true)
                ? ExpenseCategory::defaultFor($company->id, $group)->id
                : null,
            'suppliers' => Supplier::active()->orderBy('name')->get()->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'staff' => Personal::where('active', true)->orderBy('full_name')->get()->map(fn (Personal $p) => ['id' => $p->id, 'name' => $p->full_name])->all(),
            ...self::sourcePayload($company),
        ]);
    }

    /**
     * De dónde puede salir el dinero: cuentas activas y turnos abiertos. Lo
     * comparten gastos y compras.
     */
    public static function sourcePayload(Company $company): array
    {
        $sources = PaymentSource::available($company);
        $preferred = Branch::preferredIdFor(auth()->user(), $company->id);
        $sessions = in_array('cash', $sources, true) ? PaymentSource::openSessions($company->id, auth()->user()) : collect();

        return [
            'sources' => $sources,
            'accounts' => in_array('account', $sources, true)
                ? FinanceAccount::active()->orderBy('name')->get()->map(fn (FinanceAccount $a) => self::accountPayload($a))->all()
                : [],
            'open_sessions' => $sessions->map(fn (CashSession $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'caja' => $s->caja?->name,
                'branch_id' => $s->branch_id,
                'expected_cash' => round($s->expectedCash(), 2),
            ])->values()->all(),
            // El turno que se propone: el de la sucursal del usuario.
            'preferred_session_id' => ($sessions->firstWhere('branch_id', $preferred) ?? $sessions->first())?->id,
        ];
    }

    /* ── Cuentas ─────────────────────────────────────────────────────────── */

    public function accounts(): JsonResponse
    {
        $accounts = FinanceAccount::with('branch')->orderByDesc('active')->orderBy('name')->get();

        return $this->json([
            'data' => $accounts->map(fn (FinanceAccount $a) => self::accountPayload($a))->all(),
            'total' => round((float) $accounts->where('active', true)->sum('balance'), 2),
            'types' => FinanceAccount::TYPES,
        ]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $data = $this->validateAccount($request, $company->id);
        $opening = round((float) ($request->input('opening_balance') ?: 0), 2);

        $account = DB::transaction(function () use ($data, $company, $opening) {
            $account = FinanceAccount::create($data + [
                'company_id' => $company->id, 'opening_balance' => $opening, 'balance' => 0, 'active' => true,
            ]);

            if ($opening > 0) {
                $this->treasury->move($account, 'ingreso', $opening, 'Saldo inicial', ['origin' => 'apertura']);
            }

            return $account;
        });

        return $this->json(['message' => "Cuenta «{$account->name}» creada.", 'data' => self::accountPayload($account->refresh())], 201);
    }

    public function updateAccount(Request $request, FinanceAccount $account): JsonResponse
    {
        $account->update($this->validateAccount($request, $account->company_id)
            + ['active' => $request->boolean('active', (bool) $account->active)]);

        return $this->json(['message' => 'Cuenta actualizada.', 'data' => self::accountPayload($account)]);
    }

    public function movements(Request $request, FinanceAccount $account): JsonResponse
    {
        $company = $this->company($request);
        $page = $account->movements()->with('creator')->latest('occurred_on')->latest('id')->paginate(30);

        return $this->json([
            'account' => self::accountPayload($account),
            'data' => collect($page->items())->map(fn (FinanceMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'origin' => $m->origin,
                'origin_label' => $m->originLabel(),
                'concept' => $m->concept,
                'amount' => (float) $m->amount,
                'balance_after' => (float) $m->balance_after,
                'occurred_on' => $m->occurred_on->toDateString(),
                'reference' => $m->reference,
                'created_by' => $m->creator?->name,
                'cancelled' => $m->isCancelled(),
                // Solo lo manual se anula desde la cuenta.
                'can_cancel' => $m->isManual() && ! $m->isCancelled(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'other_accounts' => FinanceAccount::active()->whereKeyNot($account->id)->orderBy('name')->get()
                ->map(fn (FinanceAccount $a) => ['id' => $a->id, 'name' => $a->name])->all(),
            'currency' => $company->currency,
        ]);
    }

    public function storeMovement(Request $request, FinanceAccount $account): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FinanceMovement::TYPES))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['required', 'string', 'max:255'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ], [
            'concept.required' => 'Escribe un concepto: sin él, el saldo no se puede explicar después.',
        ]);

        $this->treasury->move($account, $data['type'], (float) $data['amount'], trim($data['concept']), [
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
            'reference' => $data['reference'] ?? null,
        ]);

        return $this->json([
            'message' => $data['type'] === 'ingreso' ? 'Ingreso registrado.' : 'Egreso registrado.',
            'data' => self::accountPayload($account->refresh()),
        ], 201);
    }

    public function transfer(Request $request, FinanceAccount $account): JsonResponse
    {
        $data = $request->validate([
            'to_account_id' => [
                'required',
                Rule::exists('finance_accounts', 'id')->where('company_id', $account->company_id)->where('active', true)->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['nullable', 'string', 'max:255'],
        ], [
            'to_account_id.required' => 'Elige a qué cuenta pasa el dinero.',
        ]);

        $to = FinanceAccount::findOrFail($data['to_account_id']);
        $this->treasury->transfer($account, $to, (float) $data['amount'], $data['concept'] ?? null);

        return $this->json([
            'message' => "Transferencia a «{$to->name}» registrada.",
            'data' => self::accountPayload($account->refresh()),
        ], 201);
    }

    public function cancelMovement(FinanceMovement $movement): JsonResponse
    {
        if (! $movement->isManual()) {
            throw ValidationException::withMessages(['error' => 'Este movimiento viene de un gasto o una compra: anúlalo desde allí.']);
        }

        $this->treasury->cancel($movement);

        return $this->json(['message' => 'Movimiento anulado: el saldo se corrigió.']);
    }

    /* ── Gastos ──────────────────────────────────────────────────────────── */

    public function expenses(Request $request): JsonResponse
    {
        $query = Expense::with(['category', 'supplier', 'personal', 'account', 'cashSession'])
            ->latest('spent_on')->latest('id');

        if ($from = $request->query('from')) {
            $query->whereDate('spent_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('spent_on', '<=', $to);
        }
        // Las pestañas de la app: a proveedores, a personal, servicios básicos.
        if (in_array($group = $request->query('group'), ExpenseCategory::GROUPS, true)) {
            $query->whereHas('category', fn ($q) => $q->inGroup($group));
        }
        if ($category = $request->integer('category')) {
            $query->where('expense_category_id', $category);
        }

        $total = (float) (clone $query)->reorder()->active()->sum('amount');
        $page = $query->paginate(30);

        return $this->json([
            'data' => collect($page->items())->map($this->expensePayload(...))->all(),
            'total' => round($total, 2),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Control de los servicios básicos de un mes (?month=YYYY-MM): pagados y pendientes. */
    public function basics(Request $request, BasicServicesMonth $basics): JsonResponse
    {
        ExpenseCategory::ensureDefaults($this->company($request)->id);

        return $this->json($basics->for(BasicServicesMonth::parse($request->query('month'))));
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $expense = $this->expenses->register($request, $this->company($request));

        return $this->json([
            'message' => "Gasto {$expense->number} registrado.",
            'data' => $this->expensePayload($expense->load(['category', 'supplier', 'personal', 'account', 'cashSession'])),
        ], 201);
    }

    public function cancelExpense(Request $request, Expense $expense): JsonResponse
    {
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $this->expenses->cancel($expense, $data['cancel_reason'] ?? null, $request->user()->id);

        return $this->json([
            'message' => "Gasto {$expense->number} anulado: el dinero volvió a ".($expense->source === 'cash' ? 'la caja.' : 'la cuenta.'),
            'data' => $this->expensePayload($expense->refresh()->load(['category', 'supplier', 'personal', 'account', 'cashSession'])),
        ]);
    }

    /* ── Categorías de gasto ─────────────────────────────────────────────── */

    public function categories(Request $request): JsonResponse
    {
        ExpenseCategory::ensureDefaults($this->company($request)->id);

        return $this->json([
            'data' => ExpenseCategory::withCount('expenses')->ordered()->get()->map($this->categoryPayload(...))->all(),
            'payees' => ExpenseCategory::PAYEES,
            'types' => ExpenseCategory::TYPES,
            'frequencies' => ExpenseCategory::FREQUENCIES,
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $companyId = $this->company($request)->id;
        $data = $this->validateCategory($request, $companyId);

        $category = ExpenseCategory::create($data + [
            'company_id' => $companyId, 'active' => true,
            'sort_order' => (int) ExpenseCategory::max('sort_order') + 1,
        ]);

        return $this->json(['message' => "Categoría «{$category->name}» creada.", 'data' => $this->categoryPayload($category)], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->update($this->validateCategory($request, $expenseCategory->company_id, $expenseCategory)
            + ['active' => $request->boolean('active', (bool) $expenseCategory->active)]);

        return $this->json(['message' => 'Categoría actualizada.', 'data' => $this->categoryPayload($expenseCategory)]);
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    protected function company(Request $request): Company
    {
        return $request->attributes->get('tenant_company') ?? abort(404);
    }

    protected function validateAccount(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(FinanceAccount::TYPES))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Ponle un nombre a la cuenta.',
        ]);

        unset($data['opening_balance'], $data['active']);
        $data['name'] = trim($data['name']);

        return $data;
    }

    protected function validateCategory(Request $request, int $companyId, ?ExpenseCategory $category = null): array
    {
        return ExpenseCategory::validateForm($request, $companyId, $category);
    }

    public static function accountPayload(FinanceAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'type_label' => $account->typeLabel(),
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'branch_id' => $account->branch_id,
            'branch' => $account->branch?->name,
            'notes' => $account->notes,
            'balance' => (float) $account->balance,
            'active' => (bool) $account->active,
        ];
    }

    protected function categoryPayload(ExpenseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'payee' => $category->payee,
            'recurring' => (bool) $category->recurring,
            // El tipo (general, basicos, proveedor, personal) y, si es servicio básico, cada cuánto.
            'type' => $category->group(),
            'frequency' => $category->frequency ?: 'mensual',
            'active' => (bool) $category->active,
            'expenses_count' => (int) ($category->expenses_count ?? 0),
        ];
    }

    protected function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'number' => $expense->number,
            'description' => $expense->description,
            'category' => $expense->category?->name,
            'payee' => $expense->payeeName(),
            'amount' => (float) $expense->amount,
            'spent_on' => $expense->spent_on->toDateString(),
            'source' => $expense->source,
            'source_label' => $expense->sourceLabel(),
            'reference' => $expense->reference,
            'notes' => $expense->notes,
            'status' => $expense->status,
            'cancelled' => $expense->isCancelled(),
            'cancel_reason' => $expense->cancel_reason,
            // El pago de una liquidación no se anula suelto.
            'can_cancel' => ! $expense->isCancelled() && ! $expense->commission_settlement_id,
        ];
    }
}
