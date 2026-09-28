<?php

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las cuatro capas de autorización son las mismas para la web y para la API;
 * lo único que cambia es cómo se dice que no. En la web se redirige, en la API
 * se devuelve JSON con un código que el cliente pueda distinguir.
 *
 * Esta decisión vive aquí, en un único sitio, para que no existan dos juegos de
 * middleware que haya que mantener en paralelo.
 */
trait RespondsToApi
{
    /**
     * ¿Hay que contestar en JSON?
     *
     * Se mira la ruta ANTES que la cabecera Accept a propósito: un cliente móvil
     * que olvide mandar «Accept: application/json» debe recibir igualmente un
     * error JSON, no una redirección a una pantalla de login que no existe.
     */
    protected function wantsApiResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * El usuario de la petición.
     *
     * OJO con esto: los middleware de grupo (SetTenant, EnsureSubscriptionActive)
     * corren ANTES que el middleware de ruta `auth:sanctum`, así que en ese
     * momento el guard por defecto sigue siendo «web» y auth()->user() devuelve
     * null en la API aunque el token sea válido. Hay que preguntarle a Sanctum
     * de forma explícita.
     *
     * En los tests con Sanctum::actingAs() esto no se nota, porque actingAs
     * cambia el guard por defecto: por eso hace falta al menos una prueba que
     * use un token Bearer de verdad.
     */
    protected function resolveUser(Request $request): ?User
    {
        return $this->wantsApiResponse($request)
            ? auth('sanctum')->user()
            : auth()->user();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function apiError(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            // El código es estable y legible por máquina; el mensaje es para
            // enseñárselo a la persona y puede cambiar sin romper al cliente.
            'error' => $code,
            'message' => $message,
        ], $extra), $status);
    }
}
