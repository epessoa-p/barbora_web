<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Listado de usuarios con su barbería y su rol en ella.
     *
     * Un usuario no tiene company_id: se une a las barberías por company_user,
     * con un rol en cada una. Por eso aquí se carga esa relación.
     *
     *   · Superadmin: ve todas las membresías de cada usuario, y puede filtrar
     *     por barbería.
     *   · Admin de barbería: solo la membresía en SU barbería. Si un usuario
     *     trabajara también en otra, eso no le incumbe y no se muestra.
     */
    public function index(Request $request)
    {
        $authUser = auth()->user();

        if ($authUser->is_super_admin) {
            $companyId = $request->integer('company_id') ?: null;

            $users = User::query()
                ->with('companies')
                ->when($companyId, fn ($q) => $q->whereHas(
                    'companies',
                    fn ($c) => $c->where('companies.id', $companyId),
                ))
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString();

            $companies = Company::orderBy('name')->get(['id', 'name']);
        } else {
            $company = $authUser->getCurrentCompany();
            $companyId = $company->id;

            $users = $company->users()
                ->with(['companies' => fn ($q) => $q->where('companies.id', $company->id)])
                ->orderBy('name')
                ->paginate(15);

            $companies = collect();
        }

        // Los nombres de rol de una sola consulta, no uno por fila.
        $roleIds = $users->getCollection()
            ->flatMap(fn (User $u) => $u->companies->pluck('pivot.role_id'))
            ->unique();

        return view('admin.users.index', [
            'users' => $users,
            'companies' => $companies,
            'selectedCompany' => $companyId,
            'roleNames' => Role::whereIn('id', $roleIds)->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('admin.users.create', $this->formData());
    }

    public function store(StoreUserRequest $request)
    {
        $membership = $this->validateMembership($request);

        if ($membership && $this->planLimitReached($membership['company_id'], 'users')) {
            return back()->withInput()->withErrors([
                'company_id' => 'La empresa alcanzó el límite de usuarios de su plan.',
            ]);
        }

        try {
            DB::transaction(function () use ($request, $membership) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password,
                    'phone' => $request->phone,
                    'is_super_admin' => auth()->user()->is_super_admin
                        ? $request->boolean('is_super_admin', false)
                        : false,
                ]);

                if ($membership) {
                    $user->companies()->attach($membership['company_id'], [
                        'role_id' => $membership['role_id'],
                        'active' => true,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Log::error('Error al crear usuario', [
                'user' => $request->only('name', 'email'),
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear el usuario.']);
        }

        return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente');
    }

    public function show(User $user)
    {
        $this->authorizeUser($user);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorizeUser($user);

        return view('admin.users.edit', array_merge($this->formData(), ['user' => $user]));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $this->authorizeUser($user);

        try {
            $data = $request->validated();

            if (! auth()->user()->is_super_admin) {
                unset($data['is_super_admin']);
            }

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el usuario.']);
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente');
    }

    public function destroy(User $user)
    {
        $this->authorizeUser($user);

        if ($user->id === auth()->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente');
    }

    /**
     * Asignar rol a un usuario dentro de una empresa.
     *
     * Comprueba las tres cosas por separado: que el actor mande en esa empresa,
     * que el rol sea asignable ahí (los del sistema menos super_admin, más los
     * propios de la empresa) y, si es un alta, que quede cupo en el plan.
     */
    public function assignRole(User $user, Company $company, Role $role)
    {
        if (! $this->manageableCompanyIds()->contains($company->id)) {
            abort(403);
        }

        if (! Role::assignableBy($company->id)->whereKey($role->id)->exists()) {
            abort(403);
        }

        $isNewMember = ! $user->companies()->whereKey($company->id)->exists();

        if ($isNewMember && $this->planLimitReached($company->id, 'users')) {
            return back()->withErrors([
                'error' => 'La empresa alcanzó el límite de usuarios de su plan.',
            ]);
        }

        $user->companies()->syncWithoutDetaching([
            $company->id => ['role_id' => $role->id],
        ]);

        return back()->with('success', 'Rol asignado exitosamente');
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    /** Empresas sobre las que el usuario autenticado puede dar de alta gente. */
    protected function manageableCompanyIds(): \Illuminate\Support\Collection
    {
        $authUser = auth()->user();

        if ($authUser->is_super_admin) {
            return Company::pluck('id');
        }

        // Sólo la empresa activa: pertenecer a una empresa no da derecho a
        // administrar las demás de las que también se es miembro.
        return collect([$authUser->getCurrentCompany()?->id])->filter()->values();
    }

    protected function formData(): array
    {
        $authUser = auth()->user();
        $companyIds = $this->manageableCompanyIds();

        return [
            'user' => null,
            'companies' => Company::whereIn('id', $companyIds)->orderBy('name')->get(),
            // Un select por empresa: cada una tiene sus propios roles además
            // de los del sistema.
            'rolesByCompany' => $companyIds->mapWithKeys(fn ($id) => [
                $id => Role::assignableBy($id)->orderBy('company_id')->orderBy('name')->get(),
            ]),
            'canCreateSuperAdmin' => (bool) $authUser->is_super_admin,
        ];
    }

    /**
     * Valida la empresa y el rol con los que nace el usuario.
     *
     * Devuelve null sólo cuando un superadmin crea otro usuario de plataforma:
     * cualquier otro usuario nace dentro de una empresa, porque sin membresía
     * no puede entrar a ninguna pantalla.
     */
    protected function validateMembership(Request $request): ?array
    {
        $authUser = auth()->user();
        $companyIds = $this->manageableCompanyIds();
        $platformUser = $authUser->is_super_admin && $request->boolean('is_super_admin');

        $validated = $request->validate([
            'company_id' => [$platformUser ? 'nullable' : 'required', 'integer'],
            'role_id' => [$platformUser ? 'nullable' : 'required', 'integer'],
        ], [
            'company_id.required' => 'Debes indicar la empresa del usuario.',
            'role_id.required' => 'Debes indicar el rol del usuario.',
        ]);

        if (empty($validated['company_id'])) {
            return null;
        }

        $companyId = (int) $validated['company_id'];

        if (! $companyIds->contains($companyId)) {
            throw ValidationException::withMessages([
                'company_id' => 'No puedes dar de alta usuarios en esa empresa.',
            ]);
        }

        if (! Role::assignableBy($companyId)->whereKey($validated['role_id'])->exists()) {
            throw ValidationException::withMessages([
                'role_id' => 'Ese rol no está disponible para esa empresa.',
            ]);
        }

        return ['company_id' => $companyId, 'role_id' => (int) $validated['role_id']];
    }

    /** Un administrador sólo toca usuarios de su propia empresa. */
    protected function authorizeUser(User $user): void
    {
        $authUser = auth()->user();

        if ($authUser->is_super_admin) {
            return;
        }

        $companyId = $authUser->getCurrentCompany()?->id;

        if (! $companyId || ! $user->companies()->whereKey($companyId)->exists()) {
            abort(403);
        }
    }
}
