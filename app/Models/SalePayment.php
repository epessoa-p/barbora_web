<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'sale_id', 'payment_method', 'amount', 'reference'];

    protected $casts = ['amount' => 'decimal:2'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Los métodos que ofrece la empresa, como [slug => nombre].
     *
     * Antes era una constante con cuatro valores fijos. Ahora cada barbería
     * define los suyos (ver PaymentMethod), así que hay que preguntarlo.
     */
    public static function methods(): array
    {
        return PaymentMethod::options();
    }

    public function methodLabel(): string
    {
        return PaymentMethod::labelFor($this->payment_method);
    }
}
