<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Observers\BranchObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Sucursal: local físico de una empresa. Recurso limitado por plan. */
#[ObservedBy(BranchObserver::class)]
class Branch extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'manager_name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    /** Almacén espejo, creado y mantenido por esta sucursal (BranchObserver). */
    public function warehouse(): HasOne
    {
        return $this->hasOne(Warehouse::class);
    }
}