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
 * Cargos de una empresa y el rol del que heredan permisos.
 *
 * Invariante de este controlador: desde aquí NUNCA se toca el catálogo global.
 * Una empresa sólo puede asignar roles disponibles para ella (los del sistema
 * menos super_admin, más los suyos) y sólo puede reescribir los permisos de un
 * rol que le pertenece. Los roles del sistema se editan en Plataforma → Roles.
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
                $role = $this->resolveRole($validated, $companyId);
                $this->syncRolePermissions($role, $validated, $companyId);

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
                $role = $this->resolveRole($validated, $companyId);
                $this->syncRolePermissions($role, $validated, $companyId);

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

    /**
     * AJAX: permisos del rol elegido, para precargar las casillas del formulario.
     * Acotado a los roles que la empresa puede asignar: si no, cualquier usuario
     * con cargos.create podría leer los permisos del rol super_admin.
     */
    public function rolePermissions(Role $role)
    {
        $companyId = $this->cargoCompanyId();

        if (! Role::assignableBy($companyId)->whereKey($role->id)->exists()) {
            abort(404);
        }

        return response()->json([
            'permissions' => $role->permissions()->grantableByCompany()->pluck('permissions.id'),
            'editable' => $role->permissionsEditableBy($companyId),
        ]);
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
            'roles' => Role::assignableBy($companyId)->orderBy('company_id')->orderBy('name')->get(),
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
            'role_mode' => ['required', 'in:existing,new'],
            'role_id' => ['required_if:role_mode,existing', 'nullable', 'integer'],
            'new_role_name' => ['required_if:role_mode,new', 'nullable', 'string', 'max:255'],
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

    /**
     * Devuelve el rol del cargo, creándolo si hace falta. Un rol nuevo nace
     * SIEMPRE como propiedad de la empresa, nunca en el catálogo del sistema.
     */
    protected function resolveRole(array $validated, int $companyId): Role
    {
        if ($validated['role_mode'] === 'new') {
            $name = trim($validated['new_role_name']);

            return Role::create([
                'company_id' => $companyId,
                'name' => $name,
                'slug' => Role::generateSlug($name, $companyId),
            ]);
        }

        $role = Role::assignableBy($companyId)->find($validated['role_id']);

        if (! $role) {
            throw ValidationException::withMessages([
                'role_id' => 'Ese rol no está disponible para tu empresa.',
            ]);
        }

        return $role;
    }

    /**
     * Aplica los permisos marcados, pero SÓLO sobre un rol propio de la empresa.
     *
     * Sobre un rol del sistema no se escribe nunca: reescribirlo cambiaría los
     * permisos de todas las empresas que lo usan. El formulario ya deshabilita
     * las casillas en ese caso (y entonces ni siquiera manda 'permissions'), así
     * que sólo se devuelve el error cuando la petición insiste en cambiarlos.
     */
    protected function syncRolePermissions(Role $role, array $validated, int $companyId): void
    {
        // La comparación se hace sólo sobre el universo que el formulario pinta:
        // los permisos de plataforma que un rol del sistema pueda tener quedan
        // fuera y no cuentan como cambio.
        $grantable = Permission::grantableByCompany()->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        $submitted = collect($validated['permissions'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->intersect($grantable)
            ->unique()->sort()->values()->all();

        if (! $role->permissionsEditableBy($companyId)) {
            $current = $role->permissions()->pluck('permissions.id')
                ->map(fn ($id) => (int) $id)
                ->intersect($grantable)
                ->unique()->sort()->values()->all();

            if (array_key_exists('permissions', $validated) && $submitted !== $current) {
                throw ValidationException::withMessages([
                    'permissions' => 'Los roles del sistema los comparten todas las empresas, '
                        . 'así que no se pueden modificar desde aquí. Crea un rol propio '
                        . 'para definir tus permisos.',
                ]);
            }

            return;
        }

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
