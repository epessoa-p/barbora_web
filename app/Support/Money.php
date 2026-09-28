<?php

namespace App\Support;

use App\Models\Company;

/**
 * Formato de importes según la moneda de la empresa.
 *
 * Evita hardcodear "Bs" o "S/" en las vistas: cada empresa tiene su propia
 * moneda (ver config/barbora.php).
 */
class Money
{
    public static function format(int|float|string|null $amount, Company|string|null $currency = null): string
    {
        $code = $currency instanceof Company
            ? ($currency->currency ?: config('barbora.default_currency'))
            : ($currency ?: config('barbora.default_currency'));

        $meta = config("barbora.currencies.{$code}", ['symbol' => $code, 'decimals' => 2]);

        return $meta['symbol'].' '.number_format((float) $amount, $meta['decimals'], '.', ',');
    }

    public static function symbol(Company|string|null $currency = null): string
    {
        $code = $currency instanceof Company
            ? ($currency->currency ?: config('barbora.default_currency'))
            : ($currency ?: config('barbora.default_currency'));

        return config("barbora.currencies.{$code}.symbol", $code);
    }
}
