<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de la app móvil
|--------------------------------------------------------------------------
|
| Pensada para el PERSONAL de la barbería (ver barbora_movil), no para
| clientes finales. Autenticación con Sanctum por token.
|
| La empresa activa viaja en la cabecera «X-Company-Id»: no hay sesión, así
| que cada petición dice en qué barbería trabaja. SetTenant comprueba que el
| usuario pertenezca a ella antes de aceptarla.
|
| Las cuatro capas de autorización son las mismas que en la web —suscripción,
| plan, permiso y límites— y corren en el mismo orden; lo único que cambia es
| que aquí contestan JSON en vez de redirigir (ver Concerns\RespondsToApi).
|
*/

Route::prefix('v1')->name('api.')->group(function () {
    require __DIR__.'/api/v1.php';
});
