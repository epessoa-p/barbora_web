<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * El rango de fechas de un reporte, ya saneado.
 *
 * Todos los reportes leen el periodo igual, así que la interpretación de
 * ?from/?to vive en un único sitio: fechas inválidas caen al mes en curso y un
 * rango al revés se endereza, en lugar de devolver una tabla vacía sin explicar
 * por qué.
 */
class ReportPeriod
{
    /** Atajos que ofrece la barra de filtros. */
    public const PRESETS = [
        'hoy' => 'Hoy',
        'semana' => 'Esta semana',
        'mes' => 'Este mes',
        'mes_pasado' => 'Mes pasado',
        'anio' => 'Este año',
    ];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $branchId = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        if ($preset = $request->input('preset')) {
            if ($range = self::preset($preset)) {
                return new self($range[0], $range[1], $branchId);
            }
        }

        $from = self::parse($request->input('from'));
        $to = self::parse($request->input('to'));

        // Sin fechas válidas, el mes en curso es la vista por defecto.
        if (! $from && ! $to) {
            [$from, $to] = self::preset('mes');
        }

        $from ??= $to->startOfMonth();
        $to ??= $from->endOfMonth();

        // Un rango al revés casi siempre es un dedazo en el formulario.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self($from->startOfDay(), $to->endOfDay(), $branchId);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    public static function preset(string $key): ?array
    {
        $now = CarbonImmutable::now();

        return match ($key) {
            'hoy' => [$now->startOfDay(), $now->endOfDay()],
            'semana' => [$now->startOfWeek()->startOfDay(), $now->endOfWeek()->endOfDay()],
            'mes' => [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay()],
            'mes_pasado' => [
                $now->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $now->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            'anio' => [$now->startOfYear()->startOfDay(), $now->endOfYear()->endOfDay()],
            default => null,
        };
    }

    /** Rango listo para un whereBetween sobre una columna dateTime. */
    public function range(): array
    {
        return [$this->from, $this->to];
    }

    public function days(): int
    {
        return $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /** El mismo número de días, justo antes: sirve para comparar. */
    public function previous(): self
    {
        $length = $this->days();

        return new self(
            $this->from->subDays($length)->startOfDay(),
            $this->from->subDay()->endOfDay(),
            $this->branchId,
        );
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->translatedFormat('d/m/Y');
        }

        return $this->from->translatedFormat('d/m/Y').' – '.$this->to->translatedFormat('d/m/Y');
    }

    /** Para conservar el filtro en enlaces y formularios. */
    public function queryParams(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'branch_id' => $this->branchId,
        ]);
    }

    protected static function parse(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
