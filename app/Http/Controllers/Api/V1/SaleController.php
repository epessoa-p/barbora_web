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
        $sale->load(['items.personal', 'payments', 'client', 'personal', 'branch', 'cashSession.caja']);

        return $this->json(['data' => $this->salePayload($sale)]);
    }

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::with(['client', 'personal'])
            ->when($request->query('day'), fn ($q, $day) => $q->whereDate('sold_at', $day))
            ->when($request->integer('personal_id'), fn ($q, $id) => $q->where('personal_id', $id))
            ->latest('sold_at')
            ->limit(min((int) $request->query('limit', 50), 100))
            ->get();

        return $this->json([
            'data' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'status' => $sale->status,
                'total' => (float) $sale->total,
                'sold_at' => ShopTime::iso($sale->sold_at, $this->company($request)),
                'client' => $sale->client?->full_name,
                'personal' => $sale->personal?->full_name,
            ])->all(),
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
            'sold_at' => ShopTime::iso($sale->sold_at, $company),
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
