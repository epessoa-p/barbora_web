<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Personal;
use App\Models\Role;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Deja una empresa recién creada lista para trabajar.
 *
 * Al dar de alta una barbería desde el panel del operador se crean, en el
 * mismo acto: la sucursal principal (y con ella su almacén, por
 * BranchObserver), los cargos de siempre con sus permisos, el responsable con
 * su cuenta de acceso, la caja y el horario de atención. Los métodos de pago
 * ya los pone CompanyObserver.
 *
 * Todo se crea saltando el CompanyScope: quien lo hace es el superadmin, sin
 * empresa activa, y el scope está en fail-closed.
 */
class CompanySetup
{
    /**
     * Cargos con los que nace toda barbería. `*` = todo lo que una empresa
     * puede conceder (Permission::grantableByCompany). El resto son los
     * permisos habituales de cada puesto (los mismos que RolePermissionSeeder
     * da a los roles del sistema).
     */
    public const CARGOS = [
        'ADMINISTRADOR' => '*',
        'RECEPCIONISTA' => [
            'branches.view', 'personal.view', 'schedules.view',
            'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
            'agenda_blocks.view',
            'services.view', 'service_categories.view',
            'clients.view', 'clients.create', 'clients.edit',
            'sales.view', 'sales.create',
            'cash.view', 'cash.create', 'cash.edit',
            'cajas.view',
        ],
        'BARBERO' => [
            'personal.view', 'schedules.view',
            'appointments.view', 'appointments.edit',
            'agenda_blocks.view',
            'sales.view', 'commissions.view',
            'services.view', 'clients.view',
        ],
    ];

    /** Días de la semana del formulario: 1 = lunes … 7 = domingo. */
    public const WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /**
     * Reglas de la puesta en marcha (campos `setup_*` del formulario).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'setup_branch_name' => ['required', 'string', 'max:255'],
            'setup_branch_address' => ['nullable', 'string', 'max:255'],
            'setup_branch_phone' => ['nullable', 'string', 'max:30'],

            'setup_owner_name' => ['required', 'string', 'max:255'],
            'setup_owner_id_number' => ['nullable', 'string', 'max:50'],
            'setup_owner_phone' => ['nullable', 'string', 'max:30'],
            // users.name es el nombre de usuario con el que se inicia sesión.
            'setup_owner_username' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'setup_owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'setup_owner_password' => ['required', 'string', 'min:8'],
            'setup_owner_bookable' => ['nullable', 'boolean'],

            'setup_caja_name' => ['required', 'string', 'max:255'],
            'setup_caja_to_owner' => ['nullable', 'boolean'],

            'setup_days' => ['nullable', 'array'],
            'setup_days.*' => ['integer', 'between:1,7'],
            'setup_open' => ['required', 'date_format:H:i'],
            'setup_close' => ['required', 'date_format:H:i', 'after:setup_open'],

            'setup_base_cargos' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'setup_branch_name.required' => 'Ponle nombre a la sucursal principal.',
            'setup_owner_name.required' => 'Escribe el nombre del responsable.',
            'setup_owner_username.required' => 'Indica el nombre de usuario del responsable.',
            'setup_owner_username.unique' => 'Ese nombre de usuario ya existe.',
            'setup_owner_email.required' => 'Indica el correo del responsable.',
            'setup_owner_email.unique' => 'Ya hay una cuenta con ese correo.',
            'setup_owner_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'setup_caja_name.required' => 'Ponle nombre a la caja.',
            'setup_close.after' => 'El cierre tiene que ser después de la apertura.',
        ];
    }

    /**
     * Crea todo. Debe llamarse dentro de la transacción del alta de empresa.
     *
     * @param  array<string, mixed>  $data  validado con rules()
     * @return array{username: string, email: string, password: string, branch: string, caja: string}
     *               las credenciales del responsable, para mostrarlas UNA vez
     */
    public function run(Company $company, array $data, Request $request): array
    {
        $branch = Branch::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => mb_strtoupper(trim($data['setup_branch_name'])),
            'code' => 'S01',
            'address' => $data['setup_branch_address'] ?? $company->address,
            'phone' => $data['setup_branch_phone'] ?? $company->phone,
            'manager_name' => mb_strtoupper(trim($data['setup_owner_name'])),
            'active' => true,
        ]);

        $cargos = $this->createCargos($company, $request->boolean('setup_base_cargos', true));
        $admin = $cargos['ADMINISTRADOR'];

        $user = User::create([
            'name' => trim($data['setup_owner_username']),
            'email' => trim($data['setup_owner_email']),
            'password' => Hash::make($data['setup_owner_password']),
            'phone' => $data['setup_owner_phone'] ?? null,
            'active' => true,
            'is_super_admin' => false,
        ]);

        $user->companies()->syncWithoutDetaching([
            $company->id => ['role_id' => $admin->role_id, 'active' => true],
        ]);

        $bookable = $request->boolean('setup_owner_bookable');

        $owner = Personal::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'cargo_id' => $admin->id,
            'user_id' => $user->id,
            'full_name' => mb_strtoupper(trim($data['setup_owner_name'])),
            'id_number' => $data['setup_owner_id_number'] ?? null,
            'phone' => $data['setup_owner_phone'] ?? null,
            'email' => trim($data['setup_owner_email']),
            'hire_date' => now()->toDateString(),
            'active' => true,
            'bookable' => $bookable,
            'agenda_color' => '#2563eb',
        ]);

        $caja = Caja::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'personal_id' => $request->boolean('setup_caja_to_owner') ? $owner->id : null,
            'name' => mb_strtoupper(trim($data['setup_caja_name'])),
            'code' => 'C01',
            'balance' => 0,
            'active' => true,
        ]);

        // Sin horario no hay huecos libres que reservar: si el responsable
        // atiende, se le pone el horario de atención de la barbería.
        if ($bookable) {
            foreach ($data['setup_days'] ?? [] as $weekday) {
                WorkSchedule::withoutGlobalScope(CompanyScope::class)->create([
                    'company_id' => $company->id,
                    'personal_id' => $owner->id,
                    'branch_id' => $branch->id,
                    'weekday' => (int) $weekday,
                    'start_time' => $data['setup_open'],
                    'end_time' => $data['setup_close'],
                    'active' => true,
                ]);
            }
        }

        return [
            'username' => $user->name,
            'email' => $user->email,
            'password' => $data['setup_owner_password'],
            'owner' => $owner->full_name,
            'branch' => $branch->name,
            'caja' => $caja->name,
        ];
    }

    /**
     * Cada cargo con su propio rol de la empresa (como hace CargoController),
     * con sus permisos. Devuelve los cargos por nombre.
     *
     * @return array<string, Cargo>
     */
    protected function createCargos(Company $company, bool $withBase): array
    {
        $grantable = Permission::grantableByCompany()->pluck('id', 'slug');
        $created = [];

        foreach (self::CARGOS as $name => $slugs) {
            if ($name !== 'ADMINISTRADOR' && ! $withBase) {
                continue;
            }

            $role = Role::create([
                'company_id' => $company->id,
                'name' => $name,
                'slug' => Role::generateSlug($name, $company->id),
            ]);

            $ids = $slugs === '*'
                ? $grantable->values()
                : $grantable->only($slugs)->values();

            $role->permissions()->sync($ids->all());

            $created[$name] = Cargo::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => $name,
                'active' => true,
            ]);
        }

        return $created;
    }
}
