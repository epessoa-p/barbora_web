<?php

namespace App\Support;

use App\Models\Company;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fechas de la API con la zona horaria de la barbería.
 *
 * El sistema guarda las fechas como «hora de pared» del local: una cita a las
 * 09:00 se almacena 09:00, sin convertir. Eso funciona en la web porque quien
 * la mira está en la barbería, pero en la API hace falta decirlo explícitamente:
 * si se serializa tal cual, Carbon le cuelga el offset de config('app.timezone')
 * —que hoy es UTC— y un móvil en Bolivia mostraría esa cita a las 05:00.
 *
 * Por eso se usa shiftTimezone y NO setTimezone: no hay que mover la hora, hay
 * que etiquetarla con la zona correcta. Las 09:00 guardadas SON las 09:00 de
 * La Paz, no las 09:00 UTC.
 *
 * Ver la deuda técnica del CHECKLIST: lo correcto a futuro es guardar en UTC y
 * convertir al mostrar, pero eso es un cambio de fondo con datos ya creados.
 */
class ShopTime
{
    /**
     * Pone el reloj de la petición en la zona de la empresa.
     *
     * Como todo se guarda en hora de pared del local, now(), today() e
     * isPast() tienen que contar en esa misma hora. Con el reloj en UTC, a las
     * 09:00 de La Paz el sistema creía que eran las 13:00: la agenda escondía
     * las horas de la mañana como «pasadas» y una venta de las 21:00 se
     * registraba al día siguiente. Lo llama SetTenant en cada petición.
     */
    public static function apply(?Company $company): void
    {
        $timezone = self::zoneOf($company);

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    public static function zoneOf(?Company $company): string
    {
        $timezone = $company?->timezone ?: config('barbora.default_timezone', 'America/La_Paz');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'America/La_Paz';
    }

    /**
     * «Ahora» en la hora de pared de la barbería, comparable con lo guardado.
     *
     * Como las fechas se guardan sin convertir (09:00 de La Paz se guarda
     * 09:00), comparar con now() —que va en UTC— daría por pasada una cita de
     * las 11:00 a las 10:00 de La Paz (14:00 UTC). Esto devuelve las 10:00.
     */
    public static function now(?Company $company): Carbon
    {
        $timezone = $company?->timezone ?: config('barbora.default_timezone', 'UTC');

        return Carbon::parse(Carbon::now($timezone)->format('Y-m-d H:i:s'));
    }

    public static function iso(?CarbonInterface $datetime, ?Company $company): ?string
    {
        if (! $datetime) {
            return null;
        }

        $timezone = $company?->timezone ?: config('barbora.default_timezone', 'UTC');

        return $datetime->copy()->shiftTimezone($timezone)->toIso8601String();
    }
}
