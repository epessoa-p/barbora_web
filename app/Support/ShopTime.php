<?php

namespace App\Support;

use App\Models\Company;
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
    public static function iso(?CarbonInterface $datetime, ?Company $company): ?string
    {
        if (! $datetime) {
            return null;
        }

        $timezone = $company?->timezone ?: config('barbora.default_timezone', 'UTC');

        return $datetime->copy()->shiftTimezone($timezone)->toIso8601String();
    }
}
