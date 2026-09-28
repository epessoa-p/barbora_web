<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_id', 'type', 'service_id', 'product_id', 'personal_id',
        'description', 'quantity', 'unit_price', 'unit_cost', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('type', 'producto');
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('type', 'servicio');
    }

    public function isProduct(): bool
    {
        return $this->type === 'producto';
    }

    /** ¿Se guardó el costo de esta línea, o hay que estimarlo? */
    public function hasFrozenCost(): bool
    {
        return $this->unit_cost !== null;
    }

    /**
     * Lo que costó lo vendido en esta línea, con el precio de compra del día.
     * Null cuando no se sabe: un servicio, o una venta anterior a que se
     * empezara a guardar el costo.
     */
    public function cost(): ?float
    {
        return $this->unit_cost === null
            ? null
            : round((float) $this->unit_cost * (float) $this->quantity, 2);
    }
}
