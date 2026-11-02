<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasBranchNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una compra de mercadería a un proveedor. Al registrarla, lo comprado entra
 * al almacén. Se paga al contado, en parte o a crédito: lo que falta es la
 * deuda con el proveedor (`total - paid`), que se salda con pagos posteriores.
 */
class Purchase extends Model
{
    use BelongsToCompany, HasBranchNumber;

    /** Correlativo de sucursal: P-000001… */
    public const NUMBER_TYPE = 'P';

    public const STATUSES = [
        'pendiente' => 'Por pagar',
        'parcial' => 'Pago parcial',
        'pagada' => 'Pagada',
        'anulada' => 'Anulada',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'number', 'supplier_id', 'warehouse_id', 'invoice_number',
        'purchased_on', 'subtotal', 'discount', 'total', 'paid', 'status', 'due_on', 'notes',
        'cancelled_at', 'cancelled_by', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'purchased_on' => 'date',
        'due_on' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    /** La sucursal es la de su almacén (si el almacén es de una). */
    protected function resolveNumberBranch(): void
    {
        $this->branch_id ??= Warehouse::withoutGlobalScopes()->whereKey($this->warehouse_id)->value('branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lo que se debe todavía. */
    public function balance(): float
    {
        return $this->isCancelled() ? 0.0 : round((float) $this->total - (float) $this->paid, 2);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'anulada';
    }

    /** Estado según lo pagado; una anulada no se toca. */
    public function refreshStatus(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->status = match (true) {
            $this->balance() <= 0.004 => 'pagada',
            (float) $this->paid > 0 => 'parcial',
            default => 'pendiente',
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pagada' => 'success',
            'parcial' => 'warning',
            'anulada' => 'secondary',
            default => 'danger',
        };
    }
}
