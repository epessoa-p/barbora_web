<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una línea de una compra. Guarda el nombre: el producto puede cambiar después. */
class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'total', 'stock_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
