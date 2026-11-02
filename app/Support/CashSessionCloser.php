<?php

namespace App\Support;

use App\Models\CashSession;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de un turno de caja: el mismo para la web y para la API.
 *
 * Solo se arquea el efectivo (los métodos marcados «cuenta como efectivo"):
 * un cobro por QR o tarjeta está en el banco, no en el cajón.
 *
 * Dos formas de contar, según la barbería (companies.cash_count_mode):
 *   total          — se escribe el total contado (como siempre);
 *   denominations  — se cuentan billetes y monedas y el total lo calcula el
 *                    servidor; el conteo queda guardado en el turno.
 */
class CashSessionCloser
{
    public const MODES = [
        'total' => 'Solo el total contado',
        'denominations' => 'Contar billetes y monedas',
    ];

    /**
     * Billetes y monedas de la moneda de la empresa, de mayor a menor.
     *
     * @return array{bills: array<int, float>, coins: array<int, float>}
     */
    public static function denominations(Company $company): array
    {
        $code = $company->currency ?: config('barbora.default_currency');
        $config = config("barbora.currencies.{$code}", []);

        return [
            'bills' => array_map('floatval', $config['bills'] ?? []),
            'coins' => array_map('floatval', $config['coins'] ?? []),
        ];
    }

    /** Clave estable de una denominación: «200», «0.50». */
    public static function key(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    public static function countsDenominations(Company $company): bool
    {
        return $company->cash_count_mode === 'denominations'
            && self::denominations($company)['bills'] !== [];
    }

    /**
     * Valida, calcula el contado y cierra. Devuelve el turno ya cerrado.
     */
    public function close(Request $request, CashSession $session, Company $company): CashSession
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['error' => 'Este turno ya está cerrado.']);
        }

        $byDenomination = self::countsDenominations($company);

        $data = $request->validate([
            'closing_amount' => [$byDenomination ? 'nullable' : 'required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
            'denominations' => [$byDenomination ? 'present' : 'nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'closing_amount.required' => 'Indica cuánto efectivo contaste en el cajón.',
            'denominations.present' => 'Cuenta los billetes y monedas del cajón.',
        ]);

        [$counted, $detail] = $byDenomination
            ? $this->sumDenominations($company, $data['denominations'] ?? [])
            : [round((float) $data['closing_amount'], 2), null];

        $session->load('movements');

        // El esperado se guarda, no se recalcula al mirarlo después: es la foto
        // del momento del arqueo.
        $expected = $session->totals()['expected_cash'];

        DB::transaction(function () use ($session, $data, $expected, $counted, $detail, $request) {
            $session->update([
                'status' => 'cerrada',
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
                'closing_amount' => $counted,
                'closing_denominations' => $detail,
                'expected_amount' => $expected,
                'difference' => round($counted - $expected, 2),
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);

            // El saldo de la caja pasa a ser lo que de verdad quedó contado.
            $session->caja?->update(['balance' => $counted]);
        });

        return $session->fresh();
    }

    /**
     * Suma lo contado. Solo valen las denominaciones de la moneda: una clave
     * desconocida se ignora, y el total lo calcula siempre el servidor.
     *
     * @param  array<string, mixed>  $counts  [«200» => 3, «0.50» => 4, …]
     * @return array{0: float, 1: array<string, int>}
     */
    protected function sumDenominations(Company $company, array $counts): array
    {
        $all = self::denominations($company);
        $detail = [];
        $total = 0.0;

        foreach (array_merge($all['bills'], $all['coins']) as $value) {
            $key = self::key($value);
            $qty = (int) ($counts[$key] ?? 0);

            if ($qty > 0) {
                $detail[$key] = ($detail[$key] ?? 0) + $qty;
                $total += $value * $qty;
            }
        }

        return [round($total, 2), $detail];
    }
}
