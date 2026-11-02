<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Un proveedor: a quien se le compra mercadería o se le paga un gasto. */
class Supplier extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'tax_id', 'contact_name', 'phone', 'email', 'address', 'notes', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Lo que se le debe: compras sin anular, total menos pagado. */
    public function debt(): float
    {
        return round((float) $this->purchases()
            ->where('status', '!=', 'anulada')
            ->selectRaw('COALESCE(SUM(total - paid), 0) as debt')
            ->value('debt'), 2);
    }
}
