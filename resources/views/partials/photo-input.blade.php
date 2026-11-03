{{--
    Campo de foto (servicios, productos): vista previa, elegir archivo y, si ya
    tiene una, «quitar la foto». El formulario necesita enctype multipart.

    Parámetros: $model (o null en un alta), $help (opcional).
--}}
<div class="col-12">
    <label for="photo" class="form-label">Foto</label>
    <div class="d-flex align-items-center gap-3">
        <img id="photo-preview" src="{{ $model?->photoUrl() }}" alt=""
             class="rounded border {{ $model?->photoUrl() ? '' : 'd-none' }}"
             style="width: 84px; height: 84px; object-fit: cover">
        <div class="flex-fill">
            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp"
                   class="form-control @error('photo') is-invalid @enderror">
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="text-muted">{{ $help ?? 'JPG, PNG o WEBP, hasta 2 MB.' }}</small>
            @if($model?->photo)
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                    <label class="form-check-label small" for="remove_photo">Quitar la foto</label>
                </div>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    // Vista previa de la foto elegida, antes de guardar.
    document.getElementById('photo')?.addEventListener('change', function () {
        const preview = document.getElementById('photo-preview');
        const file = this.files[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
    });
    </script>
    @endpush
@endonce
