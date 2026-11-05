{{--
    Modal de la caja, desde el botón de la barra superior (App\Support\CashStatus).

    Una tarjeta por caja que el usuario puede usar. Cada caja tiene su propio
    turno: la abierta muestra lo esperado en el cajón; la cerrada, un
    formulario corto para abrirla sin salir de la página. El arqueo del cierre
    (por denominaciones) va en la pantalla completa de Caja.

    Parámetros: $cajas (de CashStatus::for), $canOpen (permiso cash.create).
--}}
<div class="modal fade" id="cashModal" tabindex="-1" aria-labelledby="cashModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cashModalLabel"><i class="bi bi-cash-stack"></i> Caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Cada caja abre y cierra su propio turno. Primero ves la tuya (la asignada a tu personal) o la general.
                </p>

                @foreach($cajas as $c)
                    <div class="border rounded-3 p-3 mb-3 {{ $c['open'] ? 'border-success' : '' }}">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold">
                                    {{ $c['name'] }}
                                    @if($c['mine'])<span class="badge bg-primary-subtle text-primary-emphasis ms-1">Tuya</span>@endif
                                </div>
                                @if($c['branch'])<div class="small text-muted">{{ $c['branch'] }}</div>@endif
                            </div>
                            <span class="badge rounded-pill {{ $c['open'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $c['open'] ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </div>

                        @if($c['open'])
                            @php $s = $c['session']; @endphp
                            <div class="small text-muted mb-2">
                                Turno {{ $s['number'] }} · abrió {{ $s['opened_by'] ?? '—' }} · {{ $s['opened_at']?->format('d/m H:i') }}
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="bg-light rounded p-2">
                                        <div class="small text-muted">Esperado en el cajón</div>
                                        <div class="fw-bold">{{ \App\Support\Money::format($s['expected_cash'], $tenantCompany) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2">
                                        <div class="small text-muted">Ingresos del turno</div>
                                        <div class="fw-bold text-success">{{ \App\Support\Money::format($s['income'], $tenantCompany) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('cash.current', ['caja' => $c['id']]) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                    <i class="bi bi-arrow-left-right"></i> Movimiento
                                </a>
                                <a href="{{ route('cash.current', ['caja' => $c['id']]) }}#cerrar" class="btn btn-sm btn-outline-danger flex-fill">
                                    <i class="bi bi-lock"></i> Cerrar turno
                                </a>
                            </div>
                        @elseif($canOpen)
                            <form action="{{ route('cash.open') }}" method="POST" class="d-flex gap-2 align-items-end">
                                @csrf
                                <input type="hidden" name="caja_id" value="{{ $c['id'] }}">
                                <input type="hidden" name="redirect" value="back">
                                <div class="flex-fill">
                                    <label class="form-label small mb-1" for="opening-{{ $c['id'] }}">Fondo inicial</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                                        <input type="number" step="0.01" min="0" id="opening-{{ $c['id'] }}" name="opening_amount"
                                               class="form-control" value="{{ number_format($c['balance'], 2, '.', '') }}" required>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-success"><i class="bi bi-unlock"></i> Abrir turno</button>
                            </form>
                        @else
                            <p class="small text-muted mb-0">No tienes permiso para abrir el turno.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <a href="{{ route('cash.sessions.index') }}" class="btn btn-light border btn-sm">Turnos anteriores</a>
                <a href="{{ route('cash.current') }}" class="btn btn-primary btn-sm">Ir a la caja</a>
            </div>
        </div>
    </div>
</div>
