<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerSearchAndCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_associate_can_find_an_existing_customer_and_fields_autofill(): void
    {
        $this->actingUser();

        $customer = Customer::factory()->syncedToConnectWise()->create([
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'phone' => '8045551234',
            'email' => 'jamie@example.com',
        ]);

        Livewire::test(Checkout::class)
            ->set('customerSearch', 'Rivera')
            ->assertSee('Jamie Rivera')
            ->call('selectCustomer', $customer->id)
            ->assertSet('selectedCustomerId', $customer->id)
            ->assertSee('8045551234')
            ->assertSee('jamie@example.com');
    }

    public function test_new_customer_is_created_and_auto_approved_in_connectwise(): void
    {
        $this->actingUser();

        Http::fake([
            '*/company/companies' => Http::response(['id' => 555, 'name' => 'Taylor Morgan', 'site' => ['id' => 1]], 200),
            '*/company/contacts' => Http::response(['id' => 777], 200),
        ]);

        Livewire::test(Checkout::class)
            ->call('startNewCustomer')
            ->set('newFirstName', 'Taylor')
            ->set('newLastName', 'Morgan')
            ->set('newPhone', '8045559999')
            ->set('newEmail', 'taylor@example.com')
            ->call('saveNewCustomer')
            ->assertSet('showNewCustomerForm', false);

        $customer = Customer::where('last_name', 'Morgan')->firstOrFail();

        $this->assertSame(555, $customer->cw_company_id);
        $this->assertSame(777, $customer->cw_contact_id);
        $this->assertTrue($customer->cw_approved);
    }

    public function test_new_customer_still_saves_locally_when_connectwise_is_unreachable(): void
    {
        $this->actingUser();

        Http::fake([
            '*/company/companies' => Http::response(['message' => 'unreachable'], 500),
        ]);

        Livewire::test(Checkout::class)
            ->call('startNewCustomer')
            ->set('newFirstName', 'Casey')
            ->set('newLastName', 'Nguyen')
            ->call('saveNewCustomer');

        $customer = Customer::where('last_name', 'Nguyen')->firstOrFail();

        $this->assertNull($customer->cw_company_id);
        $this->assertFalse($customer->cw_approved);
    }
}
