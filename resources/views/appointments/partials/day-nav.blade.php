{{-- Navegación de fecha, compartida por el listado y el calendario. --}}
@php($params = request()->except(['day', 'page']))

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="{{ route($route, $params + ['day' => $day->copy()->subDay()->toDateString()]) }}"
       class="btn btn-light border" title="Día anterior">
        <i class="bi bi-chevron-left"></i>
    </a>

    <form method="GET" class="d-flex gap-2 m-0">
        @foreach($params as $key => $value)
            @if(! is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="date" name="day" class="form-control" value="{{ $day->toDateString() }}"
               onchange="this.form.submit()">
    </form>

    <a href="{{ route($route, $params + ['day' => $day->copy()->addDay()->toDateString()]) }}"
       class="btn btn-light border" title="Día siguiente">
        <i class="bi bi-chevron-right"></i>
    </a>

    @unless($day->isToday())
        <a href="{{ route($route, $params + ['day' => now()->toDateString()]) }}" class="btn btn-outline-secondary">
            Hoy
        </a>
    @endunless
</div>
