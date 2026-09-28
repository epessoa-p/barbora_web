<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Personal;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Calendario de la agenda.
 *
 * Tres vistas, cada una responde a una pregunta distinta:
 *   - Día: columnas por barbero. «¿Quién está libre ahora mismo?»
 *   - Semana: columnas por día, de un solo barbero. «¿Cómo tiene la semana?»
 *   - Mes: rejilla de días. «¿Cómo viene el mes? ¿Cuándo hay cerrado?»
 */
class CalendarController extends Controller
{
    /** Altura de una hora en la rejilla, en píxeles: la usa la vista. */
    private const HOUR_HEIGHT = 56;

    public function __invoke(Request $request)
    {
        $view = match ($request->query('view')) {
            'week' => 'week',
            'month' => 'month',
            default => 'day',
        };

        $day = $this->parseDay($request->query('day'));

        $staff = Personal::bookable()->with('schedules')->orderBy('full_name')->get();

        return match ($view) {
            'week' => $this->week($request, $day, $staff),
            'month' => $this->month($request, $day, $staff),
            default => $this->day($day, $staff),
        };
    }

    protected function day(Carbon $day, $staff)
    {
        $appointments = Appointment::with(['client', 'services'])
            ->onDay($day)
            ->blocking()
            ->orderBy('starts_at')
            ->get()
            ->groupBy('personal_id');

        [$from, $to] = $this->hourRange(
            $staff->flatMap->schedules->where('weekday', $day->dayOfWeekIso),
            $appointments->flatten(),
        );

        $blocks = AgendaBlock::with('personal')
            ->between($day->copy()->startOfDay(), $day->copy()->endOfDay())
            ->orderBy('starts_at')
            ->get();

        return view('appointments.calendar.day', [
            'day' => $day,
            'staff' => $staff,
            'appointments' => $appointments,
            'blocks' => $blocks,
            // Los de toda la barbería se muestran aparte: afectan a todos.
            'companyBlocks' => $blocks->filter->isCompanyWide(),
            'blocksByStaff' => $blocks->reject->isCompanyWide()->groupBy('personal_id'),
            'fromHour' => $from,
            'toHour' => $to,
            'hourHeight' => self::HOUR_HEIGHT,
        ]);
    }

    /**
     * Vista de mes: una rejilla de seis semanas con el recuento de cada día.
     *
     * No pinta las citas una a una a propósito: a esta escala lo que se quiere
     * ver es la carga y dónde hay cerrado, no los detalles.
     */
    protected function month(Request $request, Carbon $day, $staff)
    {
        $monthStart = $day->copy()->startOfMonth();
        $monthEnd = $day->copy()->endOfMonth();

        // La rejilla arranca el lunes de la semana del día 1 y termina el
        // domingo de la del último, para que las filas queden completas.
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $barberId = $request->integer('personal') ?: null;

        $appointments = Appointment::with('personal')
            ->between($gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay())
            ->when($barberId, fn ($q) => $q->where('personal_id', $barberId))
            ->get()
            ->groupBy(fn (Appointment $a) => $a->starts_at->toDateString());

        $blocks = AgendaBlock::with('personal')
            ->between($gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay())
            ->when($barberId, fn ($q) => $q->where(fn ($w) => $w->whereNull('personal_id')
                                                                ->orWhere('personal_id', $barberId)))
            ->get();

        $days = collect();
        $cursor = $gridStart->copy();

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $key = $cursor->toDateString();
            $own = $appointments->get($key, collect());

            $days->push([
                'date' => $cursor->copy(),
                'key' => $key,
                'inMonth' => $cursor->month === $monthStart->month,
                'isToday' => $cursor->isToday(),
                'total' => $own->reject->isReleased()->count(),
                'attended' => $own->where('status', 'atendida')->count(),
                'cancelled' => $own->whereIn('status', Appointment::RELEASED_STATUSES)->count(),
                // Un bloqueo que cubre el día entero lo pinta como cerrado.
                'blocks' => $blocks->filter(fn (AgendaBlock $b) => $b->starts_at->lessThan($cursor->copy()->endOfDay())
                                                               && $b->ends_at->greaterThan($cursor->copy()->startOfDay()))
                                   ->values(),
            ]);

            $cursor->addDay();
        }

        return view('appointments.calendar.month', [
            'day' => $day,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'weeks' => $days->chunk(7),
            'staff' => $staff,
            'barber' => $barberId ? $staff->firstWhere('id', $barberId) : null,
            'totals' => [
                'appointments' => $days->where('inMonth', true)->sum('total'),
                'attended' => $days->where('inMonth', true)->sum('attended'),
                'blocked' => $days->where('inMonth', true)->filter(fn ($d) => $d['blocks']->isNotEmpty())->count(),
            ],
        ]);
    }

    protected function week(Request $request, Carbon $day, $staff)
    {
        // Sin barbero elegido se muestra el primero: una semana con todos a la
        // vez sería ilegible.
        $barber = $staff->firstWhere('id', $request->integer('personal')) ?? $staff->first();

        $start = $day->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $appointments = $barber
            ? Appointment::with(['client', 'services'])
                ->where('personal_id', $barber->id)
                ->between($start->copy()->startOfDay(), $end->copy()->endOfDay())
                ->blocking()
                ->orderBy('starts_at')
                ->get()
                ->groupBy(fn (Appointment $a) => $a->starts_at->toDateString())
            : collect();

        [$from, $to] = $this->hourRange(
            $barber?->schedules->where('active', true) ?? collect(),
            $appointments->flatten(),
        );

        $blocks = AgendaBlock::with('personal')
            ->between($start->copy()->startOfDay(), $end->copy()->endOfDay())
            ->when($barber, fn ($q) => $q->where(fn ($w) => $w->whereNull('personal_id')
                                                              ->orWhere('personal_id', $barber->id)))
            ->orderBy('starts_at')
            ->get();

        return view('appointments.calendar.week', [
            'day' => $day,
            'weekStart' => $start,
            'weekEnd' => $end,
            'blocks' => $blocks,
            'days' => collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)),
            'staff' => $staff,
            'barber' => $barber,
            'appointments' => $appointments,
            'fromHour' => $from,
            'toHour' => $to,
            'hourHeight' => self::HOUR_HEIGHT,
        ]);
    }

    /**
     * Franja horaria que se pinta: la que cubren los turnos, ampliada si alguna
     * cita se sale. Así una barbería que abre a las 9 no mira ocho horas vacías.
     *
     * @return array{0: int, 1: int}
     */
    protected function hourRange($schedules, $appointments): array
    {
        $from = 24;
        $to = 0;

        foreach ($schedules as $schedule) {
            $from = min($from, (int) substr((string) $schedule->start_time, 0, 2));
            $to = max($to, (int) ceil($this->toDecimalHour((string) $schedule->end_time)));
        }

        foreach ($appointments as $appointment) {
            $from = min($from, (int) $appointment->starts_at->format('G'));
            $to = max($to, (int) ceil($appointment->ends_at->hour + $appointment->ends_at->minute / 60));
        }

        if ($from >= $to) {
            return [8, 21];   // agenda vacía: una jornada razonable por defecto
        }

        return [max(0, $from), min(24, $to)];
    }

    protected function toDecimalHour(string $time): float
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h + $m / 60;
    }

    protected function parseDay(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }
}
