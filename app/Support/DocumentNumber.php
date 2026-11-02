<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Correlativos por sucursal: V-000001, V-000002… de cada sucursal.
 *
 * El último número usado vive en document_sequences, una fila por empresa,
 * sucursal y tipo. Se bloquea la fila (lockForUpdate) hasta que termina la
 * transacción de quien numera: dos cobros simultáneos en la misma sucursal
 * esperan su turno y nunca se llevan el mismo número.
 */
class DocumentNumber
{
    public const TYPES = [
        'V' => 'Venta',
        'C' => 'Cita',
        'T' => 'Turno de caja',
        'I' => 'Movimiento de inventario',
        'G' => 'Gasto',
        'P' => 'Compra',
    ];

    /**
     * @param  Closure(): int  $seed  último número ya usado, para cuando la
     *                                sucursal aún no tiene fila (datos previos)
     */
    public static function next(int $companyId, ?int $branchId, string $type, ?Closure $seed = null): string
    {
        return DB::transaction(function () use ($companyId, $branchId, $type, $seed) {
            $key = ['company_id' => $companyId, 'branch_id' => $branchId ?? 0, 'type' => $type];

            if (! DB::table('document_sequences')->where($key)->exists()) {
                DB::table('document_sequences')->insertOrIgnore($key + [
                    'last_number' => $seed ? $seed() : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $last = (int) DB::table('document_sequences')->where($key)->lockForUpdate()->value('last_number');

            DB::table('document_sequences')->where($key)->update([
                'last_number' => $last + 1,
                'updated_at' => now(),
            ]);

            return static::format($type, $last + 1);
        });
    }

    public static function format(string $type, int $sequence): string
    {
        return $type.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /** El número de «V-000123» (123), o 0 si no tiene ese formato. */
    public static function sequenceOf(?string $number): int
    {
        return preg_match('/^[A-Z]+-(\d+)$/', (string) $number, $m) ? (int) $m[1] : 0;
    }
}
