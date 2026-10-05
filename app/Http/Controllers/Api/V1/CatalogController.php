<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ServiceResource;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ShopTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de apoyo para la app: servicios y clientes.
 *
 * Los clientes se paginan y se buscan porque una barbería con años de historia
 * puede tener miles, y el teléfono no va a descargarlos todos.
 */
class CatalogController extends ApiController
{
    public function services(Request $request): JsonResponse
    {
        $services = Service::with('category')
            ->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->json(['data' => ServiceResource::collection($services)->resolve()]);
    }

    /** Categorías de servicios, para elegir al editar desde el móvil. */
    public function serviceCategories(): JsonResponse
    {
        $categories = ServiceCategory::where('active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        return $this->json(['data' => $categories->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name,
        ])->all()]);
    }

    /**
     * Editar un servicio desde el móvil (precio, duración, nombre…).
     *
     * Mismas reglas que la web (App\Http\Controllers\Services\ServiceController).
     * El binding {service} ya está acotado a la empresa activa.
     */
    public function updateService(Request $request, Service $service): JsonResponse
    {
        $companyId = $service->company_id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('services', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($service->id),
            ],
            'service_category_id' => [
                'nullable',
                Rule::exists('service_categories', 'id')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.unique' => 'Ya existe un servicio con ese nombre.',
            'duration_minutes.min' => 'La duración mínima es de 5 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 10 horas.',
        ]);

        $data['active'] = $request->boolean('active', (bool) $service->active);

        $service->update($data);
        $service->load('category');

        return $this->json([
            'message' => 'Servicio actualizado.',
            'data' => (new ServiceResource($service))->resolve(),
        ]);
    }

    /**
     * Productos que se pueden vender, con lo que queda en almacén.
     *
     * El stock va incluido porque el móvil lo necesita para no dejar añadir a
     * la comanda algo que ya no hay: el servidor lo rechazaría igual, pero es
     * mejor decirlo antes de que el barbero se lo prometa al cliente.
     */
    public function products(Request $request): JsonResponse
    {
        $products = Product::sellable()
            ->with('stocks')
            ->when($request->query('q'), function ($q, string $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                                       ->orWhere('sku', 'like', $like));
            })
            ->orderBy('name')
            ->get();

        return $this->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'price' => (float) $product->sale_price,
                'tracks_stock' => (bool) $product->track_stock,
                // Null cuando no se lleva control: no es lo mismo que cero.
                'stock' => $product->track_stock ? $product->totalStock() : null,
                'low_stock' => $product->track_stock
                    && $product->min_stock > 0
                    && $product->totalStock() <= $product->min_stock,
            ])->all(),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))
            ->when($request->query('q'), function ($q, string $term) {
                $like = '%'.$term.'%';

                $q->where(fn ($w) => $w->where('full_name', 'like', $like)
                                       ->orWhere('phone', 'like', $like)
                                       ->orWhere('document_number', 'like', $like));
            })
            ->orderBy('full_name')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->json([
            'data' => ClientResource::collection($clients->items())->resolve(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    /**
     * La ficha del cliente: sus datos, un resumen de su historia con la
     * barbería y sus últimas citas.
     *
     * El binding {client} ya está acotado a la empresa activa (SetTenant corre
     * antes que SubstituteBindings), así que una ficha de otra barbería da 404.
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        $company = $request->attributes->get('tenant_company');

        $recent = $client->appointments()
            ->with(['personal', 'services', 'branch'])
            ->orderByDesc('starts_at')
            ->limit(5)
            ->get();

        // Solo cuentan las ventas pagadas: una comanda anulada no es una visita.
        $lastSale = $client->sales()->paid()->orderByDesc('sold_at')->first();

        return $this->json([
            'data' => (new ClientResource($client))->resolve() + [
                'stats' => [
                    'visits' => $client->sales()->paid()->count(),
                    'total_spent' => (float) $client->sales()->paid()->sum('total'),
                    'last_visit' => ShopTime::iso($lastSale?->sold_at, $company),
                    // Citas por venir que aún ocupan hueco (ni canceladas ni no_show).
                    'upcoming' => $client->appointments()
                        ->blocking()
                        ->where('starts_at', '>', now())
                        ->count(),
                ],
                'recent_appointments' => AppointmentResource::collection($recent)->resolve(),
            ],
        ]);
    }
}
