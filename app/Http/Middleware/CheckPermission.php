<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsToApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tercera capa: ¿el usuario tiene el permiso dentro de la empresa activa?
 *
 * Acepta varios permisos con OR — `check-permission:cargos.create,cargos.edit`
 * pasa si tiene alguno.
 */
class CheckPermission
{
    use RespondsToApi;

    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! auth()->check()) {
            return $this->wantsApiResponse($request)
                ? $this->apiError('unauthenticated', 'Tu sesión expiró. Vuelve a iniciar sesión.', 401)
                : redirect('login');
        }

        $user = auth()->user();

        // El operador de la plataforma pasa siempre.
        if ($user->is_super_admin) {
            return $next($request);
        }

        // La empresa activa ya la resolvió SetTenant; no se vuelve a consultar.
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

        foreach ($permissions as $permission) {
            if ($user->hasPermissionInCompany($permission, $company)) {
                return $next($request);
            }
        }

        return $this->wantsApiResponse($request)
            ? $this->apiError(
                'forbidden',
                'No tienes permiso para hacer esto.',
                403,
                ['required_permissions' => array_values($permissions)],
            )
            : response()->view('errors.403', [], 403);
    }
}
