<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comprobante de lo que se le pagó a un barbero por un periodo.
 */
class CommissionSettlement extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'personal_id', 'number', 'period_start', 'period_end',
        'entries_count', 'commissions_total', 'tips_total', 'total',
        'paid_at', 'paid_by', 'cash_session_id', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'entries_count' => 'integer',
        'commissions_total' => 'decimal:2',
        'tips_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'commission_settlement_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function periodLabel(): string
    {
        return $this->period_start->translatedFormat('d/m/Y')
            .' — '.$this->period_end->translatedFormat('d/m/Y');
    }

    /**
     * Siguiente número libre de la empresa: LIQ-0001, LIQ-0002…
     *
     * Con allCompanies(): al liquidar desde el Modo Global del superadmin no
     * hay empresa activa y el scope dejaría el contador en cero.
     */
    public static function nextNumberFor(int $companyId): string
    {
        $last = static::allCompanies()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'LIQ-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
