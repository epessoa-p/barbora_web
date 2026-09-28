<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Cómo cobra la barbería: efectivo, tarjeta, Tigo Money, el QR de su banco…
 *
 * En las ventas y los movimientos de caja se guarda el SLUG, no el id, para que
 * el historial sobreviva a que se renombre o se dé de baja un método.
 */
class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    /**
     * Los que se crean con cada barbería nueva. Son los mismos cuatro que había
     * cuando esto era un enum, así que los datos anteriores siguen cuadrando.
     *
     * @var array<int, array{slug: string, name: string, counts_as_cash: bool, requires_reference: bool}>
     */
    public const DEFAULTS = [
        ['slug' => 'efectivo', 'name' => 'Efectivo', 'counts_as_cash' => true, 'requires_reference' => false],
        ['slug' => 'tarjeta', 'name' => 'Tarjeta', 'counts_as_cash' => false, 'requires_reference' => false],
        ['slug' => 'transferencia', 'name' => 'Transferencia', 'counts_as_cash' => false, 'requires_reference' => true],
        ['slug' => 'qr', 'name' => 'QR', 'counts_as_cash' => false, 'requires_reference' => true],
    ];

    protected $fillable = [
        'company_id', 'name', 'slug', 'counts_as_cash',
        'requires_reference', 'active', 'sort_order',
    ];

    protected $casts = [
        'counts_as_cash' => 'boolean',
        'requires_reference' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Los que cuentan en el arqueo: lo que de verdad está en el cajón. */
    public function scopeCash(Builder $query): Builder
    {
        return $query->where('counts_as_cash', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Los métodos de la empresa activa, como [slug => nombre].
     * Es lo que pintan los desplegables.
     */
    public static function options(): array
    {
        return static::active()->ordered()->pluck('name', 'slug')->all();
    }

    /**
     * Slugs que cuentan como efectivo. Sale de aquí y no de un literal para que
     * añadir «Tigo Money» no lo meta por error en el arqueo.
     *
     * @return array<int, string>
     */
    public static function cashSlugs(): array
    {
        return static::cash()->pluck('slug')->all();
    }

    /** Nombre legible de un slug, incluso si el método ya se dio de baja. */
    public static function labelFor(string $slug): string
    {
        $method = static::withTrashed()->where('slug', $slug)->first();

        return $method?->name ?? Str::headline($slug);
    }

    public static function generateSlug(string $name, ?int $companyId, ?int $ignoreId = null): string
    {
        $base = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $base = $base !== '' ? $base : 'metodo';

        $slug = $base;
        $counter = 1;

        while (static::withTrashed()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()) {
            $slug = $base.'_'.$counter;
            $counter++;
        }

        return $slug;
    }

    /** ¿Se usó alguna vez? Si sí, no se borra: se da de baja. */
    public function isInUse(): bool
    {
        return SalePayment::where('payment_method', $this->slug)->exists()
            || CashMovement::where('payment_method', $this->slug)->exists();
    }
}
