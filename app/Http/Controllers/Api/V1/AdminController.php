<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesCompanyLogo;
use App\Models\Branch;
use App\Models\Caja;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Support\CajaForm;
use App\Support\CashSessionCloser;
use App\Support\Catalog;
use App\Support\PaymentMethodForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La sección «Administración» del móvil: mi empresa, sucursales, cajas y
 * métodos de pago.
 *
 * Mismas reglas que la web (CajaForm, PaymentMethodForm, el código único por
 * empresa de las sucursales) y mismos permisos, puestos en las rutas. El
 * CompanyScope acota todo a la empresa activa. No se borra nada desde aquí:
 * se da de baja con «activo», que es reversible.
 */
class AdminController extends ApiController
{
    use HandlesCompanyLogo;

    /* ── Mi empresa ──────────────────────────────────────────────────────── */

    public function company(Request $request): JsonResponse
    {
        return $this->json(['data' => $this->companyPayload($this->tenant($request))]);
    }

    /**
     * Los datos de texto (el logo va por updateLogo). El NIT no se edita
     * (con él el operador verifica a quien pide soporte).
     */
    public function updateCompany(Request $request): JsonResponse
    {
        $company = $this->tenant($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            // Cómo se cierra caja; si no llega (app vieja), no se toca.
            'cash_count_mode' => ['sometimes', 'in:'.implode(',', array_keys(CashSessionCloser::MODES))],
        ], [
            'name.required' => 'El nombre de la barbería es obligatorio.',
        ]);

        $company->update([
            'name' => trim($data['name']),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'receipt_footer' => $data['receipt_footer'] ?? null,
            ...(array_key_exists('cash_count_mode', $data) ? ['cash_count_mode' => $data['cash_count_mode']] : []),
        ]);

