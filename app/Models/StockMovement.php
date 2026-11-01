<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasBranchNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento de inventario. Inmutable por diseño: los errores se corrigen
 * con un ajuste, nunca editando el historial.
 */
class StockMovement extends Model
{
    use HasFactory, BelongsToCompany, HasBranchNumber;

    public const TYPES = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
    ];

    /** Motivos habituales, para no depender de texto libre en los informes. */
    public const REASONS = [
        'compra' => 'Compra a proveedor',
        'devolucion' => 'Devolución de cliente',
        'consumo' => 'Consumo en servicio',
        'venta' => 'Venta',
        'merma' => 'Merma o rotura',
        'inventario' => 'Recuento físico',
        'traslado' => 'Traslado entre almacenes',
        'otro' => 'Otro',
    ];

    /** Correlativo de sucursal: I-000001… (HasBranchNumber). */
    public const NUMBER_TYPE = 'I';

    protected $fillable = [
        'company_id', 'warehouse_id', 'branch_id', 'number', 'product_id', 'type',
        'quantity', 'quantity_after', 'reason', 'unit_cost',
        'reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** La de su almacén; NULL si es un almacén central. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Siempre la del almacén: el movimiento numera en la sucursal donde está
     * la mercancía. Un almacén central numera aparte (sin sucursal).
     */
    protected function resolveNumberBranch(): void
    {
        $this->branch_id = Warehouse::withoutGlobalScopes()->whereKey($this->warehouse_id)->value('branch_id');
    }

    public function isIncoming(): bool
    {
        return (float) $this->quantity > 0;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function reasonLabel(): ?string
    {
        return $this->reason ? (self::REASONS[$this->reason] ?? $this->reason) : null;
    }

    public function typeColor(): string
    {
        return match ($this->type) {
            'entrada' => 'success',
            'salida' => 'danger',
            default => 'secondary',
        };
    }
}
