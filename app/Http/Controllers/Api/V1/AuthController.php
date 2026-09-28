<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Entrada a la API: iniciar sesión, cerrarla y saber quién soy.
 *
 * El token de Sanctum no lleva empresa: un usuario puede trabajar en varias
 * barberías con el mismo token e ir cambiando la cabecera X-Company-Id. Por eso
 * el login devuelve la lista de empresas y /me describe la activa.
 */
class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // Nombre del dispositivo: así el usuario puede reconocer y revocar
            // sesiones sueltas («iPhone de Tania») en vez de ver tokens opacos.
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // El mismo mensaje tanto si el correo no existe como si la contraseña
        // falla: distinguirlos revelaría qué correos están dados de alta.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if (! $user->active) {
            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está desactivada. Habla con el administrador.',
            ]);
        }

        $companies = $this->companiesFor($user);

        if ($companies->isEmpty() && ! $user->is_super_admin) {
            throw ValidationException::withMessages([
                'email' => 'Tu usuario todavía no está asignado a ninguna barbería.',
            ]);
        }

        // Un dispositivo, un token: al volver a entrar desde el mismo teléfono
        // se revoca el anterior en lugar de acumularlos para siempre.
        $user->tokens()->where('name', $data['device_name'])->delete();

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return $this->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'companies' => $companies->values()->all(),
            // Si solo hay una, la app puede fijar la cabecera sin preguntar.
            'default_company_id' => $companies->count() === 1 ? $companies->first()['id'] : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * Todo lo que la app necesita para pintar su menú: quién soy, en qué
     * barbería estoy, qué permisos tengo y qué módulos incluye el plan.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('tenant_company');

        return $this->json([
            'user' => $this->userPayload($user),
            'company' => $company ? $this->companyPayload($company) : null,
            'personal' => $this->personalPayload($user, $company),
            'role' => $company ? $this->rolePayload($user, $company) : null,
            'permissions' => $this->permissionsFor($user, $company),
            'plan' => $company ? $this->planPayload($company) : null,
            'subscription' => $company ? $this->subscriptionPayload($company) : null,
            // Cada barbería define los suyos, así que la app no puede llevarlos
            // escritos: los recibe al arrancar.
            'payment_methods' => $company ? $this->paymentMethodsPayload($company) : [],
        ]);
    }

    public function companies(Request $request): JsonResponse
    {
        return $this->json([
            'data' => $this->companiesFor($request->user())->values()->all(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Payloads
     |--------------------------------------------------------------------- */

    protected function companiesFor(User $user)
    {
        // El superadmin las ve todas; el resto, solo aquellas en las que está
        // activo. Se consulta sin scope porque Company es un modelo global.
        $companies = $user->is_super_admin
            ? Company::where('active', true)->orderBy('name')->get()
            : $user->activeCompanies()->orderBy('name')->get();

        return $companies->map(fn (Company $company) => [
            'id' => $company->id,
            'name' => $company->name,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
        ]);
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_super_admin' => (bool) $user->is_super_admin,
        ];
    }

    protected function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'currency' => $company->currency,
            'currency_symbol' => \App\Support\Money::symbol($company),
            'timezone' => $company->timezone,
            'country' => $company->country,
            // Lo que el móvil necesita para el encabezado del comprobante.
            'tax_id' => $company->tax_id,
            'tax_id_label' => $company->tax_id_label,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'logo_url' => $company->logoUrl(),
            'receipt_footer' => $company->receiptFooter(),
        ];
    }

    /** Su ficha de personal en esa barbería, si la tiene. */
    protected function personalPayload(User $user, ?Company $company): ?array
    {
        if (! $company) {
            return null;
        }

        $personal = app(Tenancy::class)->runFor(
            $company->id,
            fn () => $user->personal()->with('cargo')->first(),
        );

        if (! $personal) {
            return null;
        }

        return [
            'id' => $personal->id,
            'full_name' => $personal->full_name,
            'cargo' => $personal->cargo?->name,
            'bookable' => (bool) $personal->bookable,
            'photo_url' => $personal->photoUrl(),
            'agenda_color' => $personal->agenda_color,
        ];
    }

    protected function rolePayload(User $user, Company $company): ?array
    {
        $role = $company->getRoleForUser($user);

        return $role ? ['slug' => $role->slug, 'name' => $role->name] : null;
    }

    /** @return array<int, string> */
    protected function permissionsFor(User $user, ?Company $company): array
    {
        if ($user->is_super_admin) {
            return ['*'];
        }

        if (! $company) {
            return [];
        }

        return array_values($company->getPermissionsForUser($user));
    }

    protected function planPayload(Company $company): array
    {
        $features = $company->effectiveFeatures();

        return [
            'name' => $company->subscription?->plan?->name,
            'modules' => array_values($features),
            // Con las etiquetas la app no tiene que traducirlas por su cuenta.
            'module_labels' => collect($features)
                ->mapWithKeys(fn (string $m) => [$m => Plan::MODULES[$m] ?? $m])
                ->all(),
        ];
    }

    /**
     * Los métodos con los que puede cobrar la app.
     *
     * «counts_as_cash» viaja porque el móvil enseña el efectivo esperado del
     * turno: sin ese dato pintaría en el cajón dinero que está en el banco.
     */
    protected function paymentMethodsPayload(Company $company): array
    {
        return app(Tenancy::class)->runFor($company->id, fn () => PaymentMethod::active()
            ->ordered()
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'slug' => $method->slug,
                'name' => $method->name,
                'counts_as_cash' => $method->counts_as_cash,
                'requires_reference' => $method->requires_reference,
            ])
            ->all());
    }

    protected function subscriptionPayload(Company $company): ?array
    {
        $subscription = $company->subscription;

        if (! $subscription) {
            return null;
        }

        return [
            'status' => $subscription->status,
            'on_trial' => $subscription->onTrial(),
            'allows_write' => $subscription->allowsWrite(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
        ];
    }
}
