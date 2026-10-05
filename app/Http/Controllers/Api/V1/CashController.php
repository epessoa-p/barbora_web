<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Sale;
use App\Support\ShopTime;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El turno de caja desde el móvil: abrirlo, mover dinero y arquear.
 *
 * Las reglas son las mismas que en la web y viven en el modelo CashSession
 * (totales, efectivo esperado, diferencia). Aquí solo se traduce a JSON.
 */
class CashController extends ApiController
{
    /** Cajas de la barbería y cuál tiene turno abierto. */
    public function cajas(): JsonResponse
    {
        $cajas = Caja::where('active', true)->with('branch')->orderBy('name')->get();

        return $this->json([
            'data' => $cajas->map(fn (Caja $caja) => [
                'id' => $caja->id,
                'name' => $caja->name,
                'branch' => $caja->branch?->name,
                'is_open' => $caja->isOpen(),
            ])->all(),
        ]);
    }

    /**
     * El turno abierto. Es lo primero que consulta la app: sin turno no se
     * puede cobrar, y conviene decirlo antes de que el barbero arme la comanda.
     */
    public function current(Request $request): JsonResponse
    {
        $session = $this->openSession($request->integer('caja_id') ?: null);

        if (! $session) {
            return $this->json([
                'data' => null,
                'message' => 'No hay ningún turno de caja abierto.',
            ]);
        }

        return $this->json(['data' => $this->sessionPayload($session, withMovements: true)]);
    }

    public function open(Request $request): JsonResponse
    {
        $companyId = $this->targetCompanyId();

        $data = $request->validate([
            'caja_id' => ['required', Rule::exists('cajas', 'id')->where('company_id', $companyId)],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'opening_amount.required' => 'Indica el fondo con el que abre la caja.',
        ]);

        $caja = Caja::findOrFail($data['caja_id']);

        // Dos turnos en el mismo cajón harían que el arqueo no signifique nada.
        if ($caja->isOpen()) {
            throw ValidationException::withMessages([
                'caja_id' => "La caja «{$caja->name}» ya tiene un turno abierto.",
            ]);
        }

        $session = CashSession::create([
            'company_id' => $companyId,
            'caja_id' => $caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opened_by' => $request->user()->id,
            'opening_amount' => $data['opening_amount'],
            'opening_notes' => $data['opening_notes'] ?? null,
        ]);

        return $this->json([
            'message' => "Turno abierto en «{$caja->name}».",
            'data' => $this->sessionPayload($session->fresh(), withMovements: true),
        ], 201);
    }

