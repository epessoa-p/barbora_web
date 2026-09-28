<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_id', 'sale_item_id', 'personal_id',
        'commission_rule_id', 'commission_settlement_id',
        'base_amount', 'rate_type', 'rate_value', 'amount', 'earned_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate_value' => 'decimal:2',
        'amount' => 'decimal:2',
        'earned_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }

    /** Devengadas y aún no pagadas. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('commission_settlement_id');
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereNotNull('commission_settlement_id');
    }

    public function isSettled(): bool
    {
        return $this->commission_settlement_id !== null;
    }

    public function rateLabel(): string
    {
        return $this->rate_type === 'porcentaje'
            ? rtrim(rtrim(number_format((float) $this->rate_value, 2), '0'), '.').' %'
            : 'fijo';
    }
}
