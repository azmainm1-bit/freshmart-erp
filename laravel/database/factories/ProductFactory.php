<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['Grocery', 'Produce', 'Dairy', 'Household']),
            'stock_unit' => 'unit',
            'purchase_unit' => 'unit',
            'pack_conversion_factor' => '1',
            // Money value objects reject PHP floats by design (docs/ARCHITECTURE.md
            // §12.6) — factories must respect that too, not just app code.
            'selling_price' => number_format(fake()->randomFloat(2, 10, 500), 2, '.', ''),
            'tax_rate_percent' => '0',
            'tax_inclusive' => true,
            'is_weighted' => false,
            'is_batch_tracked' => false,
            'reorder_point' => '0',
            'archived' => false,
        ];
    }
}
