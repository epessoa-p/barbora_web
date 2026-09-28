<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashMovementController extends Controller
{
    /**
     * Movimientos del periodo, con filtros. Es la misma pantalla que sirve para
     * «Ingresos» y «Gastos»: cambia solo el filtro de tipo.
     */
    public function index(Request $request)
    {
        $query = CashMovement::with(['session.caja', 'creator'])->latest('id');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($method = $request->query('method')) {
            $query->where('payment_method', $method);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $movements = $query->paginate(25)->withQueryString();

        // Los totales se calculan sobre todo el filtro, no solo sobre la página.
        $totals = (clone $query)->reorder()->get();

        return view('cash.movements.index', [
            'movements' => $movements,
            'income' => (float) $totals->where('type', 'ingreso')->sum('amount'),
            'expense' => (float) $totals->where('type', 'egreso')->sum('amount'),
        ]);
    }

    public function store(Request $request, CashSession $session)
    {
        if (! $session->isOpen()) {
            return back()->withErrors([
                'error' => 'No se pueden registrar movimientos en un turno cerrado.',
            ]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CashMovement::TYPES))],
            'payment_method' => ['required', Rule::in(array_keys(CashMovement::paymentMethods()))],
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'concept.required' => 'Escribe un concepto: sin él, el arqueo no se puede explicar.',
            'amount.min' => 'El importe tiene que ser mayor que cero.',
        ]);

        $movement = $session->movements()->create($data + [
            'company_id' => $session->company_id,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success',
            $movement->typeLabel().' de '
            . \App\Support\Money::format($movement->amount, $session->caja?->company)
            . ' registrado.');
    }

    /**
     * Anular un movimiento. Se borra en blando: el turno tiene que poder
     * explicarse después, y un movimiento que desaparece sin rastro es
     * justamente lo que hace inauditable una caja.
     */
    public function destroy(CashMovement $movement)
    {
        $session = $movement->session;

        if (! $session?->isOpen()) {
            return back()->withErrors([
                'error' => 'No se pueden anular movimientos de un turno cerrado.',
            ]);
        }

        $movement->delete();

        return back()->with('success', 'Movimiento anulado.');
    }
}
