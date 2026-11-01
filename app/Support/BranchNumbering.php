<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

/**
 * Pone en orden lo que ya estaba guardado antes de numerar por sucursal.
 *
 * Lo corre la migración 2026_11_01_000001 una sola vez. En producción hace lo
 * mismo database/sql/2026_11_01_numeracion_por_sucursal.sql: si cambias una
 * regla aquí, cámbiala también allí.
 *
 * Con DB::table a propósito: sin scopes de empresa ni eventos de modelo.
 */
class BranchNumbering
{
    public const NUMBERED = [
        'sales' => 'V',
        'appointments' => 'C',
        'cash_sessions' => 'T',
        'stock_movements' => 'I',
    ];

    public static function backfill(): void
    {
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            static::backfillCompany((int) $companyId);
        }
    }

    protected static function backfillCompany(int $companyId): void
    {
        // 1. Sucursales sin código: S01, S02…
        DB::table('branches')->where('company_id', $companyId)
            ->where(fn ($q) => $q->whereNull('code')->orWhere('code', ''))
            ->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('branches')->where('id', $id)
                ->update(['code' => Branch::nextCodeFor($companyId)]));

        // La sucursal a la que va lo que no tiene ninguna: la primera.
        $default = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at')
            ->orderByDesc('active')->orderBy('id')->value('id');

        // 2. Personal: la sucursal donde más horarios tiene; si no, la primera.
        DB::table('personal')->where('company_id', $companyId)->whereNull('branch_id')->pluck('id')
            ->each(function ($id) use ($default) {
                $branch = DB::table('work_schedules')->where('personal_id', $id)->whereNotNull('branch_id')
                    ->select('branch_id', DB::raw('COUNT(*) AS total'))
                    ->groupBy('branch_id')->orderByDesc('total')->orderBy('branch_id')
                    ->value('branch_id');

                DB::table('personal')->where('id', $id)->update(['branch_id' => $branch ?? $default]);
            });

        $personalBranch = DB::table('personal')->where('company_id', $companyId)->pluck('branch_id', 'id');

        // 3. Cajas: la de su responsable; si no, la primera.
        DB::table('cajas')->where('company_id', $companyId)->whereNull('branch_id')->get(['id', 'personal_id'])
            ->each(fn ($caja) => DB::table('cajas')->where('id', $caja->id)->update([
                'branch_id' => $personalBranch[$caja->personal_id] ?? $default,
            ]));

        $cajaBranch = DB::table('cajas')->where('company_id', $companyId)->pluck('branch_id', 'id');

        // 4. Citas: la del barbero.
        DB::table('appointments')->where('company_id', $companyId)->whereNull('branch_id')->get(['id', 'personal_id'])
            ->each(fn ($a) => DB::table('appointments')->where('id', $a->id)->update([
                'branch_id' => $personalBranch[$a->personal_id] ?? $default,
            ]));

        // 5. Turnos: la de su caja.
        DB::table('cash_sessions')->where('company_id', $companyId)->get(['id', 'caja_id'])
            ->each(fn ($s) => DB::table('cash_sessions')->where('id', $s->id)->update([
                'branch_id' => $cajaBranch[$s->caja_id] ?? $default,
            ]));

        // 6. Ventas: la de su turno de caja, su cita o su barbero.
        $sessionBranch = DB::table('cash_sessions')->where('company_id', $companyId)->pluck('branch_id', 'id');
        $appointmentBranch = DB::table('appointments')->where('company_id', $companyId)->pluck('branch_id', 'id');

        DB::table('sales')->where('company_id', $companyId)->whereNull('branch_id')
            ->get(['id', 'cash_session_id', 'appointment_id', 'personal_id'])
            ->each(fn ($sale) => DB::table('sales')->where('id', $sale->id)->update([
                'branch_id' => $sessionBranch[$sale->cash_session_id]
                    ?? $appointmentBranch[$sale->appointment_id]
                    ?? $personalBranch[$sale->personal_id]
                    ?? $default,
            ]));

        // 7. Movimientos de inventario: la de su almacén (NULL si es central).
        $warehouseBranch = DB::table('warehouses')->where('company_id', $companyId)->pluck('branch_id', 'id');

        DB::table('stock_movements')->where('company_id', $companyId)->get(['id', 'warehouse_id'])
            ->each(fn ($m) => DB::table('stock_movements')->where('id', $m->id)->update([
                'branch_id' => $warehouseBranch[$m->warehouse_id] ?? null,
            ]));

        // 8. Renumerar cada sucursal en orden de alta y dejar el contador al día.
        foreach (static::NUMBERED as $table => $type) {
            $counters = [];
            $renamed = [];

            DB::table($table)->where('company_id', $companyId)->orderBy('id')->get(['id', 'branch_id', 'number'])
                ->each(function ($row) use ($table, $type, &$counters, &$renamed) {
                    $branch = (int) ($row->branch_id ?? 0);
                    $counters[$branch] = ($counters[$branch] ?? 0) + 1;
                    $number = DocumentNumber::format($type, $counters[$branch]);

                    if ($row->number && $row->number !== $number) {
                        $renamed[$row->number] = $number;
                    }

                    DB::table($table)->where('id', $row->id)->update(['number' => $number]);
                });

            if ($table === 'sales' && $renamed) {
                static::renameSaleReferences($companyId, $renamed);
            }

            foreach ($counters as $branch => $last) {
                DB::table('document_sequences')->updateOrInsert(
                    ['company_id' => $companyId, 'branch_id' => $branch, 'type' => $type],
                    ['last_number' => $last, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    /**
     * Los movimientos de caja e inventario citan la venta por su número
     * («Venta V-000012», «Anulación V-000012»): se cambian al nuevo.
     *
     * Se calcula todo sobre los valores leídos y se escribe por id, así un
     * número nuevo que coincide con uno viejo no se renombra dos veces.
     *
     * @param  array<string, string>  $renamed  número viejo → nuevo
     */
    protected static function renameSaleReferences(int $companyId, array $renamed): void
    {
        $swap = fn (?string $text) => $text === null ? null : strtr($text, $renamed);

        DB::table('cash_movements')->where('company_id', $companyId)->whereIn('reference', array_keys($renamed))
            ->get(['id', 'reference', 'concept'])
            ->each(fn ($m) => DB::table('cash_movements')->where('id', $m->id)->update([
                'reference' => $renamed[$m->reference],
                'concept' => $swap($m->concept),
            ]));

        $references = array_merge(
            array_keys($renamed),
            array_map(fn ($old) => "Anulación {$old}", array_keys($renamed)),
        );

        DB::table('stock_movements')->where('company_id', $companyId)->whereIn('reference', $references)
            ->get(['id', 'reference'])
            ->each(fn ($m) => DB::table('stock_movements')->where('id', $m->id)->update([
                'reference' => $swap($m->reference),
            ]));
    }
}
