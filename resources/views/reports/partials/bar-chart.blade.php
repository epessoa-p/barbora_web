{{--
    Gráfico de barras en CSS puro.

    Sin librería de charts a propósito: son barras proporcionales a un máximo,
    se imprimen bien y no añaden una dependencia de JavaScript al proyecto.
    Espera $series: [['label' => '12/09', 'total' => 340.0, 'sales' => 4], …]
--}}
@php
    $max = (float) $series->max('total') ?: 1;
@endphp

@if($series->isEmpty() || $series->sum('total') <= 0)
    <p class="text-muted text-center py-4 mb-0">Sin ventas en el periodo.</p>
@else
    <div class="bar-chart" style="--bar-count: {{ $series->count() }};">
        @foreach($series as $point)
            @php $height = round(((float) $point['total'] / $max) * 100, 2); @endphp
            <div class="bar-chart-col"
                 title="{{ $point['label'] }} · {{ \App\Support\Money::format($point['total'], $company) }} · {{ $point['sales'] }} {{ $point['sales'] === 1 ? 'venta' : 'ventas' }}">
                <div class="bar-chart-track">
                    <div class="bar-chart-bar" style="height: {{ max($height, $point['total'] > 0 ? 2 : 0) }}%;"></div>
                </div>
                <span class="bar-chart-label">{{ $point['label'] }}</span>
            </div>
        @endforeach
    </div>
@endif
