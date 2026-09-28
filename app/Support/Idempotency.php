<?php

namespace App\Support;

use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hace que repetir una petición de cobro no cobre dos veces.
 *
 * Cómo se usa:
 *
 *     return app(Idempotency::class)->run($request, 'sales.store', function () {
 *         // …registrar la venta…
 *         return [$cuerpo, 201];
 *     });
 *
 * Qué pasa según el caso:
 *
 *   · Sin cabecera «Idempotency-Key» → se ejecuta y ya está. La API no obliga,
 *     para que un cliente sencillo (o curl) funcione sin ceremonia.
 *   · Clave nueva → se ejecuta y se guarda la respuesta.
 *   · Clave repetida con el MISMO cuerpo → se devuelve lo guardado, sin volver
 *     a cobrar. Es el caso del reintento tras perder la red.
 *   · Clave repetida con OTRO cuerpo → 422. Reutilizar una clave para un cobro
 *     distinto es un fallo del cliente, y devolver la venta anterior en
 *     silencio haría creer que se cobró algo que no se cobró.
 */
class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    /**
     * @param  callable():array{0: array<string, mixed>, 1: int}  $operation
     */
    public function run(Request $request, string $endpoint, callable $operation): JsonResponse
    {
        $key = trim((string) $request->header(self::HEADER));

        if ($key === '') {
            [$body, $status] = $operation();

            return $this->json($body, $status);
        }

        $companyId = $request->attributes->get('tenant_company')?->id;
        $hash = $this->hashOf($request);

        $existing = IdempotencyKey::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            return $this->replay($existing, $endpoint, $hash);
        }

        [$body, $status] = $operation();

        try {
            IdempotencyKey::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'user_id' => $request->user()?->id,
                'key' => $key,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'response_status' => $status,
                // Se codifica aquí, con el flag de los decimales, y se guarda
                // ya como cadena. Dejar que Laravel lo hiciera convertiría
                // 50.0 en 50 y el reintento devolvería un entero.
                'response_body' => $this->encode($body),
            ]);
        } catch (QueryException) {
            // Dos peticiones idénticas a la vez: la otra ganó la carrera y ya
            // guardó la clave. La venta de ésta ya se registró, así que no se
            // puede deshacer aquí; lo que sí se evita es romper la respuesta.
            // El índice único es la barrera real contra el duplicado.
        }

        return $this->json($body, $status);
    }

    protected function replay(IdempotencyKey $existing, string $endpoint, string $hash): JsonResponse
    {
        if ($existing->endpoint !== $endpoint || $existing->request_hash !== $hash) {
            return $this->json([
                'error' => 'idempotency_key_reused',
                'message' => 'Esa clave ya se usó para otra operación. '
                    . 'Genera una nueva para cada cobro.',
            ], 422);
        }

        // Se devuelve el JSON guardado TAL CUAL, sin decodificar y volver a
        // codificar: ese viaje de ida y vuelta es justo lo que convertiría
        // 50.0 en 50. El flag se inserta como texto, al principio del objeto.
        return $this->rawJson(
            $this->withReplayFlag($existing->response_body),
            $existing->response_status,
        );
    }

    /** Mete «idempotent_replay» en el JSON sin tocar el resto. */
    protected function withReplayFlag(?string $body): string
    {
        $flag = '"idempotent_replay":true';

        $body = trim((string) $body);

        // Cuerpo vacío o sin contenido: basta con el flag.
        if ($body === '' || $body === '{}' || ! str_starts_with($body, '{')) {
            return '{'.$flag.'}';
        }

        return '{'.$flag.','.substr($body, 1);
    }

    /** Huella del cuerpo, con las claves ordenadas para que el orden no cuente. */
    protected function hashOf(Request $request): string
    {
        $payload = $request->all();
        $this->sortDeep($payload);

        return hash('sha256', json_encode($payload));
    }

    protected function sortDeep(array &$data): void
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->sortDeep($value);
            }
        }
    }

    protected function json(array $body, int $status): JsonResponse
    {
        return $this->rawJson($this->encode($body), $status);
    }

    /**
     * El mismo flag que el resto de la API: un importe redondo tiene que
     * llegar como 30.0, no como 30. Ver Api\V1\ApiController.
     */
    protected function encode(array $body): string
    {
        return json_encode($body, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
    }

    protected function rawJson(string $json, int $status): JsonResponse
    {
        return JsonResponse::fromJsonString($json, $status);
    }
}
