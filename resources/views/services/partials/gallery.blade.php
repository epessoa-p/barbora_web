{{--
    Fotos de cortes del servicio (ServiceGallery): varias, cada una con su
    comentario. La primera es la portada (lista de servicios y catálogo).

    Va dentro del formulario del servicio (multipart):
      captions[id]     comentario de cada foto que ya está
      order[]          ids en el orden de la cuadrícula
      remove_photos[]  las que se quitan
      photos[]         las nuevas (al final), con new_captions[] alineados
--}}
@php
    $photos = $service?->photos ?? collect();
    $max = \App\Support\ServiceGallery::MAX;
@endphp

<div class="col-12">
    <label class="form-label mb-0">Fotos de cortes</label>
    <p class="small text-muted mb-2">
        Los cortes de este servicio, cada uno con un comentario (ej.: «1», «Corte XXTT»). La primera es la
        portada. Se muestran en el catálogo público. JPG, PNG o WEBP, hasta 2 MB cada una; máximo {{ $max }}.
    </p>

    @error('photos')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
    @foreach($errors->get('photos.*') as $messages)
        <div class="alert alert-danger py-2 small">{{ $messages[0] }}</div>
    @endforeach

    <div class="row g-2" id="gallery-grid">
        @foreach($photos as $photo)
            <div class="col-6 col-md-3 col-xl-2 gallery-item" data-id="{{ $photo->id }}">
                <div class="card h-100">
                    <div class="position-relative">
                        <img src="{{ $photo->url() }}" alt="" class="card-img-top" style="aspect-ratio: 1; object-fit: cover">
                        <span class="badge bg-dark position-absolute top-0 start-0 m-1 gallery-cover">Portada</span>
                    </div>
                    <div class="card-body p-2">
                        <input type="hidden" name="order[]" value="{{ $photo->id }}">
                        <input type="text" name="captions[{{ $photo->id }}]" maxlength="120" placeholder="Comentario"
                               class="form-control form-control-sm mb-2" value="{{ old('captions.'.$photo->id, $photo->caption) }}">
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-light border btn-sm gallery-left" title="Mover a la izquierda"><i class="bi bi-arrow-left"></i></button>
                            <button type="button" class="btn btn-light border btn-sm gallery-right" title="Mover a la derecha"><i class="bi bi-arrow-right"></i></button>
                            <label class="btn btn-outline-danger btn-sm ms-auto mb-0" title="Quitar">
                                <input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" class="d-none gallery-remove">
                                <i class="bi bi-trash"></i>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-2">
        <input type="file" id="gallery-input" name="photos[]" multiple accept="image/png,image/jpeg,image/webp"
               class="form-control @error('photos') is-invalid @enderror">
        <small class="text-muted">Puedes elegir varias a la vez; se agregan al final.</small>
    </div>
    <div class="row g-2 mt-1" id="gallery-new"></div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        const grid = document.getElementById('gallery-grid');
        if (!grid) return;

        // La primera que no se quita es la portada.
        const refresh = () => {
            let first = true;
            grid.querySelectorAll('.gallery-item').forEach(item => {
                const removed = item.querySelector('.gallery-remove').checked;
                item.style.opacity = removed ? .4 : 1;
                item.querySelector('.gallery-cover').classList.toggle('d-none', removed || !first);
                if (!removed) first = false;
            });
        };

        grid.addEventListener('click', e => {
            const item = e.target.closest('.gallery-item');
            if (!item) return;
            if (e.target.closest('.gallery-left') && item.previousElementSibling) {
                grid.insertBefore(item, item.previousElementSibling);
            } else if (e.target.closest('.gallery-right') && item.nextElementSibling) {
                grid.insertBefore(item.nextElementSibling, item);
            }
            refresh();
        });
        grid.addEventListener('change', e => {
            if (e.target.classList.contains('gallery-remove')) {
                e.target.closest('label').classList.toggle('active', e.target.checked);
                refresh();
            }
        });
        refresh();

        // Vista previa de las nuevas, cada una con su comentario.
        document.getElementById('gallery-input').addEventListener('change', function () {
            const box = document.getElementById('gallery-new');
            box.innerHTML = '';
            Array.from(this.files).forEach(file => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-3 col-xl-2';
                col.innerHTML = '<div class="card h-100"><img class="card-img-top" style="aspect-ratio:1;object-fit:cover">'
                    + '<div class="card-body p-2"><span class="badge bg-success-subtle text-success mb-1">Nueva</span>'
                    + '<input type="text" name="new_captions[]" maxlength="120" placeholder="Comentario" class="form-control form-control-sm"></div></div>';
                col.querySelector('img').src = URL.createObjectURL(file);
                box.appendChild(col);
            });
        });
    })();
    </script>
    @endpush
@endonce
