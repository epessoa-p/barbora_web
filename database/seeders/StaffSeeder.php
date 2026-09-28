<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Equipo de la Barbería Demo, con sus horarios.
 *
 * Solo la empresa Demo: Barbería Prueba se deja vacía para poder comprobar el
 * aislamiento de un vistazo.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            $cargos = [
                'Barbero' => 'barber',
                'Recepcionista' => 'receptionist',
            ];

            $cargoIds = [];

            foreach ($cargos as $name => $roleSlug) {
                $role = Role::system()->where('slug', $roleSlug)->first();

                $cargoIds[$name] = Cargo::updateOrCreate(
                    ['name' => $name],
                    ['role_id' => $role?->id, 'active' => true]
                )->id;
            }

            // nombre, cargo, especialidad, color, atiende citas
            $staff = [
                ['Iván Céspedes',   'Barbero',       'Fade y diseño',      '#2563eb', true],
                ['Rodrigo Salazar', 'Barbero',       'Barba clásica',      '#0d9488', true],
                ['Tania Molina',    'Barbero',       'Color y tratamientos', '#db2777', true],
                ['Paola Ledezma',   'Recepcionista', null,                 '#f59e0b', false],
            ];

            foreach ($staff as [$fullName, $cargoName, $specialty, $color, $bookable]) {
                $email = Str::of($fullName)->ascii()->lower()->replace(' ', '.')->append('@barberiademo.test')->value();

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => Str::of($fullName)->ascii()->lower()->replace(' ', '')->value(),
                        'password' => 'Admin@1234',
                        'is_super_admin' => false,
                        'active' => true,
                    ]
                );

                $cargo = Cargo::find($cargoIds[$cargoName]);

                $user->companies()->syncWithoutDetaching([
                    $demo->id => ['role_id' => $cargo->role_id, 'active' => true],
                ]);

                $person = Personal::updateOrCreate(
                    ['full_name' => $fullName],
                    [
                        'cargo_id' => $cargo->id,
                        'user_id' => $user->id,
                        'email' => $email,
                        'specialty' => $specialty,
                        'agenda_color' => $color,
                        'bookable' => $bookable,
                        'active' => true,
                    ]
                );

                if (! $bookable) {
                    continue;
                }

                $person->schedules()->delete();

                // Turno partido de martes a sábado, que es lo habitual en una
                // barbería; Tania además libra los martes.
                $days = $fullName === 'Tania Molina' ? [3, 4, 5, 6] : [2, 3, 4, 5, 6];

                foreach ($days as $weekday) {
                    foreach ([['09:00', '13:00'], ['15:00', '20:00']] as [$start, $end]) {
                        WorkSchedule::create([
                            'company_id' => $demo->id,
                            'personal_id' => $person->id,
                            'branch_id' => null,
                            'weekday' => $weekday,
                            'start_time' => $start,
                            'end_time' => $end,
                            'active' => true,
                        ]);
                    }
                }
            }
        });
    }
}
