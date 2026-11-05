<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceAccount;
use App\Models\Personal;
use App\Models\Supplier;
use App\Support\BasicServicesMonth;
use App\Support\ExpenseRegistrar;
use App\Support\Money;
use App\Support\PaymentSource;
use Illuminate\Http\Request;

/**
 * Gastos: pagos a proveedores y a personal, servicios (alquiler, luz, agua,
 * internet…), comida, pasajes, pedidos… Salen de una cuenta o de la caja
 * abierta. Nunca se borran: se anulan y el dinero vuelve a donde salió.
 */
class ExpenseController extends Controller
{
    public function __construct(protected ExpenseRegistrar $registrar)
    {
    }

    public function index(Request $request)
    {
        ExpenseCategory::ensureDefaults($this->targetCompanyId());

        $query = Expense::with(['category', 'supplier', 'personal', 'account', 'cashSession', 'branch'])
            ->latest('spent_on')->latest('id');

        if ($from = $request->query('from')) {
            $query->whereDate('spent_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('spent_on', '<=', $to);
        }
        // Pestañas: generales (por defecto), a proveedores, a personal,
        // servicios básicos, o todos juntos.
        $group = $request->query('group', 'general');
        $group = in_array($group, [...ExpenseCategory::GROUPS, 'todos'], true) ? $group : 'general';
        if ($group !== 'todos') {
            $query->whereHas('category', fn ($q) => $q->withTrashed()->inGroup($group));
        }

        if ($category = $request->integer('category')) {
            $query->where('expense_category_id', $category);
        }
        if (in_array($source = $request->query('source'), ['account', 'cash'], true)) {
            $query->where('source', $source);
        }

        $total = (clone $query)->reorder()->active()->sum('amount');

        return view('finance.expenses.index', [
            'group' => $group,
            'expenses' => $query->paginate(25)->withQueryString(),
            'categories' => ExpenseCategory::inGroup($group === 'todos' ? null : $group)->ordered()->get(),
            'total' => (float) $total,
            // El control del mes, solo en la pestaña de servicios básicos.
            // En Personal: las comisiones sin pagar, de cualquier fecha.
            'owed' => $group === 'personal' && $this->canSeeCommissions($request)
                ? \App\Support\PendingCommissions::byPersonal()
                : null,
            'basics' => $group === 'basicos'
                ? app(BasicServicesMonth::class)->for(BasicServicesMonth::parse($request->query('month')))
                : null,
        ]);
    }

    /** Las comisiones se muestran en Gastos › Personal si el plan y el permiso lo dejan. */
    protected function canSeeCommissions(Request $request): bool
    {
        $company = $request->attributes->get('tenant_company');

        return (bool) $company?->planAllows('comisiones')
            && auth()->user()->hasPermissionInCompany('commissions.view', $company);
    }

    public function create(Request $request)
    {
        $companyId = $this->targetCompanyId();
        ExpenseCategory::ensureDefaults($companyId);
        $company = $request->attributes->get('tenant_company');

        // El formulario cambia según desde qué pestaña se llega.
        $group = old('group', $request->query('group', 'general'));
        $group = in_array($group, ExpenseCategory::GROUPS, true) ? $group : 'general';

        return view('finance.expenses.create', [
            'group' => $group,
            'categories' => ExpenseCategory::active()->inGroup($group)->ordered()->get(),
            // Desde el control del mes: el servicio ya elegido y el último importe.
            'selectedCategory' => $request->integer('category') ?: null,
            'suggestedAmount' => $request->query('amount'),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'staff' => Personal::where('active', true)->orderBy('full_name')->get(),
            'branches' => Branch::workableFor(auth()->user(), $company->id),
            ...$this->sourceOptions($company),
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->attributes->get('tenant_company');
        $expense = $this->registrar->register($request, $company);

        // De vuelta a la pestaña del gasto.
        return redirect()->route('expenses.index', ['group' => $expense->category?->group() ?? 'general'])
            ->with('success', "Gasto {$expense->number} de ".Money::format($expense->amount, $company).' registrado.');
    }

    public function show(Expense $expense)
    {
        return view('finance.expenses.show', [
            'expense' => $expense->load(['category', 'supplier', 'personal', 'account', 'cashSession.caja', 'branch', 'creator']),
        ]);
    }

    public function cancel(Request $request, Expense $expense)
    {
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $this->registrar->cancel($expense, $data['cancel_reason'] ?? null, $request->user()->id);

        return redirect()->route('expenses.show', $expense)
            ->with('success', "Gasto {$expense->number} anulado: el dinero volvió a ".($expense->source === 'cash' ? 'la caja.' : 'la cuenta.'));
    }

    /**
     * Las opciones del «de dónde sale el dinero», también para Compras: las
     * cuentas activas y los turnos de caja abiertos (el de la sucursal del
     * usuario primero).
     */
    public static function sourceOptions($company): array
    {
        $sources = PaymentSource::available($company);

        return [
            'sources' => $sources,
            'accounts' => in_array('account', $sources, true) ? FinanceAccount::active()->orderBy('name')->get() : collect(),
            'openSessions' => in_array('cash', $sources, true) ? PaymentSource::openSessions($company->id, auth()->user()) : collect(),
            'preferredBranchId' => Branch::preferredIdFor(auth()->user(), $company->id),
        ];
    }
}
