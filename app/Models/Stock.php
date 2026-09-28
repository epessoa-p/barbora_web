<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Existencias de un producto en un almacén concreto. */
class Stock extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'warehouse_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLow(): bool
    {
        return $this->product
            && $this->product->track_stock
            && (float) $this->quantity <= (float) $this->product->min_stock;
    }
}