    /** Arqueo: se cuenta el efectivo y se cierra. */
    public function close(Request $request, CashSession $session): JsonResponse
    {
        if (! $session->isOpen()) {
            return $this->json([
                'error' => 'session_closed',
                'message' => 'Este turno ya está cerrado.',
            ], 422);
        }

        $data = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'closing_amount.required' => 'Indica cuánto efectivo contaste en el cajón.',
        ]);

        $session->load('movements');

        // El esperado se guarda, no se recalcula al mirarlo después: es la foto
        // del momento del arqueo.
        $expected = $session->totals()['expected_cash'];
        $counted = (float) $data['closing_amount'];

        DB::transaction(function () use ($session, $data, $expected, $counted, $request) {
            $session->update([
                'status' => 'cerrada',
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
                'closing_amount' => $counted,
                'expected_amount' => $expected,
                'difference' => round($counted - $expected, 2),
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);
        });

        return $this->json([
            'message' => 'Turno cerrado: '.strtolower($session->fresh()->differenceLabel()).'.',
            'data' => $this->sessionPayload($session->fresh()->load('movements'), withMovements: true),
        ]);
    }

    /** Movimiento manual: una compra, un retiro, un gasto del día. */
    public function storeMovement(Request $request): JsonResponse
    {
        $session = $this->openSession($request->integer('caja_id') ?: null);

        if (! $session) {
            return $this->json([
                'error' => 'no_open_session',
                'message' => 'Abre un turno de caja antes de registrar movimientos.',
            ], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CashMovement::TYPES))],
            'payment_method' => ['required', Rule::in(array_keys(CashMovement::paymentMethods()))],
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'concept.required' => 'Escribe de qué es el movimiento.',
            'amount.min' => 'El importe tiene que ser mayor que cero.',
        ]);

        $movement = $session->movements()->create($data + [
            'company_id' => $session->company_id,
            'created_by' => $request->user()->id,
        ]);

        return $this->json([
            'message' => 'Movimiento registrado.',
            'data' => $this->movementPayload($movement),
            'session' => $this->sessionPayload($session->fresh()->load('movements')),
        ], 201);
    }

    public function movements(Request $request): JsonResponse
    {
        $session = $this->openSession($request->integer('caja_id') ?: null);

        if (! $session) {
            return $this->json(['data' => [], 'session' => null]);
        }

        $session->load('movements.creator');
        $details = $this->saleDetails($session->movements);

        return $this->json([
            'session' => $this->sessionPayload($session),
            'data' => $session->movements
                ->map(fn (CashMovement $m) => $this->movementPayload($m, $details))
                ->all(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    /**
     * El turno abierto de una caja, o el primero que haya abierto en la
     * barbería. Casi siempre hay uno solo; con varias sucursales, la app manda
     * el caja_id.
     */
    protected function openSession(?int $cajaId): ?CashSession
    {
        return CashSession::open()
            ->when($cajaId, fn ($q) => $q->where('caja_id', $cajaId))
            ->with(['caja', 'movements', 'openedBy'])
            ->orderBy('opened_at')
            ->first();
    }

    protected function sessionPayload(CashSession $session, bool $withMovements = false): array
    {
        $company = request()->attributes->get('tenant_company');
        $totals = $session->totals();

        $payload = [
            'id' => $session->id,
            'status' => $session->status,
            'is_open' => $session->isOpen(),
            'caja' => [
                'id' => $session->caja?->id,
                'name' => $session->caja?->name,
            ],
            'opened_at' => ShopTime::iso($session->opened_at, $company),
            'opened_by' => $session->openedBy?->name,
            'opening_amount' => (float) $session->opening_amount,
            'duration' => $session->durationLabel(),
            'totals' => [
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'cash_income' => $totals['cash_income'],
                'cash_expense' => $totals['cash_expense'],
                // Lo que debería haber en el cajón ahora mismo.
                'expected_cash' => $totals['expected_cash'],
                'net' => $totals['net'],
            ],
            'by_payment_method' => $session->byPaymentMethod(),
            'movements_count' => $session->movements()->count(),
        ];

        if (! $session->isOpen()) {
            $payload += [
                'closed_at' => ShopTime::iso($session->closed_at, $company),
                'closed_by' => $session->closedBy?->name,
                'closing_amount' => (float) $session->closing_amount,
                'expected_amount' => (float) $session->expected_amount,
                'difference' => (float) $session->difference,
                'difference_label' => $session->differenceLabel(),
            ];
        }

        if ($withMovements) {
            $details = $this->saleDetails($session->movements);
            $payload['movements'] = $session->movements
                ->map(fn (CashMovement $m) => $this->movementPayload($m, $details))
                ->all();
        }

        return $payload;
    }

    /**
     * El detalle de los movimientos que son ventas: «Corte clásico + Barba».
     *
     * El movimiento guarda el número de venta en `reference`; aquí se traen esas
     * ventas de una sola vez (sin N+1) y se arma el listado de lo que incluyó.
     *
     * @param  Collection<int, CashMovement>  $movements
     * @return array<string, string>  número de venta → detalle
     */
    protected function saleDetails(Collection $movements): array
    {
        $refs = $movements->pluck('reference')->filter()->unique()->values();

        if ($refs->isEmpty()) {
            return [];
        }

        return Sale::whereIn('number', $refs)->with('items')->get()
            ->mapWithKeys(fn (Sale $sale) => [
                $sale->number => $sale->items->map(function ($item) {
                    $qty = (float) $item->quantity;

                    return $qty > 1
                        ? $item->description.' ×'.rtrim(rtrim(number_format($qty, 2), '0'), '.')
                        : $item->description;
                })->implode(' + '),
            ])->all();
    }

    /** @param  array<string, string>  $saleDetails */
    protected function movementPayload(CashMovement $movement, array $saleDetails = []): array
    {
        $company = request()->attributes->get('tenant_company');

        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'payment_method' => $movement->payment_method,
            'payment_method_label' => CashMovement::paymentMethods()[$movement->payment_method]
                ?? $movement->payment_method,
            'concept' => $movement->concept,
            // Qué incluyó la venta (si el movimiento es una venta).
            'detail' => $movement->reference ? ($saleDetails[$movement->reference] ?? null) : null,
            'amount' => (float) $movement->amount,
            'reference' => $movement->reference,
            'created_at' => ShopTime::iso($movement->created_at, $company),
            'created_by' => $movement->creator?->name,
        ];
    }

    protected function targetCompanyId(): int
    {
        /** @var Company $company */
        $company = request()->attributes->get('tenant_company');

        return $company->id;
    }
}
