<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $tipos = [
            'Teclado', 'Mouse', 'Monitor', 'Laptop', 'Auriculares', 'Impresora', 'Router',
            'Disco duro', 'Memoria USB', 'Cámara web', 'Parlantes', 'Tablet', 'Cargador',
            'Silla de oficina', 'Escritorio',
        ];
        $marcas = [
            'Logitech', 'HP', 'Dell', 'Lenovo', 'Samsung', 'Asus', 'Acer', 'Sony',
            'Kingston', 'TP-Link', 'Epson', 'Xiaomi',
        ];

        $tipo = fake()->randomElement($tipos);
        $marca = fake()->randomElement($marcas);
        $modelo = strtoupper(fake()->bothify('??-####'));
        $garantia = fake()->randomElement([3, 6, 12, 24]);

        return [
            'name' => "{$tipo} {$marca} {$modelo}",
            'description' => "{$tipo} marca {$marca}, modelo {$modelo}. Garantía de {$garantia} meses.",
            'price' => fake()->randomFloat(2, 1, 1500),
            // ~8% de los productos quedan sin stock, para que el inicio tenga ambos casos
            'stock' => fake()->boolean(8) ? 0 : fake()->numberBetween(1, 500),
        ];
    }
}
