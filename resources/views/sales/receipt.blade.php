{{-- Comprobante imprimible: sin menú ni armazón, pensado para papel de 80 mm. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $sale->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root { color-scheme: light; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 1.5rem;
            color: #1f2937;
        }

        .receipt {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 1.25rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .1);
            font-size: 12px;
            line-height: 1.45;
        }

        .receipt h1 { font-size: 15px; margin: 0; letter-spacing: .08em; }
        /* Tope de alto: en papel de 80 mm un logo grande se come el ticket. */
        .logo { display: block; margin: 0 auto .4rem; max-height: 56px; max-width: 70%; object-fit: contain; }
        /* Las impresoras térmicas imprimen en escala de grises. */
        @media print { .logo { filter: grayscale(1); } }
        .muted { color: #6b7280; }
        .center { text-align: center; }
        .right { text-align: right; }
        .row { display: flex; justify-content: space-between; gap: .5rem; }
        .divider { border-top: 1px dashed #d1d5db; margin: .6rem 0; }
        .total { font-size: 15px; font-weight: 700; }
        .cancelled { color: #b91c1c; font-weight: 700; text-align: center; letter-spacing: .1em; }

        .actions { text-align: center; margin-top: 1.25rem; }
        .actions button, .actions a {
            font: inherit; padding: .5rem 1rem; border-radius: .4rem;
            border: 1px solid #d1d5db; background: #fff; cursor: pointer;
            text-decoration: none; color: inherit;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; width: auto; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            @if($company?->logoUrl())
                <img src="{{ $company->logoUrl() }}" alt="" class="logo">
            @endif
            <h1>{{ Str::upper($company?->name ?? 'BARBORA') }}</h1>
            @if($company?->tax_id)
                <div class="muted">{{ $company->tax_id_label }}: {{ $company->tax_id }}</div>
            @endif
            @if($sale->branch)
                <div class="muted">{{ $sale->branch->name }}</div>
                @if($sale->branch->address)<div class="muted">{{ $sale->branch->address }}</div>@endif
            @elseif($company?->address)
                <div class="muted">{{ $company->address }}</div>
            @endif
        </div>

        <div class="divider"></div>

        @if($sale->isCancelled())
            <div class="cancelled">*** ANULADA ***</div>
            <div class="divider"></div>
        @endif

        <div class="row"><span class="muted">Comprobante</span><strong>{{ $sale->number }}</strong></div>
        <div class="row"><span class="muted">Fecha</span><span>{{ $sale->sold_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span class="muted">Cliente</span><span>{{ $sale->client?->full_name ?? 'Mostrador' }}</span></div>
        @if($sale->personal)
            <div class="row"><span class="muted">Atendió</span><span>{{ $sale->personal->full_name }}</span></div>
        @endif

        <div class="divider"></div>

        @foreach($sale->items as $item)
            <div class="row">
                <span>
                    {{ $item->description }}
                    @if((float) $item->quantity != 1)
                        <span class="muted">×{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</span>
                    @endif
                </span>
                <span class="right">{{ \App\Support\Money::format($item->total, $company) }}</span>
            </div>
        @endforeach

        <div class="divider"></div>

        <div class="row"><span class="muted">Subtotal</span><span>{{ \App\Support\Money::format($sale->subtotal, $company) }}</span></div>
        @if((float) $sale->discount > 0)
            <div class="row"><span class="muted">Descuento</span><span>− {{ \App\Support\Money::format($sale->discount, $company) }}</span></div>
        @endif
        @if((float) $sale->tip > 0)
            <div class="row"><span class="muted">Propina</span><span>{{ \App\Support\Money::format($sale->tip, $company) }}</span></div>
        @endif

        <div class="divider"></div>

        <div class="row total"><span>TOTAL</span><span>{{ \App\Support\Money::format($sale->total, $company) }}</span></div>

        <div class="divider"></div>

        @foreach($sale->payments as $payment)
            <div class="row">
                <span class="muted">{{ $payment->methodLabel() }}</span>
                <span>{{ \App\Support\Money::format($payment->amount, $company) }}</span>
            </div>
        @endforeach

        <div class="divider"></div>

        <div class="center muted">
            {{ $company?->receiptFooter() ?? '¡Gracias por tu visita!' }}
            @if($company?->phone)<div>{{ $company->phone }}</div>@endif
            @if($company?->email)<div>{{ $company->email }}</div>@endif
        </div>
    </div>

    <div class="actions">
        <button onclick="window.print()">Imprimir</button>
        <a href="{{ route('sales.show', $sale) }}">Volver</a>
    </div>
</body>
</html>
