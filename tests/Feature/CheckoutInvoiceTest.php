<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\SolutionsList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_and_closes_a_connectwise_invoice_in_real_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->syncedToConnectWise()->create();
        $item = CatalogItem::factory()->create(['price' => 25.00, 'taxable' => true]);

        Http::fake([
            '*/finance/invoices/*' => Http::response(['id' => 4242, 'closedFlag' => true], 200),
            '*/finance/invoices' => Http::response(['id' => 4242, 'invoiceNumber' => 'INV-4242'], 200),
        ]);

        Livewire::test(Checkout::class)
            ->call('selectCustomer', $customer->id)
            ->call('confirmCustomer')
            ->call('addCatalogItem', $item->id)
            ->call('checkout')
            ->assertSet('step', 'complete');

        $list = SolutionsList::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('invoiced', $list->status);
        $this->assertSame(4242, $list->cw_invoice_id);
        $this->assertSame('INV-4242', $list->cw_invoice_number);
        $this->assertEquals(25.00, $list->total);
        $this->assertNotNull($list->invoiced_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'finance/invoices') && $request->method() === 'PATCH');
    }

    public function test_checkout_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->syncedToConnectWise()->create();

        Livewire::test(Checkout::class)
            ->call('selectCustomer', $customer->id)
            ->call('confirmCustomer')
            ->call('checkout')
            ->assertSet('step', 'cart')
            ->assertSet('checkoutError', 'Add at least one item before checking out.');
    }

    public function test_removing_the_only_item_zeroes_the_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->syncedToConnectWise()->create();
        $item = CatalogItem::factory()->create(['price' => 10.00]);

        $component = Livewire::test(Checkout::class)
            ->call('selectCustomer', $customer->id)
            ->call('confirmCustomer')
            ->call('addCatalogItem', $item->id);

        $listId = $component->get('solutionsListId');
        $lineItem = SolutionsList::find($listId)->items->first();

        $component->call('removeItem', $lineItem->id);

        $this->assertEquals(0, SolutionsList::find($listId)->total);
    }
}
