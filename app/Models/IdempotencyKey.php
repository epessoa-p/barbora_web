<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de una petición ya procesada, para no repetirla.
 *
 * Ver App\Support\Idempotency: existe para que un reintento del móvil tras
 * perder la red no acabe cobrando dos veces al mismo cliente.
 *
 * OJO con response_body: se guarda como CADENA, sin cast a array. Si se casteara,
 * Laravel lo re-codificaría sin JSON_PRESERVE_ZERO_FRACTION y un importe de
 * 50.0 volvería como 50; el cliente Dart que lo lee con «as double» reventaría
 * justo en el reintento, que es el peor momento posible.
 */
class IdempotencyKey extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'key', 'endpoint',
        'request_hash', 'response_status', 'response_body',
    ];

    protected $casts = [
        'response_status' => 'integer',
    ];
}
