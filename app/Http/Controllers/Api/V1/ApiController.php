<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base de los controladores de la API.
 *
 * Existe por una sola razón, pero importante: JSON_PRESERVE_ZERO_FRACTION.
 *
 * Sin ese flag, PHP serializa 30.0 como «30» y 30.50 como «30.5». Un cliente
 * Dart que haga «json['amount'] as double» funciona con 30.5 y revienta con 30,
 * así que el fallo solo aparece cuando el importe es redondo: pasa las pruebas
 * y se rompe en producción el día que alguien cobra Bs 30 exactos.
 *
 * Con el flag, un decimal siempre se serializa como decimal.
 */
abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
