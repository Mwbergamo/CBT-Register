<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'cw_company_id' => null,
            'cw_contact_id' => null,
            'company_name' => fake()->company(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            'phone' => fake()->numerify('804#######'),
            'email' => fake()->unique()->safeEmail(),
            'cw_approved' => false,
        ];
    }

    public function syncedToConnectWise(): static
    {
        return $this->state(fn () => [
            'cw_company_id' => fake()->unique()->numberBetween(1000, 99999),
            'cw_contact_id' => fake()->unique()->numberBetween(1000, 99999),
            'cw_approved' => true,
            'cw_synced_at' => now(),
        ]);
    }
}
