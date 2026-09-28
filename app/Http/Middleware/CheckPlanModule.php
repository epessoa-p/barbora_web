<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsToApi;
use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segunda capa: ¿el plan de la empresa incluye este módulo?
 *
 * Acepta varios módulos con OR — `plan:pos,caja` pasa si el plan incluye
 * alguno de los dos. Ver ARQUITECTURA §6.
 */
class CheckPlanModule
{
    use RespondsToApi;

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        if (! auth()->check()) {
            return $this->wantsApiResponse($request)
                ? $this->apiError('unauthenticated', 'Tu sesión expiró. Vuelve a iniciar sesión.', 401)
                : redirect()->route('login');
        }

        // El operador de la plataforma no está sujeto a ningún plan.
        if (auth()->user()->is_super_admin) {
            return $next($request);
        }

        $company = $request->attributes->get('tenant_company');

        if (! $company) {
            return $this->wantsApiResponse($request)
                ? $this->apiError(
                    'company_required',
                    'Indica en qué empresa trabajas con la cabecera '.SetTenant::COMPANY_HEADER.'.',
                    409,
                )
                : redirect()->route('select-company')
                    ->with('error', 'Selecciona una empresa para continuar.');
        }

        foreach ($modules as $module) {
            if ($company->planAllows($module)) {
                return $next($request);
            }
        }

        return $this->wantsApiResponse($request)
            ? $this->apiError(
                'plan_required',
                'El plan contratado no incluye esta función.',
                403,
                [
                    'required_modules' => array_values($modules),
                    'module_labels' => array_values(array_map(
                        fn (string $m) => Plan::MODULES[$m] ?? $m,
                        $modules,
                    )),
                ],
            )
            : response()->view('errors.plan', [
                'modules' => $modules,
                'company' => $company,
            ], 403);
    }
}
