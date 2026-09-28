@extends('layouts.app')

@section('title', 'Dashboard')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold mb-1">Dashboard</h4>
            <p class="text-muted mb-0">Resumen general del sistema</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalUsers }}</div>
                        <div class="kpi-label">Usuarios</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-people"></i> Registrados</div>
                    </div>
                    <div class="kpi-icon" style="background: #7c4dff;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalPersonal }}</div>
                        <div class="kpi-label">Personal</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-person-vcard"></i> Empleados</div>
                    </div>
                    <div class="kpi-icon" style="background: #ff6d00;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalBranches }}</div>
                        <div class="kpi-label">Sucursales</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-diagram-2"></i> Activas</div>
                    </div>
                    <div class="kpi-icon" style="background: #00c853;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalCajas }}</div>
                        <div class="kpi-label">Cajas</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-safe"></i> Registradas</div>
                    </div>
                    <div class="kpi-icon" style="background: #0288d1;">
                        <i class="bi bi-safe2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-scissors text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 fw-semibold">Bienvenido a Barbora</h5>
            <p class="text-muted mb-0">
                La base está lista: empresas, sucursales, cajas, personal, planes y permisos.
                Los módulos de barbería —agenda, ventas y comisiones— llegan en la siguiente fase.
            </p>
        </div>
    </div>
</div>
@endsection
