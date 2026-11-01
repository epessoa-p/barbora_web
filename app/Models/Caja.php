<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'personal_id',
        'name',
        'code',
        'description',
        'balance',
        'active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Toda caja es de una sucursal: sus turnos se numeran ahí. Sin indicarla,
     * la de su responsable; si no, la primera de la empresa.
     */
    protected static function booted(): void
    {
        static::creating(function (Caja $caja) {
            $caja->branch_id ??= Personal::withoutGlobalScopes()->whereKey($caja->personal_id)->value('branch_id')
                ?? ($caja->company_id ? Branch::defaultIdFor((int) $caja->company_id) : null);
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** El personal al que está asignada esta caja; null = caja general. */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class)->latest('opened_at');
    }

    /** El turno abierto, si lo hay: solo puede haber uno por caja a la vez. */
    public function openSession(): ?CashSession
    {
        return $this->sessions()->open()->first();
    }

    public function isOpen(): bool
    {
        return $this->sessions()->open()->exists();
    }
}
