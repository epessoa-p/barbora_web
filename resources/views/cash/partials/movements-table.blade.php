<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Movimientos del turno</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Hora</th>
                        <th>Concepto</th>
                        <th>Método</th>
                        <th class="text-end">Importe</th>
                        @if($session->isOpen())
                            <th class="text-end pe-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td class="ps-3 text-nowrap">{{ $movement->created_at->format('H:i') }}</td>
                            <td>
                                <span class="fw-semibold">{{ $movement->concept }}</span>
                                @if($movement->reference)
                                    <small class="text-muted d-block">{{ $movement->reference }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $movement->paymentMethodLabel() }}</span>
                            </td>
                            <td class="text-end fw-semibold text-{{ $movement->isIncome() ? 'success' : 'danger' }}">
                                {{ $movement->isIncome() ? '+' : '−' }}
                                {{ \App\Support\Money::format($movement->amount, $tenantCompany) }}
                            </td>
                            @if($session->isOpen())
                                <td class="text-end pe-3">
                                    <form action="{{ route('cash.movements.destroy', $movement) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Anular «{{ $movement->concept }}»?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Anular">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Todavía no hay movimientos en este turno.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
