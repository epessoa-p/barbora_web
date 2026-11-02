<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El catálogo público de una barbería: la página sin sesión que se comparte
 * por enlace o por QR (en la puerta del local, en redes, por WhatsApp).
 *
 * Muestra los servicios activos por categoría y, si la barbería lo elige, los
 * productos a la venta. Nace apagado: publicar es decisión de la barbería. El
 * enlace lleva un token difícil de adivinar; regenerarlo invalida el anterior
 * (por ejemplo, si se imprimió un QR por error).
 */
class Catalog
{
    /** La barbería cuyo catálogo está publicado con ese token, o null. */
    public static function findPublished(string $token): ?Company
    {
        return Company::where('catalog_token', $token)
            ->where('catalog_enabled', true)
            ->where('active', true)
            ->first();
    }

    /** El token del enlace; se crea la primera vez que hace falta. */
    public static function token(Company $company): string
    {
        if (! $company->catalog_token) {
            $company->forceFill(['catalog_token' => self::newToken()])->save();
        }

        return $company->catalog_token;
    }

    /** Un enlace nuevo: el anterior (y su QR) deja de funcionar. */
    public static function regenerate(Company $company): string
    {
        $company->forceFill(['catalog_token' => self::newToken()])->save();

        return $company->catalog_token;
    }

    public static function url(Company $company): string
    {
        return route('catalog.public', self::token($company));
    }

    public static function posterUrl(Company $company): string
    {
        return route('catalog.poster', self::token($company));
    }

    /**
     * Los servicios activos, agrupados por categoría (en el orden de la
     * barbería; lo que no tiene categoría va al final, en «Otros»).
     *
     * @return Collection<string, Collection<int, Service>>
     */
    public static function services(Company $company): Collection
    {
        return app(Tenancy::class)->runFor($company->id, fn () => Service::with('category')
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get())
            ->sortBy(fn (Service $s) => [$s->category ? 0 : 1, $s->category?->sort_order ?? 0, $s->category?->name ?? ''])
            ->groupBy(fn (Service $s) => $s->category?->name ?? 'Otros');
    }

    /**
     * Los productos a la venta, por categoría. Vacío si la barbería no quiere
     * mostrarlos.
     *
     * @return Collection<string, Collection<int, Product>>
     */
    public static function products(Company $company): Collection
    {
        if (! $company->catalog_show_products) {
            return collect();
        }

        return app(Tenancy::class)->runFor($company->id, fn () => Product::with('category')
            ->sellable()
            ->orderBy('name')
            ->get())
            ->groupBy(fn (Product $p) => $p->category?->name ?? 'Otros');
    }

    protected static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (Company::where('catalog_token', $token)->exists());

        return $token;
    }
}
