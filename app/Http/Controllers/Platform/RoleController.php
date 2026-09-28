<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\Rule;

/**
 * Catálogo de roles del SISTEMA: los que comparten todas las empresas.
 * Reservado al superadmin (ver routes/modules/platform.php).
 *
 * Los roles propios de una barbería NO se administran aquí: son suyos y se
 * crean desde su pantalla de cargos. Por eso todo este controlador trabaja
 * sobre Role::system().
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::system()->with('permissions')->paginate(15);

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::all()->groupBy('module');

        return view('admin.roles.create', compact('permissions'));
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'slug')->whereNull('company_id'),
            ],
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'company_id' => null,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Rol creado exitosamente');
    }

    public function show(Role $role)
    {
        $this->authorizeSystemRole($role);
        $role->load('permissions');

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        $this->authorizeSystemRole($role);

        $permissions = Permission::all()->groupBy('module');
        $role->load('permissions');

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Role $role)
    {
        $this->authorizeSystemRole($role);

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'slug')->whereNull('company_id')->ignore($role->id),
            ],
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Vaciarle los permisos al rol del operador dejaría el SaaS sin nadie
        // capaz de administrarlo.
        if ($role->isSuperAdmin()) {
            unset($validated['slug']);

            return $this->updateSuperAdmin($role, $validated);
        }

        $role->update($validated);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado exitosamente');
    }

    public function destroy(Role $role)
    {
        $this->authorizeSystemRole($role);

        if ($role->isSuperAdmin()) {
            return back()->withErrors(['error' => 'El rol del operador de la plataforma no se puede eliminar.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un rol que tiene usuarios asignados']);
        }

        if ($role->cargos()->withoutGlobalScopes()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un rol que está asignado a un cargo']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado exitosamente');
    }

    /** Un rol de una empresa no se administra desde el catálogo global. */
    protected function authorizeSystemRole(Role $role): void
    {
        if (! $role->isSystem()) {
            abort(404);
        }
    }

    protected function updateSuperAdmin(Role $role, array $validated)
    {
        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado. El slug y los permisos del operador no se modifican.');
    }
}
