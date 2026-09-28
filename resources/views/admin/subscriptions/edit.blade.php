@extends('layouts.app')

@section('title', 'Suscripción - Barbora')

@php
    $overrideFeatures = old('features_override', $subscription?->features_override);
    $hasFeatureOverride = old('override_features', $subscription?->features_override !== null);
    $currentFeatures = $overrideFeatures ?? ($subscription?->plan?->features ?? []);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1"><i class="bi bi-receipt"></i> Suscripción</h1>
        <p class="text-muted mb-0">{{ $company->name }}</p>
    </div>
    <a href="{{ route('companies.show', $company) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver a la empresa
    </a>
</div>

<form action="{{ route('companies.subscription.update', $company) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Plan y vigencia</h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="plan_id" class="form-label">Plan</label>
                            <select id="plan_id" name="plan_id" class="form-select" required>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}"
                                            {{ (string) old('plan_id', $subscription?->plan_id) === (string) $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }}{{ $plan->active ? '' : ' (inactivo)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Estado</label>
                            <select id="status" name="status" class="form-select" required>
                                @foreach(\App\Models\Subscription::STATUSES as $value => $label)
                                    <option value="{{ $value }}"
                                            {{ old('status', $subscription?->status ?? 'trial') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="trial_ends_at" class="form-label">Fin de la prueba</label>
                            <input type="date" id="trial_ends_at" name="trial_ends_at" class="form-control"
                                   value="{{ old('trial_ends_at', $subscription?->trial_ends_at?->format('Y-m-d')) }}">
                            <small class="text-muted">Se usa cuando el estado es «En prueba».</small>
                        </div>

                        <div class="col-md-6">
                            <label for="current_period_end" class="form-label">Fin del periodo pagado</label>
                            <input type="date" id="current_period_end" name="current_period_end" class="form-control"
                                   value="{{ old('current_period_end', $subscription?->current_period_end?->format('Y-m-d')) }}">
                            <small class="text-muted">Se usa cuando el estado es «Activa».</small>
                        </div>

                        <div class="col-md-6">
                            <label for="grace_days" class="form-label">Días de gracia</label>
                            <input type="number" min="0" max="90" id="grace_days" name="grace_days" class="form-control"
                                   value="{{ old('grace_days', $subscription?->grace_days ?? 3) }}" required>
                            <small class="text-muted">Tras vencer: puede consultar, pero no registrar cambios.</small>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notas internas</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $subscription?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">Límites personalizados</h6>
                    <p class="text-muted small mb-3">
                        Deja el campo vacío para heredar el límite del plan. Un <code>0</code> significa «ninguno permitido».
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="max_users_override" class="form-label">Usuarios</label>
                            <input type="number" min="0" id="max_users_override" name="max_users_override" class="form-control"
                                   value="{{ old('max_users_override', $subscription?->max_users_override) }}"
                                   placeholder="Plan: {{ $subscription?->plan?->max_users ?? 'ilimitado' }}">
                            <small class="text-muted">
                                En uso: {{ $usage['users']['usage'] }} / {{ $usage['users']['limit'] ?? '∞' }}
                            </small>
                        </div>

                        <div class="col-md-4">
                            <label for="max_branches_override" class="form-label">Sucursales</label>
                            <input type="number" min="0" id="max_branches_override" name="max_branches_override" class="form-control"
                                   value="{{ old('max_branches_override', $subscription?->max_branches_override) }}"
                                   placeholder="Plan: {{ $subscription?->plan?->max_branches ?? 'ilimitado' }}">
                            <small class="text-muted">
                                En uso: {{ $usage['branches']['usage'] }} / {{ $usage['branches']['limit'] ?? '∞' }}
                            </small>
                        </div>

                        <div class="col-md-4">
                            <label for="max_products_override" class="form-label">Productos</label>
                            <input type="number" min="0" id="max_products_override" name="max_products_override" class="form-control"
                                   value="{{ old('max_products_override', $subscription?->max_products_override) }}"
                                   placeholder="Plan: {{ $subscription?->plan?->max_products ?? 'ilimitado' }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">Módulos personalizados</h6>
                    <p class="text-muted small mb-3">
                        Sin activar, esta empresa usa exactamente los módulos de su plan. Al activarlo,
                        la lista de abajo <strong>reemplaza</strong> por completo a la del plan.
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="override_features" name="override_features" value="1"
                               {{ $hasFeatureOverride ? 'checked' : '' }}>
                        <label class="form-check-label" for="override_features">Personalizar los módulos de esta empresa</label>
                    </div>

                    <div class="row g-2" id="features-override-box">
                        @foreach(\App\Models\Plan::MODULES as $slug => $label)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features_override[]"
                                           value="{{ $slug }}" id="override-{{ $slug }}"
                                           {{ in_array($slug, $currentFeatures, true) ? 'checked' : '' }}
                                           {{ $hasFeatureOverride ? '' : 'disabled' }}>
                                    <label class="form-check-label" for="override-{{ $slug }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Estado actual</h6>

                    @if($subscription)
                        <p class="mb-1"><strong>Situación:</strong> {{ $subscription->statusLabel() }}</p>
                        <p class="mb-1"><strong>Puede entrar:</strong> {{ $subscription->allowsRead() ? 'Sí' : 'No' }}</p>
                        <p class="mb-1"><strong>Puede registrar cambios:</strong> {{ $subscription->allowsWrite() ? 'Sí' : 'No' }}</p>
                        @if($graceEnd = $subscription->graceEndsAt())
                            <p class="mb-1"><strong>Gracia hasta:</strong> {{ $graceEnd->translatedFormat('d/m/Y') }}</p>
                        @endif
                        <hr>
                        <p class="mb-2"><strong>Módulos efectivos:</strong></p>
                        @forelse($subscription->effectiveFeatures() as $feature)
                            <span class="badge bg-light text-dark border">{{ \App\Models\Plan::MODULES[$feature] ?? $feature }}</span>
                        @empty
                            <span class="text-muted">Ninguno</span>
                        @endforelse
                    @else
                        <p class="text-muted mb-0">
                            Esta empresa todavía no tiene suscripción, así que no puede entrar al sistema.
                            Asigna un plan y guarda para activarla.
                        </p>
                    @endif
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar suscripción
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // Los módulos personalizados solo se envían si el operador activa el override.
    const overrideSwitch = document.getElementById('override_features');
    const featureBox = document.getElementById('features-override-box');

    overrideSwitch?.addEventListener('change', function () {
        featureBox.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
            input.disabled = !overrideSwitch.checked;
        });
    });
</script>
@endpush