        return $this->json([
            'message' => 'Datos de la barbería actualizados.',
            'data' => $this->companyPayload($company->refresh()),
        ]);
    }

    /** Subir o quitar el logo. Mismas reglas de archivo que la web. */
    public function updateLogo(Request $request): JsonResponse
    {
        $company = $this->tenant($request);

        $request->validate([
            'logo' => ['required_without:remove_logo', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.required_without' => 'Elige una imagen.',
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
            'logo.mimes' => 'El logo tiene que ser PNG, JPG o WEBP.',
        ]);

        $this->syncCompanyLogo($request, $company);

        return $this->json([
            'message' => $company->logo ? 'Logo actualizado.' : 'Logo quitado.',
            'data' => $this->companyPayload($company->refresh()),
        ]);
    }

    /* ── Catálogo público ────────────────────────────────────────────────── */

    public function catalog(Request $request): JsonResponse
    {
        return $this->json(['data' => $this->catalogPayload($this->tenant($request))]);
    }

    public function updateCatalog(Request $request): JsonResponse
    {
        $company = $this->tenant($request);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'show_products' => ['sometimes', 'boolean'],
        ]);

        $company->update([
            ...(array_key_exists('enabled', $data) ? ['catalog_enabled' => (bool) $data['enabled']] : []),
            ...(array_key_exists('show_products', $data) ? ['catalog_show_products' => (bool) $data['show_products']] : []),
        ]);

        return $this->json([
            'message' => $company->catalog_enabled ? 'Catálogo publicado.' : 'Catálogo sin publicar.',
            'data' => $this->catalogPayload($company),
        ]);
    }

    /** Enlace nuevo: el anterior (y los QR impresos) dejan de funcionar. */
    public function regenerateCatalog(Request $request): JsonResponse
    {
        $company = $this->tenant($request);
        Catalog::regenerate($company);

        return $this->json([
            'message' => 'Enlace nuevo generado. El anterior y su QR ya no funcionan.',
            'data' => $this->catalogPayload($company),
        ]);
    }

    protected function catalogPayload(Company $company): array
    {
        return [
            'enabled' => (bool) $company->catalog_enabled,
            'show_products' => (bool) $company->catalog_show_products,
            'url' => Catalog::url($company),
            'poster_url' => Catalog::posterUrl($company),
            'services_count' => Catalog::services($company)->flatten()->count(),
        ];
    }

    /* ── Sucursales ──────────────────────────────────────────────────────── */

    public function branches(Request $request): JsonResponse
    {
        $branches = Branch::withCount(['cajas', 'personal'])->orderBy('name')->get();
        $company = $this->tenant($request);

        return $this->json([
            'data' => $branches->map($this->branchPayload(...))->all(),
            // Para no ofrecer «Nueva sucursal» si el plan ya no deja.
            'can_add' => $request->user()->is_super_admin || $company->withinLimit('branches'),
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $company = $this->tenant($request);

        if (! $request->user()->is_super_admin && ! $company->withinLimit('branches')) {
            return $this->json([
                'message' => 'Alcanzaste el límite de sucursales de tu plan. '
                    . 'Contacta a tu proveedor para ampliarlo.',
            ], 403);
        }

        $data = $this->validateBranch($request, $company->id);

        $branch = Branch::create([
            ...$data,
            'company_id' => $company->id,
            'active' => $request->boolean('active', true),
        ]);

        return $this->json([
            'message' => 'Sucursal creada.',
            'data' => $this->branchPayload($branch->loadCount(['cajas', 'personal'])),
        ], 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validateBranch($request, $branch->company_id, $branch);

        $branch->update([
            ...$data,
            // Vacío no borra el código: identifica la sucursal en todas partes.
            'code' => ($data['code'] ?? null) ?: $branch->code,
            'active' => $request->boolean('active', (bool) $branch->active),
        ]);

        return $this->json([
            'message' => 'Sucursal actualizada.',
            'data' => $this->branchPayload($branch->loadCount(['cajas', 'personal'])),
        ]);
    }

    /* ── Cajas ───────────────────────────────────────────────────────────── */

    public function cajas(): JsonResponse
    {
        $cajas = Caja::with(['branch', 'personal'])->orderBy('name')->get();

        return $this->json(['data' => $cajas->map($this->cajaPayload(...))->all()]);
    }

    /** Sucursales y personal activos, para los selectores del formulario. */
    public function cajaOptions(): JsonResponse
    {
        return $this->json([
            'branches' => $this->branchOptions(),
            'personal' => Personal::where('active', true)->orderBy('full_name')->get()
                ->map(fn (Personal $p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'branch_id' => $p->branch_id,
                ])->all(),
        ]);
    }

    public function storeCaja(Request $request): JsonResponse
    {
        $companyId = $this->tenant($request)->id;

        $data = $request->validate(CajaForm::rules($companyId));

        $caja = Caja::create([
            ...$data,
            'company_id' => $companyId,
            'active' => $request->boolean('active', true),
        ]);

        return $this->json([
            'message' => 'Caja creada.',
            'data' => $this->cajaPayload($caja->load(['branch', 'personal'])),
        ], 201);
    }

    public function updateCaja(Request $request, Caja $caja): JsonResponse
    {
        $data = $request->validate(CajaForm::rules($caja->company_id));

        $caja->update([
            ...$data,
            'active' => $request->boolean('active', (bool) $caja->active),
        ]);

        return $this->json([
            'message' => 'Caja actualizada.',
            'data' => $this->cajaPayload($caja->load(['branch', 'personal'])),
        ]);
    }

    /* ── Métodos de pago ─────────────────────────────────────────────────── */

    /** Todos, también los dados de baja: aquí se administran. */
    public function paymentMethods(): JsonResponse
    {
        return $this->json([
            'data' => PaymentMethod::ordered()->get()->map($this->methodPayload(...))->all(),
        ]);
    }

    public function storePaymentMethod(Request $request, PaymentMethodForm $form): JsonResponse
    {
        $companyId = $this->tenant($request)->id;
        $data = $form->validate($request);

        $method = PaymentMethod::create($data + [
            'company_id' => $companyId,
            'slug' => PaymentMethod::generateSlug($data['name'], $companyId),
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
        ]);

        return $this->json([
            'message' => "Método de pago «{$method->name}» creado.",
            'data' => $this->methodPayload($method),
        ], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod, PaymentMethodForm $form): JsonResponse
    {
        // El slug NO se toca al renombrar: es lo que guardan ventas y caja.
        $paymentMethod->update($form->validate($request, $paymentMethod));

        return $this->json([
            'message' => "Método de pago «{$paymentMethod->name}» actualizado.",
            'data' => $this->methodPayload($paymentMethod),
        ]);
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    protected function tenant(Request $request): Company
    {
        $company = $request->attributes->get('tenant_company');

        abort_unless($company, 404);

        return $company;
    }

    protected function validateBranch(Request $request, int $companyId, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Único dentro de la empresa; vacío = lo genera el sistema (S01…).
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('branches', 'code')->where('company_id', $companyId)->ignore($branch?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Ponle un nombre a la sucursal.',
            'code.unique' => 'Ya hay otra sucursal con ese código.',
        ]);
    }

    /** Sucursales activas como [{id, name}], para los selectores. */
    public static function branchOptions(): array
    {
        return Branch::where('active', true)->orderBy('name')->get()
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code])
            ->all();
    }

    protected function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'tax_id' => $company->tax_id,
            'tax_id_label' => $company->tax_id_label,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'receipt_footer' => $company->receipt_footer,
            'cash_count_mode' => $company->cash_count_mode ?: 'total',
            'logo_url' => $company->logoUrl(),
        ];
    }

    protected function branchPayload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'address' => $branch->address,
            'manager_name' => $branch->manager_name,
            'active' => (bool) $branch->active,
            'cajas_count' => (int) ($branch->cajas_count ?? 0),
            'personal_count' => (int) ($branch->personal_count ?? 0),
        ];
    }

    protected function cajaPayload(Caja $caja): array
    {
        return [
            'id' => $caja->id,
            'name' => $caja->name,
            'code' => $caja->code,
            'description' => $caja->description,
            'branch_id' => $caja->branch_id,
            'branch' => $caja->branch?->name,
            'personal_id' => $caja->personal_id,
            'personal' => $caja->personal?->full_name,
            'active' => (bool) $caja->active,
        ];
    }

    protected function methodPayload(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'slug' => $method->slug,
            'name' => $method->name,
            'counts_as_cash' => (bool) $method->counts_as_cash,
            'requires_reference' => (bool) $method->requires_reference,
            'active' => (bool) $method->active,
        ];
    }
}
