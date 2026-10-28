<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\Idempotency;
use App\Support\SaleItemResolver;
use App\Support\SaleRegistrar;
use App\Support\ShopTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cobrar desde el móvil.
 *
 * Toda la lógica —congelar precios, descontar stock, mover la caja, devengar
 * comisiones y marcar la cita atendida— vive en SaleRegistrar, el mismo que usa
 * el POS de la web. Aquí solo se valida la entrada y se envuelve en la
 * idempotencia.
 *
 * Lo de la idempotencia no es adorno: si el teléfono pierde la red justo
 * después de mandar el cobro y reintenta, sin ella se crearían dos ventas.
 * Ver App\Support\Idempotency.
 */
class SaleController extends ApiController
{
    public function __construct(
        protected SaleRegistrar $registrar,
        protected SaleItemResolver $items,
        protected Idempotency $idempotency,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $companyId = $company->id;

        $data = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'appointment_id' => ['nullable', Rule::exists('appointments', 'id')->where('company_id', $companyId)],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', Rule::in(['servicio', 'producto'])],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.personal_id' => ['nullable', 'integer'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method' => ['required', Rule::in(array_keys(SalePayment::methods()))],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Añade al menos un servicio o producto.',
            'payments.required' => 'Indica cómo se paga.',
        ]);

        return $this->idempotency->run($request, 'sales.store', function () use ($company, $data) {
            // El precio lo pone el catálogo, nunca lo que mande el teléfono.
            $items = $this->items->resolve($data['items'], $data['personal_id'] ?? null);

            $sale = $this->registrar->register($company, $data, $items, $data['payments']);

            return [
                [
                    'message' => "Venta {$sale->number} cobrada.",
                    'data' => $this->salePayload($sale->fresh()->load([
                        'items.personal', 'payments', 'client', 'personal', 'branch',
                    ])),
                ],
                201,
            ];
        });
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['items.personal', 'payments', 'client', 'personal', 'branch', 'cashSession.caja', 'canceller']);

        return $this->json(['data' => $this->salePayload($sale)]);
    }

    /**
     * El historial: un día (`day`) o un periodo (`from`/`to`), opcionalmente de
     * un barbero o de un estado. El resumen cuenta todo el filtro, no solo lo
     * devuelto, y las anuladas no suman a lo cobrado (igual que la web).
     */
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from', $request->query('day'));
        $to = $request->query('to', $request->query('day'));

        $query = Sale::query()
            ->when($from, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->when($request->integer('personal_id'), fn ($q, $id) => $q->where('personal_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $sales = (clone $query)->with(['client', 'personal', 'payments'])
            ->latest('sold_at')
            ->limit(min((int) $request->query('limit', 100), 200))
            ->get();

        $paid = (clone $query)->paid();
        $company = $this->company($request);

        return $this->json([
            'from' => $from,
            'to' => $to,
            'summary' => [
                'count' => (clone $paid)->count(),
                'total' => (float) (clone $paid)->sum('total'),
                'tips' => (float) (clone $paid)->sum('tip'),
                'cancelled' => (clone $query)->where('status', 'anulada')->count(),
            ],
            'data' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'status' => $sale->status,
                'status_label' => $sale->statusLabel(),
                'total' => (float) $sale->total,
                'sold_at' => ShopTime::iso($sale->sold_at, $company),
                'client' => $sale->client?->full_name,
                'personal' => $sale->personal?->full_name,
                'methods' => $sale->payments->map->methodLabel()->unique()->values()->all(),
            ])->all(),
        ]);
    }

    public function cancel(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->registrar->cancel($sale, $data['cancel_reason'] ?? null);

        $message = "Venta {$sale->number} anulada. Se devolvió el stock y se registró el egreso en caja.";

        // Las comisiones ya pagadas no se tocan: hay que regularizarlas a mano.
        if ($left = $this->registrar->settledCommissionsLeftOver()) {
            $message .= ' Ojo: '.($left === 1 ? '1 comisión ya estaba liquidada' : "{$left} comisiones ya estaban liquidadas")
                .'; descuéntala del próximo pago al barbero.';
        }

        $sale->load(['items.personal', 'payments', 'client', 'personal', 'branch', 'canceller']);

        return $this->json([
            'message' => $message,
            'settled_commissions' => $left,
            'data' => $this->salePayload($sale),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    protected function salePayload(Sale $sale): array
    {
        $company = request()->attributes->get('tenant_company');

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'status' => $sale->status,
            'status_label' => $sale->statusLabel(),
            'sold_at' => ShopTime::iso($sale->sold_at, $company),
            'cancel_reason' => $sale->cancel_reason,
            'cancelled_at' => ShopTime::iso($sale->cancelled_at, $company),
            'cancelled_by' => $sale->relationLoaded('canceller') ? $sale->canceller?->name : null,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tip' => (float) $sale->tip,
            'total' => (float) $sale->total,
            'notes' => $sale->notes,

            'client' => $sale->client
                ? ['id' => $sale->client->id, 'full_name' => $sale->client->full_name]
                : null,
            'personal' => $sale->personal
                ? ['id' => $sale->personal->id, 'full_name' => $sale->personal->full_name]
                : null,
            'branch' => $sale->branch?->name,
            'appointment_id' => $sale->appointment_id,

            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
                'personal' => $item->personal?->full_name,
            ])->all(),

            'payments' => $sale->payments->map(fn ($payment) => [
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $payment->methodLabel(),
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
            ])->all(),
        ];
    }

    protected function company(Request $request): Company
    {
        return $request->attributes->get('tenant_company');
    }
}
