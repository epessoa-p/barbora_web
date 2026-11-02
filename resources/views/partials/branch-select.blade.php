{{--
    Selector de sucursal, igual en todos los formularios.

    · Con una sola sucursal (o ninguna) no se muestra: va oculto con esa
      sucursal, o vacío si $emptyWhenSingle (filtros tipo «Todas»).
    · El valor propuesto lo decide quien lo incluye; lo normal es la sucursal
      del usuario: Branch::preferredIdFor(auth()->user(), $companyId).

    Parámetros: $branches, $selected, y opcionales $name ('branch_id'),
    $id, $label ('Sucursal'), $required (false), $placeholder (null = sin
    opción vacía), $wrapper ('col-md-6'), $help, $emptyWhenSingle (false).
--}}
@php
    $name = $name ?? 'branch_id';
    $id = $id ?? $name;
    $selected = old($name, $selected ?? null);
    $required = $required ?? false;
    $placeholder = $placeholder ?? null;
    $emptyWhenSingle = $emptyWhenSingle ?? false;
@endphp

@if($branches->count() <= 1)
    <input type="hidden" name="{{ $name }}" id="{{ $id }}"
           value="{{ $emptyWhenSingle ? '' : $branches->first()?->id }}">
@else
    <div class="{{ $wrapper ?? 'col-md-6' }}">
        <label for="{{ $id }}" class="form-label">
            {{ $label ?? 'Sucursal' }} @if($required)<span class="text-danger">*</span>@endif
        </label>
        <select id="{{ $id }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" {{ $required ? 'required' : '' }}>
            @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (string) $selected === (string) $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        @if(!empty($help))<small class="text-muted">{{ $help }}</small>@endif
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@endif
