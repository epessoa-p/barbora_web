<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    public function index()
    {
        // El CompanyScope ya filtra por la empresa activa.
        $query = Personal::with(['cargo.role', 'company', 'user'])->latest();

        if (auth()->user()->is_super_admin && request('company_id')) {
            $query->forCompany((int) request('company_id'));
        }

        $personals = $query->paginate(15);

        return view('admin.personal.index', compact('personals'));
    }

    public function create()
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin ? request()->integer('company_id') : $authUser->getCurrentCompany()?->id;

        $cargos = Cargo::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        return view('admin.personal.create', [
            'cargos' => $cargos,
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id')
            : (int) $authUser->getCurrentCompany()?->id;

        // La cuenta de acceso es opcional: no todo personal entra al sistema.
        $wantsUser = $request->boolean('create_user');

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'cargo_id' => ['required', 'exists:cargos,id'],
                'full_name' => ['required', 'string', 'max:255'],
                'id_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'hire_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
                'active' => ['nullable', 'boolean'],
                ...$this->barberRules(),
                ...$this->userRules($wantsUser),
            ]);

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            DB::transaction(function () use ($validated, $companyId, $cargo, $request, $wantsUser) {
                $userId = null;

                if ($wantsUser) {
                    $user = User::create([
                        'name' => trim($validated['username']),
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'phone' => $validated['phone'] ?? null,
                        'active' => $request->boolean('active', true),
                        'is_super_admin' => false,
                    ]);

                    $user->companies()->syncWithoutDetaching([
                        $companyId => ['role_id' => $cargo->role_id, 'active' => true],
                    ]);

                    $userId = $user->id;
                }

                Personal::create([
                    'company_id' => $companyId,
                    'cargo_id' => $cargo->id,
                    'user_id' => $userId,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', true),
                    'photo' => $this->storePhoto($request),
                    'specialty' => $validated['specialty'] ?? null,
                    'agenda_color' => $validated['agenda_color'] ?? '#2563eb',
                    'bookable' => $request->boolean('bookable'),
                ]);
            });

            return redirect()->route('personal.index')->with('success',
                $wantsUser ? 'Personal creado con su cuenta de acceso.' : 'Personal creado (sin cuenta de acceso).');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear personal', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el personal.']);
        }
    }

    public function edit(Personal $personal)
    {
        $this->authorizePersonal($personal);
        $authUser = auth()->user();

        $companyId = $authUser->is_super_admin
            ? $personal->company_id
            : $authUser->getCurrentCompany()?->id;

        $cargos = Cargo::where('company_id', $companyId)
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        $personal->load('user', 'cargo.role');

        return view('admin.personal.edit', [
            'personal' => $personal,
            'cargos' => $cargos,
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $personal->company_id)
            : (int) $personal->company_id;

        // Ya tiene cuenta → se edita. No la tiene pero la marcan → se crea ahora.
        $hasUser = (bool) $personal->user_id;
        $wantsUser = $hasUser || $request->boolean('create_user');

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'cargo_id' => ['required', 'exists:cargos,id'],
                'full_name' => ['required', 'string', 'max:255'],
                'id_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'hire_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
                'active' => ['nullable', 'boolean'],
                ...$this->barberRules(),
                ...$this->userRules($wantsUser, $personal->user_id, requirePassword: ! $hasUser),
            ]);

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            DB::transaction(function () use ($validated, $personal, $cargo, $companyId, $request, $hasUser, $wantsUser) {
                $user = $personal->user;

                if ($wantsUser) {
                    $userData = [
                        'name' => trim($validated['username']),
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'active' => $request->boolean('active', false),
                    ];

                    if (!empty($validated['password'])) {
                        $userData['password'] = Hash::make($validated['password']);
                    }

                    if ($hasUser) {
                        $user->update($userData);
                    } else {
                        // Alta de la cuenta sobre un personal que no la tenía.
                        $user = User::create($userData + [
                            'password' => Hash::make($validated['password']),
                            'is_super_admin' => false,
                        ]);
                    }

                    $user->companies()->syncWithoutDetaching([
                        $companyId => ['role_id' => $cargo->role_id, 'active' => true],
                    ]);
                }

                $personal->update([
                    'company_id' => $companyId,
                    'cargo_id' => $cargo->id,
                    'user_id' => $user?->id,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', false),
                    // Sin foto nueva se conserva la que ya tenía.
                    'photo' => $this->storePhoto($request, $personal) ?? $personal->photo,
                    'specialty' => $validated['specialty'] ?? null,
                    'agenda_color' => $validated['agenda_color'] ?? '#2563eb',
                    'bookable' => $request->boolean('bookable'),
                ]);
            });

            return redirect()->route('personal.index')->with('success', 'Personal actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar personal', ['personal_id' => $personal->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el personal.']);
        }
    }

    public function destroy(Personal $personal)
    {
        $this->authorizePersonal($personal);

        DB::transaction(function () use ($personal) {
            $user = $personal->user;
            $personal->delete();
            if ($user) {
                $user->delete();
            }
        });

        return redirect()->route('personal.index')->with('success', 'Registro de personal eliminado.');
    }

    protected function authorizePersonal(Personal $personal): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $personal->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    /** Campos de la ficha de barbero, compartidos por alta y edición. */
    protected function barberRules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'agenda_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bookable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Guarda la foto nueva y borra la anterior. Devuelve null si no se subió
     * ninguna, para que quien llama decida si conservar la que había.
     */
    protected function storePhoto(Request $request, ?Personal $personal = null): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $path = $request->file('photo')->store('personal', 'public');

        if ($personal?->photo) {
            Storage::disk('public')->delete($personal->photo);
        }

        return $path;
    }

    /**
     * Reglas de la cuenta de acceso, solo cuando se va a crear/editar una.
     *
     * El nombre de usuario se guarda en users.name y sirve para iniciar sesión
     * (ver LoginController), así que debe ser único. En edición se ignora el
     * propio usuario. La contraseña es obligatoria al crear y opcional al editar.
     *
     * @return array<string, mixed>
     */
    protected function userRules(bool $wantsUser, ?int $ignoreUserId = null, bool $requirePassword = true): array
    {
        if (! $wantsUser) {
            return [];
        }

        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($ignoreUserId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
