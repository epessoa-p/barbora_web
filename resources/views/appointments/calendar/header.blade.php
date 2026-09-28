<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar3"></i> Calendario</h1>
        <p class="text-muted mb-0">
            @if($view === 'week')
                Semana del {{ $weekStart->translatedFormat('d \d\e F') }}
                al {{ $weekEnd->translatedFormat('d \d\e F \d\e Y') }}
            @elseif($view === 'month')
                {{ $monthStart->translatedFormat('F \d\e Y') }}
            @else
                {{ $day->translatedFormat('l d \d\e F \d\e Y') }}
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group">
            <a href="{{ route('appointments.calendar', ['day' => $day->toDateString(), 'view' => 'day']) }}"
               class="btn btn-{{ $view === 'day' ? 'primary' : 'light border' }}">Día</a>
            <a href="{{ route('appointments.calendar', array_filter(['day' => $day->toDateString(), 'view' => 'week', 'personal' => $barber?->id ?? null])) }}"
               class="btn btn-{{ $view === 'week' ? 'primary' : 'light border' }}">Semana</a>
            <a href="{{ route('appointments.calendar', array_filter(['day' => $day->toDateString(), 'view' => 'month', 'personal' => $barber?->id ?? null])) }}"
               class="btn btn-{{ $view === 'month' ? 'primary' : 'light border' }}">Mes</a>
        </div>
        <a href="{{ route('appointments.index', ['day' => $day->toDateString()]) }}" class="btn btn-light border">
            <i class="bi bi-list-ul"></i> Lista
        </a>
        <a href="{{ route('appointments.create', ['day' => $day->toDateString()]) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva cita
        </a>
    </div>
</div>

@if($view === 'week')
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a href="{{ route('appointments.calendar', ['day' => $weekStart->copy()->subWeek()->toDateString(), 'view' => 'week', 'personal' => $barber?->id]) }}"
           class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
        <a href="{{ route('appointments.calendar', ['day' => $weekStart->copy()->addWeek()->toDateString(), 'view' => 'week', 'personal' => $barber?->id]) }}"
           class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
        @unless($weekStart->isSameWeek(now()))
            <a href="{{ route('appointments.calendar', ['day' => now()->toDateString(), 'view' => 'week', 'personal' => $barber?->id]) }}"
               class="btn btn-outline-secondary">Esta semana</a>
        @endunless

        <form method="GET" class="d-flex gap-2 m-0 ms-auto">
            <input type="hidden" name="view" value="week">
            <input type="hidden" name="day" value="{{ $day->toDateString() }}">
            <select name="personal" class="form-select" onchange="this.form.submit()">
                @foreach($staff as $person)
                    <option value="{{ $person->id }}" {{ $barber?->id === $person->id ? 'selected' : '' }}>
                        {{ $person->full_name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
@elseif($view === 'month')
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a href="{{ route('appointments.calendar', array_filter(['day' => $monthStart->copy()->subMonthNoOverflow()->toDateString(), 'view' => 'month', 'personal' => $barber?->id])) }}"
           class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
        <a href="{{ route('appointments.calendar', array_filter(['day' => $monthStart->copy()->addMonthNoOverflow()->toDateString(), 'view' => 'month', 'personal' => $barber?->id])) }}"
           class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
        @unless($monthStart->isSameMonth(now()))
            <a href="{{ route('appointments.calendar', array_filter(['day' => now()->toDateString(), 'view' => 'month', 'personal' => $barber?->id])) }}"
               class="btn btn-outline-secondary">Este mes</a>
        @endunless

        <form method="GET" class="d-flex gap-2 m-0 ms-auto">
            <input type="hidden" name="view" value="month">
            <input type="hidden" name="day" value="{{ $day->toDateString() }}">
            <select name="personal" class="form-select" onchange="this.form.submit()">
                <option value="">Todos los barberos</option>
                @foreach($staff as $person)
                    <option value="{{ $person->id }}" {{ $barber?->id === $person->id ? 'selected' : '' }}>
                        {{ $person->full_name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
@else
    @include('appointments.partials.day-nav', ['day' => $day, 'route' => 'appointments.calendar'])
@endif
