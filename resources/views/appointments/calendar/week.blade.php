@extends('layouts.app')

@section('title', 'Calendario semanal - Barbora')

@section('page')
@include('appointments.calendar.header', ['view' => 'week'])

@if($staff->isEmpty())
    @include('appointments.calendar.empty-staff')
@else
    @if($blocks->isNotEmpty())
        <div class="alert alert-warning py-2 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong class="small"><i class="bi bi-slash-circle"></i> Bloqueos de la semana:</strong>
                @foreach($blocks as $block)
                    <span class="badge bg-{{ $block->reasonColor() }}">
                        {{ $block->title }} · {{ $block->rangeLabel() }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="calendar-scroll">
                <div class="calendar" style="--hour-height: {{ $hourHeight }}px; --columns: 7;">

                    <div class="calendar-head">
                        <div class="calendar-gutter"></div>
                        @foreach($days as $d)
                            <div class="calendar-col-head {{ $d->isToday() ? 'is-today' : '' }}">
                                <span class="fw-semibold">{{ $d->translatedFormat('D') }}</span>
                                <small class="text-muted d-block">{{ $d->format('d/m') }}</small>
                            </div>
                        @endforeach
                    </div>

                    <div class="calendar-body">
                        <div class="calendar-gutter">
                            @for($h = $fromHour; $h < $toHour; $h++)
                                <div class="calendar-hour">{{ sprintf('%02d:00', $h) }}</div>
                            @endfor
                        </div>

                        @foreach($days as $d)
                            @php
                                $shifts = $barber?->schedules->where('active', true)->where('weekday', $d->dayOfWeekIso) ?? collect();
                            @endphp
                            <div class="calendar-col {{ $d->isToday() ? 'is-today' : '' }}">
                                @for($h = $fromHour; $h < $toHour; $h++)
                                    <div class="calendar-slot"></div>
                                @endfor

                                @foreach($shifts as $shift)
                                    @php
                                        $shiftStart = (int) substr($shift->start_time, 0, 2) + ((int) substr($shift->start_time, 3, 2)) / 60;
                                        $shiftEnd = (int) substr($shift->end_time, 0, 2) + ((int) substr($shift->end_time, 3, 2)) / 60;
                                    @endphp
                                    <div class="calendar-shift"
                                         style="top: calc(({{ $shiftStart - $fromHour }}) * var(--hour-height));
                                                height: calc(({{ $shiftEnd - $shiftStart }}) * var(--hour-height));"></div>
                                @endforeach

                                @foreach($appointments->get($d->toDateString(), collect()) as $appointment)
                                    @php
                                        $top = $appointment->starts_at->hour + $appointment->starts_at->minute / 60 - $fromHour;
                                        $height = $appointment->durationMinutes() / 60;
                                    @endphp
                                    <a href="{{ route('appointments.show', $appointment) }}"
                                       class="calendar-event calendar-event-{{ $appointment->status }}"
                                       style="top: calc({{ $top }} * var(--hour-height));
                                              height: calc({{ $height }} * var(--hour-height));
                                              --event-color: {{ $barber?->agenda_color ?? '#2563eb' }};"
                                       title="{{ $appointment->rangeLabel() }} · {{ $appointment->client?->full_name }}">
                                        <span class="calendar-event-time">{{ $appointment->starts_at->format('H:i') }}</span>
                                        <span class="calendar-event-title">{{ $appointment->client?->full_name }}</span>
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
