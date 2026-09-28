<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use App\Support\StockManager;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Inventario de ejemplo para la Barbería Demo, con existencias ya cargadas y
 * un producto por debajo del mínimo para que el aviso se vea funcionando.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () {
            $warehouse = Warehouse::orderBy('id')->first();

            if (! $warehouse) {
                return;
            }

            $categories = ['Cuidado del cabello', 'Barba', 'Color', 'Herramientas'];
            $ids = [];

            foreach ($categories as $i => $name) {
                $ids[$name] = ProductCategory::updateOrCreate(
                    ['name' => $name],
                    ['sort_order' => $i + 1, 'active' => true]
                )->id;
            }

            // nombre, categoría, unidad, costo, venta, mínimo, vendible, stock inicial
            $products = [
                ['Cera mate 100 ml',      'Cuidado del cabello', 'unidad', 28, 55, 5, true,  24],
                ['Pomada brillo 100 ml',  'Cuidado del cabello', 'unidad', 30, 60, 5, true,  18],
                ['Shampoo anticaspa',     'Cuidado del cabello', 'unidad', 35, 70, 4, true,   3],  // bajo mínimo
                ['Aceite para barba',     'Barba',               'unidad', 40, 85, 4, true,  12],
                ['Bálsamo para barba',    'Barba',               'unidad', 38, 75, 4, true,   9],
                ['Tinte castaño',         'Color',               'unidad', 45, 0,  6, false, 15],
                ['Agua oxigenada 20 vol', 'Color',               'ml',     12, 0, 10, false, 40],
                ['Cuchillas desechables', 'Herramientas',        'caja',   25, 0,  3, false,  8],
                ['Toallas desechables',   'Herramientas',        'caja',   30, 0,  2, false,  2],  // bajo mínimo
            ];

            $stock = app(StockManager::class);

            foreach ($products as [$name, $category, $unit, $cost, $sale, $min, $sellable, $initial]) {
                $product = Product::updateOrCreate(['name' => $name], [
                    'product_category_id' => $ids[$category],
                    'unit' => $unit,
                    'cost_price' => $cost,
                    'sale_price' => $sale,
                    'min_stock' => $min,
                    'is_sellable' => $sellable,
                    'track_stock' => true,
                    'active' => true,
                ]);

                // Solo carga stock si el producto aún no tiene: el seeder debe
                // poder reejecutarse sin inflar las existencias.
                if ($product->stocks()->sum('quantity') > 0) {
                    continue;
                }

                $stock->receive($product, $warehouse, $initial, [
                    'reason' => 'compra',
                    'unit_cost' => $cost,
                    'reference' => 'Carga inicial',
                    'created_by' => null,
                ]);
            }
        });
    }
}
