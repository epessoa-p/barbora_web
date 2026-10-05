<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashSessionController extends Controller
{
    /** Caja actual: el turno abierto, o la invitación a abrir uno. */
    public function current(Request $request)
    {
        $cajas = Caja::where('active', true)->orderBy('name')->get();

        // Sin caja en la URL, se prefiere la asignada al personal del usuario;
        // si no tiene una propia, la primera de la lista (la general).
        $myPersonalId = Personal::where('user_id', auth()->id())->value('id');
        $default = ($myPersonalId ? $cajas->firstWhere('personal_id', $myPersonalId) : null) ?? $cajas->first();

        $caja = $cajas->firstWhere('id', $request->integer('caja')) ?? $default;

        $session = $caja?->sessions()->open()->with(['movements.creator', 'openedBy'])->first();

        return view('cash.current', [
            'cajas' => $cajas,
            'caja' => $caja,
            'session' => $session,
            'totals' => $session?->totals(),
        ]);
    }

    /** Historial de turnos cerrados y abiertos. */
    public function index(Request $request)
    {
        $query = CashSession::with(['caja', 'openedBy', 'closedBy'])->latest('opened_at');

        if ($cajaId = $request->integer('caja')) {
            $query->where('caja_id', $cajaId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('cash.sessions.index', [
            'sessions' => $query->paginate(20)->withQueryString(),
            'cajas' => Caja::orderBy('name')->get(),
        ]);
    }

    public function show(CashSession $session)
    {
        $session->load(['caja', 'movements.creator', 'openedBy', 'closedBy']);

        return view('cash.sessions.show', [
            'session' => $session,
            'totals' => $session->totals(),
            'breakdown' => $session->byPaymentMethod(),
            // Con los de baja incluidos: el arqueo de un turno viejo tiene que
            // seguir nombrando un método que ya no se usa.
            'methodNames' => PaymentMethod::withTrashed()->pluck('name', 'slug')->all(),
            'cashMethods' => PaymentMethod::cashSlugs(),
        ]);
    }

    /** Abrir turno. */
    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();

        $data = $request->validate([
            'caja_id' => ['required', Rule::exists('cajas', 'id')->where('company_id', $companyId)],
            'opening_amount' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ], [
            'opening_amount.required' => 'Indica el fondo con el que abre la caja.',
        ]);

        $caja = Caja::findOrFail($data['caja_id']);

        // Una caja con turno abierto no puede abrir otro: el arqueo dejaría de
        // tener sentido si dos turnos comparten el mismo cajón.
        if ($caja->isOpen()) {
            throw ValidationException::withMessages([
                'caja_id' => "La caja «{$caja->name}» ya tiene un turno abierto. Ciérralo antes de abrir otro.",
            ]);
        }

        $session = CashSession::create([
            'company_id' => $companyId,
            'caja_id' => $caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opened_by' => auth()->id(),
            'opening_amount' => $data['opening_amount'],
            'opening_notes' => $data['opening_notes'] ?? null,
        ]);

        return redirect()->route('cash.current', ['caja' => $caja->id])
            ->with('success', "Turno abierto en «{$caja->name}» con un fondo de "
                . \App\Support\Money::format($session->opening_amount, $caja->company).'.');
    }

    /** Cerrar turno: se cuenta el efectivo y se guarda el arqueo. */
    public function close(Request $request, CashSession $session)
    {
        if (! $session->isOpen()) {
            return redirect()->route('cash.sessions.show', $session)
                ->withErrors(['error' => 'Este turno ya está cerrado.']);
        }

        $data = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ], [
            'closing_amount.required' => 'Indica cuánto efectivo contaste en el cajón.',
        ]);

        $session->load('movements');
        $expected = $session->totals()['expected_cash'];
        $counted = (float) $data['closing_amount'];

        DB::transaction(function () use ($session, $data, $expected, $counted) {
            $session->update([
                'status' => 'cerrada',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'closing_amount' => $counted,
                'expected_amount' => $expected,
                'difference' => $counted - $expected,
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);

            // El saldo de la caja pasa a ser lo que de verdad quedó contado.
            $session->caja->update(['balance' => $counted]);
        });

        return redirect()->route('cash.sessions.show', $session)
            ->with('success', 'Turno cerrado. El arqueo quedó '.strtolower($session->differenceLabel()).'.');
    }
}
