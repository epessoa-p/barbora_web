<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsToApi;
use App\Support\SubscriptionGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primera de las cuatro capas de autorización: ¿la suscripción de la empresa
 * permite siquiera entrar? Corre antes que plan y permiso.
 *
 * Ver ARQUITECTURA §7.3.
 */
class EnsureSubscriptionActive
{
    use RespondsToApi;

    /**
     * Rutas que nunca se bloquean: si no, un usuario con la suscripción
     * vencida quedaría atrapado sin poder ni salir ni cambiar de empresa.
     *
     * En la API vale lo mismo: hay que poder autenticarse, cerrar sesión y
     * preguntar quién soy aunque la barbería deba dinero, o la app no tendría
     * forma de explicar por qué no entra.
     */
    protected array $except = [
        'login',
        'login.store',
        'logout',
        'select-company',
        'set-company',
        'exit-company',
        'subscription.blocked',
        'api.login',
        'api.logout',
        'api.me',
        'api.companies',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Igual que SetTenant: este middleware corre antes que auth:sanctum,
        // así que el usuario hay que pedírselo al guard correcto.
        $user = $this->resolveUser($request);

        if (! $user) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), $this->except, true)) {
            return $next($request);
        }

        $company = $request->attributes->get('tenant_company');

        $decision = SubscriptionGate::decide($user, $company, $request);

        if ($decision === SubscriptionGate::BLOCK) {
            return $this->wantsApiResponse($request)
                ? $this->apiError(
                    'subscription_blocked',
                    'La suscripción de la barbería está vencida. Habla con el administrador.',
                    402,
                    ['subscription' => $this->subscriptionSummary($company)],
                )
                : redirect()->route('subscription.blocked');
        }

        if ($decision === SubscriptionGate::READONLY) {
            $message = 'Tu suscripción venció. Puedes consultar la información, '
                     . 'pero no registrar cambios hasta renovarla.';

            return $this->wantsApiResponse($request)
                ? $this->apiError(
                    'subscription_readonly',
                    $message,
                    402,
                    ['subscription' => $this->subscriptionSummary($company)],
                )
                : back()->withInput()->withErrors(['error' => $message]);
        }

        return $next($request);
    }

    /** Lo justo para que la app pueda explicar la situación sin otra llamada. */
    protected function subscriptionSummary($company): ?array
    {
        $subscription = $company?->subscription;

        if (! $subscription) {
            return null;
        }

        return [
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'grace_ends_at' => $subscription->graceEndsAt()?->toIso8601String(),
        ];
    }
}
