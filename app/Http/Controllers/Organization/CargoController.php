<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cargos de una empresa y su rol de permisos.
 *
 * Cada cargo tiene su PROPIO rol, dueño la empresa: al crear un cargo se crea
 * un rol nuevo dentro de la empresa con los permisos marcados. Nunca se elige
 * ni se toca un rol del catálogo global (eso cambiaría los permisos de todas
 * las empresas). Los roles del sistema se editan en Plataforma → Roles.
 *
 * Si un cargo heredado apuntaba a un rol del sistema (datos antiguos), al
 * editarlo se le crea un rol propio y se desengancha del compartido.
 */
class CargoController extends Controller
{
    public function index()
    {
        // El CompanyScope ya filtra por la empresa activa.
        $query = Cargo::with(['company', 'role'])->latest();

        if (auth()->user()->is_super_admin && request('company_id')) {
            $query->forCompany((int) request('company_id'));
        }

        $cargos = $query->paginate(15);

        return view('admin.cargos.index', compact('cargos'));
    }

    public function create()
    {
        $companyId = $this->cargoCompanyId();

        return view('admin.cargos.create', $this->formData($companyId));
    }

    public function store(Request $request)
    {
        $companyId = $this->cargoCompanyId($request);

        $validated = $this->validateCargo($request, $companyId);

        if (! $companyId) {
            return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
        }

        try {
            DB::transaction(function () use ($validated, $companyId, $request) {
                // Un cargo nuevo nace con su propio rol, dueño la empresa.
                $role = $this->createCompanyRole($validated['name'], $companyId);
                $this->syncRolePermissions($role, $validated);

                Cargo::create([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear cargo', ['message' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear el cargo.']);
        }

        return redirect()->route('cargos.index')->with('success', 'Cargo creado exitosamente.');
    }

    public function edit(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        $cargo->load('role.permissions');
        $companyId = $this->cargoCompanyId(null, $cargo);

        return view('admin.cargos.edit', array_merge($this->formData($companyId), ['cargo' => $cargo]));
    }

    public function update(Request $request, Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        $companyId = $this->cargoCompanyId($request, $cargo);

        $validated = $this->validateCargo($request, $companyId, $cargo);

        try {
            DB::transaction(function () use ($validated, $cargo, $companyId, $request) {
                // Se editan los permisos del rol propio del cargo; si heredaba
                // uno del sistema, se le crea uno propio y se desengancha.
                $role = $this->companyRoleForUpdate($cargo, $validated['name'], $companyId);
                $this->syncRolePermissions($role, $validated);

                $cargo->update([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cargo', ['cargo_id' => $cargo->id, 'message' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cargo.']);
        }

        return redirect()->route('cargos.index')->with('success', 'Cargo actualizado exitosamente.');
    }

    public function destroy(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        if ($cargo->personals()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un cargo con personal asignado.']);
        }

        $cargo->delete();

        return redirect()->route('cargos.index')->with('success', 'Cargo eliminado exitosamente.');
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    /** Empresa a la que pertenece (o pertenecerá) el cargo. */
    protected function cargoCompanyId(?Request $request = null, ?Cargo $cargo = null): ?int
    {
        $authUser = auth()->user();

        if ($authUser->is_super_admin) {
            $fromRequest = $request?->input('company_id') ?? $cargo?->company_id;

            return $fromRequest ? (int) $fromRequest : null;
        }

        return $cargo
            ? (int) $cargo->company_id
            : $authUser->getCurrentCompany()?->id;
    }

    protected function formData(?int $companyId): array
    {
        $authUser = auth()->user();

        return [
            'cargo' => null,
            // Los permisos de plataforma nunca se conceden desde un cargo.
            'permissions' => Permission::grantableByCompany()->orderBy('module')->get()->groupBy('module'),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ];
    }

    protected function validateCargo(Request $request, ?int $companyId, ?Cargo $cargo = null): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('cargos', 'name')
                    ->ignore($cargo?->id)
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer'],
        ]);
    }

    /** Crea un rol nuevo, dueño la empresa, con el nombre del cargo. */
    protected function createCompanyRole(string $cargoName, int $companyId): Role
    {
        $name = trim($cargoName);

        return Role::create([
            'company_id' => $companyId,
            'name' => $name,
            'slug' => Role::generateSlug($name, $companyId),
        ]);
    }

    /**
     * El rol sobre el que se guardan los permisos al editar un cargo.
     *
     * Si el cargo ya tiene un rol propio de la empresa, se reutiliza. Si
     * heredaba uno del sistema (compartido por todas), se le crea uno propio
     * para no tocar el catálogo global.
     */
    protected function companyRoleForUpdate(Cargo $cargo, string $cargoName, int $companyId): Role
    {
        $role = $cargo->role;

        if ($role && $role->permissionsEditableBy($companyId)) {
            return $role;
        }

        return $this->createCompanyRole($cargoName, $companyId);
    }

    /**
     * Aplica los permisos marcados al rol (siempre propio de la empresa).
     *
     * Sólo se guardan los permisos concedibles desde un cargo: los de
     * plataforma (companies, plans, roles…) se descartan aquí, aunque la
     * petición los incluya.
     */
    protected function syncRolePermissions(Role $role, array $validated): void
    {
        $grantable = Permission::grantableByCompany()->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        $submitted = collect($validated['permissions'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->intersect($grantable)
            ->unique()->sort()->values()->all();

        $role->permissions()->sync($submitted);
    }

    protected function authorizeCargo(Cargo $cargo): void
    {
        $authUser = auth()->user();

        if (! $authUser->is_super_admin && $cargo->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
