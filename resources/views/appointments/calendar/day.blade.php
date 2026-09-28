@extends('layouts.app')

@section('title', 'Calendario - Barbora')

@section('page')
@include('appointments.calendar.header', ['view' => 'day'])

@if($staff->isEmpty())
    @include('appointments.calendar.empty-staff')
@else
    {{-- Bloqueos del día: lo primero que hay que ver antes de citar a nadie. --}}
    @if($blocks->isNotEmpty())
        <div class="alert alert-warning py-2 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong class="small"><i class="bi bi-slash-circle"></i> No se atiende:</strong>
                @foreach($blocks as $block)
                    <span class="badge bg-{{ $block->reasonColor() }}">
                        {{ $block->isCompanyWide() ? 'Toda la barbería' : $block->personal?->full_name }}
                        · {{ $block->title }}
                        @unless($block->all_day)
                            ({{ $block->starts_at->format('H:i') }}–{{ $block->ends_at->format('H:i') }})
                        @endunless
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="calendar-scroll">
                <div class="calendar" style="--hour-height: {{ $hourHeight }}px; --columns: {{ $staff->count() }};">

                    {{-- Cabecera: un barbero por columna --}}
                    <div class="calendar-head">
                        <div class="calendar-gutter"></div>
                        @foreach($staff as $person)
                            <div class="calendar-col-head">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="d-inline-block rounded-circle"
                                          style="width:.6rem;height:.6rem;background:{{ $person->agenda_color }};"></span>
                                    <span class="fw-semibold">{{ $person->full_name }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="calendar-body">
                        {{-- Horas --}}
                        <div class="calendar-gutter">
                            @for($h = $fromHour; $h < $toHour; $h++)
                                <div class="calendar-hour">{{ sprintf('%02d:00', $h) }}</div>
                            @endfor
                        </div>

                        @foreach($staff as $person)
                            @php
                                // En bloque y no con @php(...): la forma en línea compila mal
                                // cuando la expresión encadena llamadas con paréntesis.
                                $worksToday = $person->schedules->where('active', true)->where('weekday', $day->dayOfWeekIso);
                            @endphp
                            <div class="calendar-col">
                                {{-- Líneas de hora --}}
                                @for($h = $fromHour; $h < $toHour; $h++)
                                    <div class="calendar-slot"></div>
                                @endfor

                                {{-- Turnos: lo que queda fuera es horario no laborable --}}
                                @foreach($worksToday as $shift)
                                    @php
                                        $shiftStart = (int) substr($shift->start_time, 0, 2) + ((int) substr($shift->start_time, 3, 2)) / 60;
                                        $shiftEnd = (int) substr($shift->end_time, 0, 2) + ((int) substr($shift->end_time, 3, 2)) / 60;
                                    @endphp
                                    <div class="calendar-shift"
                                         style="top: calc(({{ $shiftStart - $fromHour }}) * var(--hour-height));
                                                height: calc(({{ $shiftEnd - $shiftStart }}) * var(--hour-height));"></div>
                                @endforeach

                                {{-- Citas --}}
                                @foreach($appointments->get($person->id, collect()) as $appointment)
                                    @php
                                        $top = $appointment->starts_at->hour + $appointment->starts_at->minute / 60 - $fromHour;
                                        $height = $appointment->durationMinutes() / 60;
                                    @endphp
                                    <a href="{{ route('appointments.show', $appointment) }}"
                                       class="calendar-event calendar-event-{{ $appointment->status }}"
                                       style="top: calc({{ $top }} * var(--hour-height));
                                              height: calc({{ $height }} * var(--hour-height));
                                              --event-color: {{ $person->agenda_color }};"
                                       title="{{ $appointment->rangeLabel() }} · {{ $appointment->client?->full_name }} · {{ $appointment->servicesLabel() }}">
                                        <span class="calendar-event-time">{{ $appointment->starts_at->format('H:i') }}</span>
                                        <span class="calendar-event-title">{{ $appointment->client?->full_name }}</span>
                                        <span class="calendar-event-meta">{{ $appointment->servicesLabel() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('appointments.calendar.legend')
@endif
@endsection
