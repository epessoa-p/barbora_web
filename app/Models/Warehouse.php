<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Almacén de una empresa.
 *
 * Puede ser independiente (creado a mano) o estar ligado a una sucursal: en ese
 * caso lo creó y lo mantiene la propia sucursal, y su CRUD es de solo lectura.
 */
class Warehouse extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'phone',
        'address',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** ¿Lo gestiona una sucursal? Entonces no se edita desde su propio CRUD. */
    public function isManagedByBranch(): bool
    {
        return $this->branch_id !== null;
    }

    /**
     * Siguiente código libre de la empresa: ALM-0001, ALM-0002…
     *
     * Usa allCompanies() a propósito: al crear una sucursal desde el Modo
     * Global del superadmin no hay empresa activa y el scope dejaría el
     * contador en cero, repitiendo códigos.
     */
    public static function nextCodeFor(int $companyId): string
    {
        $used = static::allCompanies()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->pluck('code')
            ->all();

        $sequence = count($used) + 1;

        do {
            $code = 'ALM-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (in_array($code, $used, true));

        return $code;
    }
}
