@extends('layouts.app')

@section('title', 'Usuarios - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-people"></i> Usuarios</h1>
        <p class="text-muted mb-0">
            @if(auth()->user()->is_super_admin)
                Cuentas de acceso de todas las barberías.
            @else
                Quiénes pueden entrar a {{ $tenantCompany?->name }}.
            @endif
        </p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo usuario
    </a>
</div>

@if(auth()->user()->is_super_admin)
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="company_id" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las barberías</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ (int) $selectedCompany === $company->id ? 'selected' : '' }}>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Usuario</th>
                        <th>{{ auth()->user()->is_super_admin ? 'Barbería y rol' : 'Rol' }}</th>
                        <th>Teléfono</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="ps-3">
                                <span class="fw-semibold">{{ $user->name }}</span>
                                <small class="text-muted d-block">{{ $user->email }}</small>
                            </td>
                            <td>
                                @if($user->is_super_admin)
                                    <span class="badge bg-danger">Operador de la plataforma</span>
                                @endif

                                {{-- Un usuario puede estar en varias barberías, con un rol en cada una. --}}
                                @foreach($user->companies as $membership)
                                    <div class="d-flex align-items-center gap-2 {{ $loop->first ? '' : 'mt-1' }}">
                                        @if(auth()->user()->is_super_admin)
                                            <span class="small">{{ $membership->name }}</span>
                                        @endif
                                        <span class="badge bg-primary-subtle text-primary">
                                            {{ $roleNames[$membership->pivot->role_id] ?? 'Sin rol' }}
                                        </span>
                                        @unless($membership->pivot->active)
                                            <span class="badge bg-secondary" title="Tiene la cuenta, pero no puede entrar a esta barbería">
                                                Sin acceso
                                            </span>
                                        @endunless
                                    </div>
                                @endforeach

                                @if(! $user->is_super_admin && $user->companies->isEmpty())
                                    <span class="badge bg-warning text-dark" title="No puede entrar a ninguna pantalla">
                                        Sin barbería
                                    </span>
                                @endif
                            </td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge {{ $user->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $user->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if(auth()->id() !== $user->id)
                                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar a {{ $user->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No hay usuarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $users->links() }}
</div>

<p class="text-muted small mt-2">
    <i class="bi bi-info-circle"></i>
    La cuenta de usuario da acceso a la barbería. La ficha de <strong>Personal</strong> es aparte:
    es para quien atiende (horario, agenda, comisiones), y un administrador puede no tenerla.
</p>
@endsection
