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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
