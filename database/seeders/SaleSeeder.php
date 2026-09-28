<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Support\SaleRegistrar;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Ventas de ejemplo del turno abierto de hoy.
 *
 * Se registran por SaleRegistrar y no a mano, así que el stock queda
 * descontado y los ingresos aparecen en la caja igual que en el uso real: la
 * demo enseña el sistema enlazado, no filas sueltas.
 */
class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            $registrar = app(SaleRegistrar::class);

            $services = Service::where('active', true)->orderBy('id')->get();
            $products = Product::sellable()->orderBy('id')->get();
            $clients = Client::orderBy('id')->get();

            if ($services->isEmpty() || $clients->isEmpty()) {
                return;
            }

            // Citas ya atendidas de la semana sembrada: cobrarlas es el flujo
            // natural. No se limita a hoy porque a primera hora del día aún no
            // habría ninguna atendida y la demo saldría vacía.
            $appointments = Appointment::with(['services', 'client', 'personal'])
                ->where('status', 'atendida')
                ->orderByDesc('starts_at')
                ->take(3)
                ->get();

            foreach ($appointments as $i => $appointment) {
                if ($appointment->services->isEmpty()) {
                    continue;
                }

                $items = $appointment->services->map(fn (Service $s) => [
                    'type' => 'servicio',
                    'service_id' => $s->id,
                    'description' => $s->name,
                    'quantity' => 1,
                    'unit_price' => (float) $s->pivot->price,
                ])->all();

                // En una de cada dos se lleva además un producto.
                if ($i % 2 === 0 && $products->isNotEmpty()) {
                    $product = $products[$i % $products->count()];

                    $items[] = [
                        'type' => 'producto',
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => 1,
                        'unit_price' => (float) $product->sale_price,
                    ];
                }

                $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
                $tip = $i === 0 ? 10.0 : 0.0;
                $total = $subtotal + $tip;

                // Alterna efectivo puro y pago mixto, para que el arqueo de la
                // caja tenga ambos casos.
                $payments = $i === 1
                    ? [
                        ['payment_method' => 'efectivo', 'amount' => round($total / 2, 2)],
                        ['payment_method' => 'tarjeta', 'amount' => round($total - round($total / 2, 2), 2)],
                    ]
                    : [['payment_method' => 'efectivo', 'amount' => $total]];

                $registrar->register($demo, [
                    'client_id' => $appointment->client_id,
                    'personal_id' => $appointment->personal_id,
                    'appointment_id' => $appointment->id,
                    'branch_id' => $appointment->branch_id,
                    'tip' => $tip,
                ], $items, $payments);
            }

            // Y una venta de mostrador, sin cita ni cliente: el otro flujo
            // real de una barbería.
            if ($products->isNotEmpty()) {
                $product = $products->first();

                $registrar->register($demo, [], [[
                    'type' => 'producto',
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit_price' => (float) $product->sale_price,
                ]], [[
                    'payment_method' => 'qr',
                    'amount' => (float) $product->sale_price,
                ]]);
            }
        });
    }
}
