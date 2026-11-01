@extends('layouts.app')

@section('page')
<h1><i class="bi bi-building"></i> {{ $company->name }}</h1>

{{-- Recién creada con «Dejar lista para usar»: las credenciales del
     responsable, una sola vez (vienen en el flash; al recargar ya no están). --}}
@if($credentials = session('credentials'))
    @php
        $loginUrl = route('login');
        $credentialsText = implode("\n", [
            $company->name,
            'Acceso (web): '.$loginUrl,
            'App: Barbora (Android)',
            'Usuario: '.$credentials['username'],
            'Correo: '.$credentials['email'],
            'Contraseña: '.$credentials['password'],
        ]);
    @endphp
    <div class="card border-success mt-4" id="company-credentials">
        <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-key"></i> Credenciales del responsable</h5>
            <button type="button" class="btn btn-success btn-sm" data-copy="{{ $credentialsText }}">
                <i class="bi bi-clipboard"></i> Copiar todo
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="bi bi-exclamation-triangle text-warning"></i>
                Cópialas ahora: la contraseña solo se muestra esta vez. Si se pierde, se cambia desde Usuarios.
            </p>
            <div class="row g-2">
                @foreach([
                    'Acceso (web)' => $loginUrl,
                    'Usuario' => $credentials['username'],
                    'Correo' => $credentials['email'],
                    'Contraseña' => $credentials['password'],
                ] as $label => $value)
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">{{ $label }}</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="{{ $value }}" readonly data-no-upper>
                            <button type="button" class="btn btn-outline-secondary" data-copy="{{ $value }}" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            <hr>
            <p class="small mb-0">
                <strong>Quedó creado:</strong>
                sucursal <strong>{{ $credentials['branch'] }}</strong> (con su almacén),
                responsable <strong>{{ $credentials['owner'] }}</strong> con cargo ADMINISTRADOR,
                caja <strong>{{ $credentials['caja'] }}</strong> y los métodos de pago.
            </p>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('#company-credentials [data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const text = button.dataset.copy;
                try {
                    await navigator.clipboard.writeText(text);
                } catch {
                    // Sin HTTPS el portapapeles moderno no está: se cae al método clásico.
                    const area = Object.assign(document.createElement('textarea'), { value: text });
                    document.body.appendChild(area);
                    area.select();
                    document.execCommand('copy');
                    area.remove();
                }
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copiado';
                setTimeout(() => { button.innerHTML = original; }, 1500);
            });
        });
    </script>
    @endpush
@endif

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                <p><strong>{{ $company->tax_id_label ?? 'NIT' }}:</strong> {{ $company->tax_id ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $company->email ?? '-' }}</p>
                <p><strong>Teléfono:</strong> {{ $company->phone ?? '-' }}</p>
                <p><strong>Dirección:</strong> {{ $company->address ?? '-' }}</p>
                <p><strong>País:</strong> {{ config("barbora.countries.{$company->country}.name", $company->country) }}</p>
                <p><strong>Moneda:</strong> {{ $company->currency }} ({{ \App\Support\Money::symbol($company) }})</p>
                <p><strong>Zona horaria:</strong> {{ $company->timezone }}</p>
                <p class="mb-0"><strong>Estado:</strong> <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">{{ $company->active ? 'Activo' : 'Inactivo' }}</span></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        @php($subscription = $company->subscription)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Suscripción</h5>
                @if($subscription)
                    <span class="badge bg-{{ $subscription->allowsWrite() ? 'success' : ($subscription->allowsRead() ? 'warning' : 'danger') }}">
                        {{ $subscription->statusLabel() }}
                    </span>
                @else
                    <span class="badge bg-danger">Sin plan</span>
                @endif
            </div>
            <div class="card-body">
                @if($subscription)
                    <p class="mb-1"><strong>Plan:</strong> {{ $subscription->plan?->name ?? '-' }}</p>
                    @if($subscription->onTrial())
                        <p class="mb-1"><strong>Prueba hasta:</strong> {{ $subscription->trial_ends_at->translatedFormat('d/m/Y') }}</p>
                    @elseif($subscription->current_period_end)
                        <p class="mb-1"><strong>Periodo hasta:</strong> {{ $subscription->current_period_end->translatedFormat('d/m/Y') }}</p>
                    @endif
                    <p class="mb-1">
                        <strong>Sucursales:</strong>
                        {{ $company->usageFor('branches') }} / {{ $company->effectiveLimit('branches') ?? '∞' }}
                    </p>
                    <p class="mb-3">
                        <strong>Usuarios:</strong>
                        {{ $company->usageFor('users') }} / {{ $company->effectiveLimit('users') ?? '∞' }}
                    </p>
                @else
                    <p class="text-muted">Esta empresa no puede entrar hasta que le asignes un plan.</p>
                @endif

                <a href="{{ route('companies.subscription.edit', $company) }}" class="btn btn-outline-primary w-100">
                    <i class="bi bi-receipt"></i> {{ $subscription ? 'Gestionar suscripción' : 'Asignar un plan' }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('set-company', $company->id) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-eye"></i> Entrar como esta empresa
                    </button>
                </form>
                <a href="{{ route('companies.edit', $company) }}" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <form action="{{ route('companies.destroy', $company) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('¿Estás seguro?')">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Usuarios de la Empresa</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->pivot->role_id ? \App\Models\Role::find($user->pivot->role_id)->name : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No hay usuarios en esta empresa</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<a href="{{ route('companies.index') }}" class="btn btn-secondary mt-4">
    <i class="bi bi-arrow-left"></i> Volver
</a>
@endsection
