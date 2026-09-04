<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\SolutionsList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolutionsListTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_totals_applies_tax_only_to_taxable_lines(): void
    {
        $list = SolutionsList::create([
            'customer_id' => Customer::factory()->create()->id,
            'created_by_user_id' => User::factory()->create()->id,
            'status' => 'draft',
        ]);

        $list->items()->create(['description' => 'Taxable widget', 'quantity' => 2, 'unit_price' => 10, 'line_total' => 20, 'taxable' => true]);
        $list->items()->create(['description' => 'Non-taxable service', 'quantity' => 1, 'unit_price' => 15, 'line_total' => 15, 'taxable' => false]);

        $list->recalculateTotals(0.06);

        $this->assertEquals(35.00, $list->subtotal);
        $this->assertEquals(1.20, $list->tax_total); // 6% of the $20 taxable portion only
        $this->assertEquals(36.20, $list->total);
    }

    public function test_ticket_numbers_are_generated_sequentially(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        $first = SolutionsList::create(['customer_id' => $customer->id, 'created_by_user_id' => $user->id]);
        $second = SolutionsList::create(['customer_id' => $customer->id, 'created_by_user_id' => $user->id]);

        $this->assertNotEquals($first->ticket_number, $second->ticket_number);
        $this->assertStringStartsWith('RC-', $first->ticket_number);
    }
}
