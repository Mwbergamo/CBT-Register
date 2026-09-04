<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_search_scope_matches_identifier_and_description(): void
    {
        CatalogItem::factory()->create(['identifier' => 'RTL-1001', 'description' => 'USB-C Charging Cable']);
        CatalogItem::factory()->create(['identifier' => 'RTL-1002', 'description' => 'Wireless Mouse']);

        $this->assertCount(1, CatalogItem::search('charging')->get());
        $this->assertCount(1, CatalogItem::search('RTL-1002')->get());
        $this->assertCount(0, CatalogItem::search('nonexistent')->get());
    }

    public function test_inactive_items_are_excluded_from_search(): void
    {
        CatalogItem::factory()->create(['description' => 'Retired Adapter', 'is_active' => false]);

        $this->assertCount(0, CatalogItem::search('Retired')->get());
    }

    public function test_associate_can_add_catalog_items_to_the_cart_from_the_register_screen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->syncedToConnectWise()->create();
        $item = CatalogItem::factory()->create(['description' => 'HDMI Cable 6ft', 'price' => 12.99]);

        $component = Livewire::test(Checkout::class)
            ->call('selectCustomer', $customer->id)
            ->call('confirmCustomer')
            ->assertSet('step', 'cart')
            ->set('productSearch', 'HDMI')
            ->assertSee('HDMI Cable 6ft')
            ->call('addCatalogItem', $item->id)
            ->assertSee('HDMI Cable 6ft');

        $listId = $component->get('solutionsListId');
        $this->assertNotNull($listId);

        $list = \App\Models\SolutionsList::with('items')->findOrFail($listId);
        $this->assertEquals(12.99, $list->items->first()->unit_price);
    }
}
