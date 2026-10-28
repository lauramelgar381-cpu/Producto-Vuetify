<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    private const TOTAL = 1500;

    /**
     * Genera 1,500 productos con ProductFactory (faker).
     * Si ya hay 1,500 o más, no agrega otros: se puede ejecutar varias veces sin duplicar.
     */
    public function run(): void
    {
        if (Product::count() >= self::TOTAL) {
            $this->command?->info('Ya hay '.self::TOTAL.' o más productos; no se agregó ninguno.');

            return;
        }

        DB::transaction(function () {
            // Inserción por lotes de 500: mucho más rápido que crear uno por uno
            foreach (array_fill(0, intdiv(self::TOTAL, 500), 500) as $lote) {
                $ahora = now();

                $filas = Product::factory()
                    ->count($lote)
                    ->make()
                    ->map(fn (Product $p) => $p->getAttributes() + [
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])
                    ->all();

                Product::insert($filas);
            }
        });

        $this->command?->info(self::TOTAL.' productos generados.');
    }
}
