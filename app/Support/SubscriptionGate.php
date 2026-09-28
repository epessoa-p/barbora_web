<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Decide qué puede hacer un usuario según el estado de la suscripción de su
 * empresa. Lógica compartida por el middleware web y, en el futuro, por el
 * de la API. Ver ARQUITECTURA §7.3.
 */
class SubscriptionGate
{
    public const ALLOW = 'allow';
    public const BLOCK = 'block';
    public const READONLY = 'readonly';

    /**
     * @return self::ALLOW|self::BLOCK|self::READONLY
     */
    public static function decide(?User $user, ?Company $company, Request $request): string
    {
        // El operador de la plataforma no está sujeto a ninguna suscripción.
        if ($user?->is_super_admin) {
            return self::ALLOW;
        }

        if (! config('barbora.require_subscription')) {
            return self::ALLOW;
        }

        if (! $company) {
            return self::ALLOW;   // sin empresa activa no hay nada que cobrar
        }

        $subscription = $company->subscription;

        // Empresa sin suscripción: el operador todavía no se la ha asignado.
        if (! $subscription) {
            return self::BLOCK;
        }

        if (! $subscription->allowsRead()) {
            return self::BLOCK;
        }

        if ($subscription->inGrace() && static::isWrite($request)) {
            return self::READONLY;
        }

        return self::ALLOW;
    }

    public static function isWrite(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
