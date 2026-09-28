@extends('layouts.app')

@section('title', 'Bloqueos de agenda - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-slash-circle"></i> Bloqueos de agenda</h1>
        <p class="text-muted mb-0">Vacaciones, feriados y descansos: cuándo no se puede reservar.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('appointments.calendar', ['view' => 'month']) }}" class="btn btn-light border">
            <i class="bi bi-calendar3"></i> Calendario
        </a>
        @if(auth()->user()->hasPermissionInCompany('agenda_blocks.create', $tenantCompany))
            <a href="{{ route('agenda-blocks.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo bloqueo
            </a>
        @endif
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <div class="btn-group">
            <a href="{{ route('agenda-blocks.index', array_filter(['personal' => request('personal')])) }}"
               class="btn btn-sm btn-{{ $scope === 'proximos' ? 'primary' : 'light border' }}">Próximos</a>
            <a href="{{ route('agenda-blocks.index', array_filter(['scope' => 'pasados', 'personal' => request('personal')])) }}"
               class="btn btn-sm btn-{{ $scope === 'pasados' ? 'primary' : 'light border' }}">Pasados</a>
        </div>
    </div>
    <div class="col-auto">
        @if($scope === 'pasados')<input type="hidden" name="scope" value="pasados">@endif
        <select name="personal" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach($staff as $person)
                <option value="{{ $person->id }}" {{ (string) request('personal') === (string) $person->id ? 'selected' : '' }}>
                    {{ $person->full_name }}
                </option>
            @endforeach
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bloqueo</th>
                        <th>Afecta a</th>
                        <th>Periodo</th>
                        <th>Sucursal</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($blocks as $block)
                        <tr>
                            <td class="ps-3">
                                <span class="fw-semibold">{{ $block->title }}</span>
                                <span class="badge bg-{{ $block->reasonColor() }} ms-1">{{ $block->reasonLabel() }}</span>
                                @if($block->notes)
                                    <small class="text-muted d-block">{{ $block->notes }}</small>
                                @endif
                            </td>
                            <td>
                                @if($block->isCompanyWide())
                                    <span class="badge bg-danger-subtle text-danger">
                                        <i class="bi bi-shop"></i> Toda la barbería
                                    </span>
                                @else
                                    {{ $block->scopeLabel() }}
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $block->rangeLabel() }}</td>
                            <td class="text-muted">{{ $block->branch?->name ?? 'Todas' }}</td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                                    @if(auth()->user()->hasPermissionInCompany('agenda_blocks.edit', $tenantCompany))
                                        <a href="{{ route('agenda-blocks.edit', $block) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermissionInCompany('agenda_blocks.delete', $tenantCompany))
                                        <form action="{{ route('agenda-blocks.destroy', $block) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar «{{ $block->title }}»? Ese periodo volverá a estar disponible.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                {{ $scope === 'pasados' ? 'No hay bloqueos pasados.' : 'No hay bloqueos próximos.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $blocks->links() }}
</div>

<p class="text-muted small mt-2">
    <i class="bi bi-info-circle"></i>
    Un bloqueo impide reservar citas nuevas en ese periodo, pero no cancela las que ya existían.
</p>
@endsection
