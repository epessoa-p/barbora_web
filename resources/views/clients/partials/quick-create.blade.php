{{--
    Alta rápida de cliente en un modal, sin recargar la página: lo usan «Nueva
    cita» y «Nueva venta». Al guardar, el cliente entra al select $selectId y
    queda elegido. Solo se incluye si el usuario puede crear clientes.
--}}
@php($selectId = $selectId ?? 'client_id')

    {{-- Alta rápida: se guarda por fetch, sin recargar ni perder lo ya
         rellenado en la cita, y el cliente nuevo queda elegido en el select. --}}
    <div class="modal fade" id="new-client-modal" tabindex="-1" aria-labelledby="new-client-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="new-client-form" action="{{ route('clients.store') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="active" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="new-client-title"><i class="bi bi-person-plus"></i> Nuevo cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="new-client-error"></div>
                    <div class="mb-3">
                        <label for="nc-full_name" class="form-label">Nombre completo</label>
                        <input type="text" id="nc-full_name" name="full_name" class="form-control" required maxlength="255">
                        <div class="invalid-feedback" data-error-for="full_name"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nc-phone" class="form-label">Teléfono</label>
                            <input type="text" id="nc-phone" name="phone" class="form-control" maxlength="30">
                            <div class="invalid-feedback" data-error-for="phone"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="nc-document_number" class="form-label">Documento</label>
                            <input type="text" id="nc-document_number" name="document_number" class="form-control" maxlength="30">
                            <div class="invalid-feedback" data-error-for="document_number"></div>
                        </div>
                        <div class="col-12">
                            <label for="nc-email" class="form-label">Correo</label>
                            <input type="email" id="nc-email" name="email" class="form-control" maxlength="255">
                            <div class="invalid-feedback" data-error-for="email"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="new-client-save">
                        <i class="bi bi-check-circle"></i> Guardar cliente
                    </button>
                </div>
            </form>
        </div>
    </div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('new-client-form');
    const modalEl = document.getElementById('new-client-modal');
    const select = document.getElementById(@json($selectId));
    const saveBtn = document.getElementById('new-client-save');
    const generalError = document.getElementById('new-client-error');

    function clearErrors() {
        generalError.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }

    // Al abrir, el foco va al nombre; al cerrar, el formulario queda limpio.
    modalEl.addEventListener('shown.bs.modal', () => form.querySelector('[name="full_name"]').focus());
    modalEl.addEventListener('hidden.bs.modal', () => { form.reset(); clearErrors(); });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearErrors();
        saveBtn.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const body = await response.json().catch(() => ({}));

            if (response.status === 422) {
                Object.entries(body.errors || {}).forEach(function ([field, messages]) {
                    const input = form.querySelector('[name="' + field + '"]');
                    const feedback = form.querySelector('[data-error-for="' + field + '"]');
                    if (input && feedback) {
                        input.classList.add('is-invalid');
                        feedback.textContent = messages[0];
                    } else {
                        generalError.textContent = messages[0];
                        generalError.classList.remove('d-none');
                    }
                });
                return;
            }

            if (!response.ok) {
                generalError.textContent = body.message || 'No se pudo registrar el cliente. Inténtalo de nuevo.';
                generalError.classList.remove('d-none');
                return;
            }

            // El cliente nuevo entra al select y queda elegido.
            const client = body.data;
            const option = new Option(client.full_name + (client.phone ? ' · ' + client.phone : ''), client.id, true, true);
            select.add(option);
            select.value = String(client.id);
            select.classList.remove('is-invalid');
            select.dispatchEvent(new Event('change'));

            bootstrap.Modal.getInstance(modalEl).hide();
        } catch (e) {
            generalError.textContent = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
            generalError.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });
})();
</script>
@endpush
