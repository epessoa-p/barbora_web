@extends('layouts.app')

@section('title', 'Calendario del mes - Barbora')

@section('page')
@include('appointments.calendar.header', ['view' => 'month'])

@if($staff->isEmpty())
    @include('appointments.calendar.empty-staff')
@else
    <div class="row g-3 mb-3">
        @php
            $cards = [
                ['label' => 'Citas del mes', 'value' => $totals['appointments']],
                ['label' => 'Atendidas', 'value' => $totals['attended']],
                ['label' => 'Días con bloqueo', 'value' => $totals['blocked']],
            ];
        @endphp
        @foreach($cards as $card)
            <div class="col-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-2">
                        <p class="text-muted small mb-0">{{ $card['label'] }}</p>
                        <p class="h5 fw-bold mb-0">{{ $card['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="month-grid">
                <thead>
                    <tr>
                        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($weeks as $week)
                        <tr>
                            @foreach($week as $cell)
                                @php
                                    // Un bloqueo que cubre el día entero lo pinta como cerrado.
                                    $closed = $cell['blocks']->first(fn ($b) => $b->coversWholeDay($cell['date']));
                                @endphp
                                <td class="month-cell
                                           {{ $cell['inMonth'] ? '' : 'month-cell-outside' }}
                                           {{ $cell['isToday'] ? 'month-cell-today' : '' }}
                                           {{ $closed ? 'month-cell-closed' : '' }}">
                                    <a href="{{ route('appointments.calendar', array_filter(['day' => $cell['key'], 'view' => 'day'])) }}"
                                       class="month-cell-link">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="month-cell-date">{{ $cell['date']->day }}</span>
                                            @if($cell['total'] > 0)
                                                <span class="badge bg-primary">{{ $cell['total'] }}</span>
                                            @endif
                                        </div>

                                        @foreach($cell['blocks'] as $block)
                                            <span class="month-tag bg-{{ $block->reasonColor() }}-subtle text-{{ $block->reasonColor() }}"
                                                  title="{{ $block->title }} · {{ $block->scopeLabel() }}">
                                                <i class="bi bi-slash-circle"></i>
                                                {{ $block->isCompanyWide() ? $block->title : $block->personal?->full_name }}
                                            </span>
                                        @endforeach

                                        @if($cell['attended'] > 0)
                                            <span class="month-note text-success">
                                                {{ $cell['attended'] }} atendida{{ $cell['attended'] === 1 ? '' : 's' }}
                                            </span>
                                        @endif
                                        @if($cell['cancelled'] > 0)
                                            <span class="month-note text-muted">
                                                {{ $cell['cancelled'] }} cancelada{{ $cell['cancelled'] === 1 ? '' : 's' }}
                                            </span>
                                        @endif
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        <i class="bi bi-info-circle"></i>
        El número es cuántas citas ocupan hueco ese día. Pulsa un día para ver el detalle.
    </p>
@endif
@endsection
