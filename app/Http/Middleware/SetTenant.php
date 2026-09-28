<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsToApi;
use App\Models\User;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa activa de la petición. Corre en el grupo `web` y en el `api`.
 *
 *   - Invitado           → sin empresa (fail-closed). No rompe el login:
 *                          User y Company son modelos globales.
 *   - Superadmin         → Modo Global (sin filtro), salvo que haya elegido
 *                          "entrar como" una empresa concreta.
 *   - Usuario de empresa → la de su sesión (web) o la de la cabecera
 *                          X-Company-Id (API), o la única que tenga.
 *
 * Ver ARQUITECTURA §4.1.
 */
class SetTenant
{
    use RespondsToApi;

    /** Cabecera con la que la app móvil dice en qué barbería está trabajando. */
    public const COMPANY_HEADER = 'X-Company-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(Tenancy::class);
        $user = $this->resolveUser($request);

        if (! $user) {
            $tenancy->set(null);

            return $next($request);
        }

        if ($user->is_super_admin) {
            $this->applySuperAdmin($request, $tenancy, $user);
        } else {
            $companyId = $this->resolveCompanyId($request, $user);

            if ($companyId === false) {
                // Pidió una empresa que no es suya. Se corta aquí en vez de
                // dejarlo pasar sin tenant: así el cliente recibe un motivo.
                return $this->wantsApiResponse($request)
                    ? $this->apiError(
                        'company_forbidden',
                        'No perteneces a esa empresa.',
                        403,
                    )
                    : redirect()->route('select-company')
                        ->with('error', 'No tienes acceso a esa empresa.');
            }

            if ($companyId && ! $this->wantsApiResponse($request)) {
                session(['current_company_id' => $companyId]);
            }

            $tenancy->set($companyId ?: null);
        }

        $company = $tenancy->company();

        // Los controladores y las vistas leen de aquí, sin repetir la consulta.
        $request->attributes->set('tenant_company', $company);
        view()->share('tenantCompany', $company);
        view()->share('globalMode', $tenancy->isUnrestricted());

        return $next($request);
    }

    /**
     * El operador del SaaS no está atado a ninguna empresa: sin elegir una,
     * trabaja en Modo Global.
     */
    protected function applySuperAdmin(Request $request, Tenancy $tenancy, User $user): void
    {
        $chosen = $this->wantsApiResponse($request)
            ? $request->header(self::COMPANY_HEADER)
            : session('current_company_id');

        if ($chosen) {
            $tenancy->set((int) $chosen);

            return;
        }

        $tenancy->unrestrict();
    }

    /**
     * Empresa activa de un usuario normal.
     *
     * @return int|null|false  id, null si no tiene ninguna, false si pidió una
     *                         que no le corresponde.
     */
    protected function resolveCompanyId(Request $request, User $user): int|null|false
    {
        if ($this->wantsApiResponse($request)) {
            $requested = $request->header(self::COMPANY_HEADER);

            if ($requested) {
                // CLAVE: la cabecera la manda el cliente, así que no se cree sin
                // comprobar. Sin esta verificación, cualquiera con un token
                // válido leería los datos de cualquier barbería del SaaS
                // poniendo otro número aquí.
                return $user->activeCompanies()->whereKey((int) $requested)->exists()
                    ? (int) $requested
                    : false;
            }

            // Sin cabecera solo vale si no hay ambigüedad: una sola empresa.
            $companies = $user->activeCompanies()->pluck('companies.id');

            return $companies->count() === 1 ? (int) $companies->first() : null;
        }

        $companyId = session('current_company_id')
            ?? $user->activeCompanies()->value('companies.id');

        return $companyId ? (int) $companyId : null;
    }
}
