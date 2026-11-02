<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto de una empresa nueva
    |--------------------------------------------------------------------------
    |
    | Barbora es multipaís: cada empresa guarda su propio país, moneda, zona
    | horaria y etiqueta de identificador fiscal. Estos son los valores que se
    | proponen al dar de alta una empresa desde el panel del superadmin.
    |
    */

    'default_country'  => env('BARBORA_COUNTRY', 'BO'),
    'default_currency' => env('BARBORA_CURRENCY', 'BOB'),
    'default_timezone' => env('BARBORA_TIMEZONE', 'America/La_Paz'),

    /*
    |--------------------------------------------------------------------------
    | Catálogo de países
    |--------------------------------------------------------------------------
    |
    | Por cada país: cómo se llama su identificador fiscal, qué moneda usa por
    | defecto y en qué zona horaria opera. Al elegir país en el formulario de
    | empresa, los otros tres campos se autocompletan con estos valores.
    |
    */

    'countries' => [
        'BO' => ['name' => 'Bolivia',     'tax_label' => 'NIT',  'currency' => 'BOB', 'timezone' => 'America/La_Paz'],
        'PE' => ['name' => 'Perú',        'tax_label' => 'RUC',  'currency' => 'PEN', 'timezone' => 'America/Lima'],
        'CL' => ['name' => 'Chile',       'tax_label' => 'RUT',  'currency' => 'CLP', 'timezone' => 'America/Santiago'],
        'CO' => ['name' => 'Colombia',    'tax_label' => 'NIT',  'currency' => 'COP', 'timezone' => 'America/Bogota'],
        'MX' => ['name' => 'México',      'tax_label' => 'RFC',  'currency' => 'MXN', 'timezone' => 'America/Mexico_City'],
        'AR' => ['name' => 'Argentina',   'tax_label' => 'CUIT', 'currency' => 'ARS', 'timezone' => 'America/Argentina/Buenos_Aires'],
        'EC' => ['name' => 'Ecuador',     'tax_label' => 'RUC',  'currency' => 'USD', 'timezone' => 'America/Guayaquil'],
        'SV' => ['name' => 'El Salvador', 'tax_label' => 'NIT',  'currency' => 'USD', 'timezone' => 'America/El_Salvador'],
        'ES' => ['name' => 'España',      'tax_label' => 'NIF',  'currency' => 'EUR', 'timezone' => 'Europe/Madrid'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monedas
    |--------------------------------------------------------------------------
    |
    | Símbolo y número de decimales de cada moneda soportada. Lo consume
    | App\Support\Money para no escribir símbolos a mano en las vistas.
    |
    */

    // «bills» y «coins»: lo que se cuenta al cerrar caja cuando la barbería
    // arquea por billetes y monedas (de mayor a menor).
    'currencies' => [
        'BOB' => ['symbol' => 'Bs', 'decimals' => 2,
            'bills' => [200, 100, 50, 20, 10], 'coins' => [5, 2, 1, 0.5, 0.2, 0.1]],
        'PEN' => ['symbol' => 'S/', 'decimals' => 2,
            'bills' => [200, 100, 50, 20, 10], 'coins' => [5, 2, 1, 0.5, 0.2, 0.1]],
        'CLP' => ['symbol' => '$', 'decimals' => 0,
            'bills' => [20000, 10000, 5000, 2000, 1000], 'coins' => [500, 100, 50, 10]],
        'COP' => ['symbol' => '$', 'decimals' => 0,
            'bills' => [100000, 50000, 20000, 10000, 5000, 2000], 'coins' => [1000, 500, 200, 100, 50]],
        'MXN' => ['symbol' => '$', 'decimals' => 2,
            'bills' => [1000, 500, 200, 100, 50, 20], 'coins' => [10, 5, 2, 1, 0.5]],
        'ARS' => ['symbol' => '$', 'decimals' => 2,
            'bills' => [20000, 10000, 2000, 1000, 500, 200, 100], 'coins' => [50, 20, 10, 5, 2, 1]],
        'USD' => ['symbol' => '$', 'decimals' => 2,
            'bills' => [100, 50, 20, 10, 5, 1], 'coins' => [0.25, 0.1, 0.05, 0.01]],
        'EUR' => ['symbol' => '€', 'decimals' => 2,
            'bills' => [500, 200, 100, 50, 20, 10, 5], 'coins' => [2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Control de suscripción
    |--------------------------------------------------------------------------
    |
    | Con false, el middleware EnsureSubscriptionActive deja pasar todo. Útil
    | en desarrollo o en una instalación on-premise de un solo cliente.
    |
    */

    'require_subscription' => env('BARBORA_REQUIRE_SUBSCRIPTION', true),

];
