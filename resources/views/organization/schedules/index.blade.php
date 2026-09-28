@extends('layouts.app')

@section('title', 'Horarios - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar-week"></i> Horarios</h1>
        <p class="text-muted mb-0">Quién trabaja cada día. Es lo que la agenda usará para ofrecer huecos.</p>
    </div>
    <a href="{{ route('personal.index') }}" class="btn btn-light border">
        <i class="bi bi-person-vcard"></i> Personal
    </a>
</div>

@if($staff->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-person-badge text-muted" style="font-size: 2.5rem;"></i>
            <h6 class="fw-semibold mt-3">Todavía no hay nadie que atienda citas</h6>
            <p class="text-muted mb-3">
                Marca «Atiende citas» en la ficha de quienes cortan para que aparezcan aquí
                y en la agenda.
            </p>
            <a href="{{ route('personal.index') }}" class="btn btn-primary">Ir a Personal</a>
        </div>
    </div>
@else
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 schedule-grid">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Barbero</th>
                            @foreach($weekdays as $number => $label)
                                <th class="text-center">{{ mb_substr($label, 0, 3) }}</th>
                            @endforeach
                            <th class="text-end pe-3">Semana</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($staff as $person)
                            @php
                                $byDay = $person->schedules->where('active', true)->groupBy('weekday');
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('schedules.show', $person) }}"
                                       class="d-flex align-items-center gap-2 text-decoration-none">
                                        @if($person->photoUrl())
                                            <img src="{{ $person->photoUrl() }}" alt="" class="staff-avatar">
                                        @else
                                            <span class="staff-avatar staff-avatar-initials"
                                                  style="background: {{ $person->agenda_color }};">
                                                {{ $person->initials() }}
                                            </span>
                                        @endif
                                        <span>
                                            <span class="fw-semibold d-block">{{ $person->full_name }}</span>
                                            <small class="text-muted">{{ $person->specialty ?: $person->cargo?->name }}</small>
                                        </span>
                                    </a>
                                </td>

                                @foreach($weekdays as $number => $label)
                                    <td class="text-center">
                                        @forelse($byDay->get($number, collect()) as $schedule)
                                            <span class="schedule-chip" title="{{ $schedule->branchLabel() }}">
                                                {{ $schedule->rangeLabel() }}
                                            </span>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>
                                @endforeach

                                <td class="text-end pe-3 text-nowrap">
                                    @php($minutes = $person->weeklyMinutes())
                                    @if($minutes > 0)
                                        <span class="fw-semibold">{{ intdiv($minutes, 60) }} h</span>
                                        @if($minutes % 60) <span class="text-muted">{{ $minutes % 60 }} min</span> @endif
                                    @else
                                        <span class="text-muted">Sin horario</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
