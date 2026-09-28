<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    /** Unidades de medida habituales en una barbería. */
    public const UNITS = [
        'unidad' => 'Unidad',
        'ml' => 'Mililitros',
        'gr' => 'Gramos',
        'par' => 'Par',
        'caja' => 'Caja',
    ];

    protected $fillable = [
        'company_id', 'product_category_id', 'name', 'sku', 'description', 'unit',
        'cost_price', 'sale_price', 'is_sellable', 'track_stock', 'min_stock', 'active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'is_sellable' => 'boolean',
        'track_stock' => 'boolean',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true)->where('active', true);
    }

    public function scopeTracked(Builder $query): Builder
    {
        return $query->where('track_stock', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
        });
    }

    /** Existencias sumadas de todos los almacenes. */
    public function totalStock(): float
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : $this->stocks()->get();

        return (float) $stocks->sum('quantity');
    }

    public function stockIn(Warehouse|int $warehouse): float
    {
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : $this->stocks()->get();

        return (float) ($stocks->firstWhere('warehouse_id', $warehouseId)?->quantity ?? 0);
    }

    /** Por debajo del umbral: es lo que dispara el aviso de reposición. */
    public function isLowStock(): bool
    {
        return $this->track_stock && $this->totalStock() <= (float) $this->min_stock;
    }

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit] ?? $this->unit;
    }

    /**
     * Cantidad con su unidad, lista para leer: «13 unidades», «1 caja», «40 ml».
     *
     * Los decimales sobrantes se recortan: nadie quiere leer «13,00 unidades».
     */
    public function formatQuantity(float $quantity): string
    {
        $number = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

        $plural = [
            'unidad' => 'unidades',
            'par' => 'pares',
            'caja' => 'cajas',
            'ml' => 'ml',
            'gr' => 'gr',
        ];

        $unit = abs($quantity) === 1.0
            ? $this->unit
            : ($plural[$this->unit] ?? $this->unit);

        return "{$number} {$unit}";
    }

    /** Margen sobre el precio de venta, en porcentaje. */
    public function marginPercent(): ?float
    {
        $sale = (float) $this->sale_price;

        if ($sale <= 0) {
            return null;
        }

        return round((($sale - (float) $this->cost_price) / $sale) * 100, 1);
    }
}
