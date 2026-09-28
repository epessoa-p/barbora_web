<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Pantalla que ve una empresa cuya suscripción ya no permite ni entrar.
 *
 * Usa el layout de autenticación a propósito: el layout de la aplicación
 * consulta plan y permisos, que aquí no están disponibles.
 */
class SubscriptionBlockedController extends Controller
{
    public function __invoke(Request $request)
    {
        $company = $request->attributes->get('tenant_company');

        return view('subscription.blocked', [
            'company' => $company,
            'subscription' => $company?->subscription,
        ]);
    }
}
