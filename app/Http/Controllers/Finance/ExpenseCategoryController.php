<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** En qué se gasta. Se administran en una sola pantalla, con edición en línea. */
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        ExpenseCategory::ensureDefaults($this->targetCompanyId());

        return view('finance.expense-categories.index', [
            'categories' => ExpenseCategory::withCount('expenses')->ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();
        $data = $this->validated($request, $companyId);

        ExpenseCategory::create($data + [
            'company_id' => $companyId,
            'active' => true,
            'sort_order' => (int) ExpenseCategory::max('sort_order') + 1,
        ]);

        return back()->with('success', "Categoría «{$data['name']}» creada.");
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($this->validated($request, $expenseCategory->company_id, $expenseCategory)
            + ['active' => $request->boolean('active')]);

        return back()->with('success', "Categoría «{$expenseCategory->name}» actualizada.");
    }

    /** Con gastos no se borra: se da de baja, para que el historial siga leyendo. */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            $expenseCategory->update(['active' => false]);

            return back()->with('success', "«{$expenseCategory->name}» se dio de baja: tiene gastos registrados.");
        }

        $expenseCategory->delete();

        return back()->with('success', "Categoría «{$expenseCategory->name}» eliminada.");
    }

    protected function validated(Request $request, ?int $companyId, ?ExpenseCategory $category = null): array
    {
        // Tipo (general, servicio básico, a proveedor, a personal) y, si es
        // servicio básico, mensual o anual. Las mismas reglas que la API.
        return ExpenseCategory::validateForm($request, $companyId, $category);
    }
}
