<div class="d-flex align-items-center gap-3 flex-wrap mt-3 small text-muted">
    <span class="d-inline-flex align-items-center gap-2">
        <span class="legend-swatch legend-shift"></span> Turno de trabajo
    </span>
    @foreach(['reservada' => 'Reservada', 'confirmada' => 'Confirmada', 'atendida' => 'Atendida'] as $status => $label)
        <span class="d-inline-flex align-items-center gap-2">
            <span class="legend-swatch calendar-event-{{ $status }}" style="--event-color: #6b7280;"></span> {{ $label }}
        </span>
    @endforeach
    <span class="ms-auto">Las citas canceladas y las ausencias liberan el hueco y no se pintan.</span>
</div>
