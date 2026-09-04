<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return [
            'cw_catalog_id' => fake()->unique()->numberBetween(1, 999999),
            'identifier' => strtoupper(fake()->bothify('RTL-####')),
            'description' => fake()->words(3, true),
            'category' => 'Retail',
            'item_type' => 'Product',
            'price' => fake()->randomFloat(2, 5, 500),
            'taxable' => true,
            'quantity_on_hand' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'cw_synced_at' => now(),
        ];
    }
}
