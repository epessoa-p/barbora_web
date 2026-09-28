<?php

namespace Database\Seeders;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Service;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Citas de ejemplo para la Barbería Demo: unas cuantas hoy y el resto repartidas
 * por la semana, para que el calendario tenga algo que enseñar nada más entrar.
 */
class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            $barbers = Personal::bookable()->with('schedules')->orderBy('full_name')->get();
            $clients = Client::orderBy('full_name')->get();
            $services = Service::where('active', true)->get();

            if ($barbers->isEmpty() || $clients->isEmpty() || $services->isEmpty()) {
                return;
            }

            Appointment::query()->forceDelete();

            // Los barberos trabajan de martes a sábado: se siembra sobre esos
            // días para que las citas caigan siempre dentro de un turno.
            $days = collect(range(0, 6))
                ->map(fn ($i) => Carbon::today()->startOfWeek(Carbon::MONDAY)->addDays($i))
                ->filter(fn (Carbon $d) => $d->dayOfWeekIso >= 2 && $d->dayOfWeekIso <= 6);

            $hours = ['09:00', '10:30', '12:00', '15:30', '17:00', '18:30'];
            $statuses = ['reservada', 'confirmada', 'atendida'];
            $seed = 0;

            foreach ($days as $day) {
                foreach ($barbers as $barber) {
                    foreach (array_slice($hours, 0, 3) as $i => $hour) {
                        $seed++;

                        // Una de cada cuatro combinaciones se salta, para que la
                        // agenda no se vea artificialmente llena.
                        if ($seed % 4 === 0) {
                            continue;
                        }

                        $start = $day->copy()->setTimeFromTimeString($hour);
                        $chosen = $services->slice($seed % max(1, $services->count() - 1), 1);

                        if ($chosen->isEmpty()) {
                            continue;
                        }

                        $end = $start->copy()->addMinutes((int) $chosen->sum('duration_minutes'));

                        if (! $barber->worksDuring($start, $end)) {
                            continue;
                        }

                        if (Appointment::overlapping($barber->id, $start, $end)->exists()) {
                            continue;
                        }

                        $status = $start->isPast() ? 'atendida' : $statuses[$seed % 2];

                        $appointment = Appointment::create([
                            'company_id' => $demo->id,
                            'personal_id' => $barber->id,
                            'client_id' => $clients[$seed % $clients->count()]->id,
                            'starts_at' => $start,
                            'ends_at' => $end,
                            'status' => $status,
                        ]);

                        $appointment->services()->attach(
                            $chosen->mapWithKeys(fn (Service $s) => [
                                $s->id => ['duration_minutes' => $s->duration_minutes, 'price' => $s->price],
                            ])->all()
                        );
                    }
                }
            }

            $this->seedBlocks($demo, $barbers);
        });
    }

    /**
     * Un bloqueo de cada tipo, para que la vista de mes enseñe los tres casos:
     * la barbería cerrada, un barbero de vacaciones y un descanso de unas horas.
     */
    protected function seedBlocks(Company $demo, $barbers): void
    {
        if ($barbers->isEmpty() || AgendaBlock::exists()) {
            return;
        }

        $nextMonth = Carbon::today()->addMonthNoOverflow()->startOfMonth();

        AgendaBlock::create([
            'company_id' => $demo->id,
            'title' => 'Feriado',
            'reason' => 'feriado',
            'starts_at' => $nextMonth->copy()->addDays(5)->startOfDay(),
            'ends_at' => $nextMonth->copy()->addDays(5)->endOfDay(),
            'all_day' => true,
            'notes' => 'La barbería no abre.',
        ]);

        $first = $barbers->first();

        AgendaBlock::create([
            'company_id' => $demo->id,
            'personal_id' => $first->id,
            'title' => 'Vacaciones de '.$first->full_name,
            'reason' => 'vacaciones',
            'starts_at' => $nextMonth->copy()->addDays(14)->startOfDay(),
            'ends_at' => $nextMonth->copy()->addDays(20)->endOfDay(),
            'all_day' => true,
        ]);

        AgendaBlock::create([
            'company_id' => $demo->id,
            'personal_id' => $first->id,
            'title' => 'Almuerzo',
            'reason' => 'descanso',
            'starts_at' => Carbon::today()->addDay()->setTime(13, 0),
            'ends_at' => Carbon::today()->addDay()->setTime(14, 0),
            'all_day' => false,
        ]);
    }
}
