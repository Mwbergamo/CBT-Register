<?php

namespace Tests\Feature;

use App\Jobs\PushSolutionsListToConnectWise;
use App\Livewire\Checkout;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\SolutionsList;
use App\Models\SyncQueueItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SyncQueueRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_falls_back_to_the_sync_queue_when_connectwise_is_down(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->syncedToConnectWise()->create();
        $item = CatalogItem::factory()->create(['price' => 40.00]);

        Http::fake([
            '*/finance/invoices' => Http::response(['message' => 'Service Unavailable'], 503),
        ]);

        Livewire::test(Checkout::class)
            ->call('selectCustomer', $customer->id)
            ->call('confirmCustomer')
            ->call('addCatalogItem', $item->id)
            ->call('checkout')
            ->assertSet('step', 'complete');

        $list = SolutionsList::where('customer_id', $customer->id)->firstOrFail();

        // The sale still completes locally — status is 'submitted', not
        // 'invoiced', and nothing about the customer's checkout experience
        // depended on ConnectWise being reachable.
        $this->assertSame('submitted', $list->status);
        $this->assertNull($list->cw_invoice_id);

        $queueItem = SyncQueueItem::where('solutions_list_id', $list->id)->firstOrFail();
        $this->assertSame('pending', $queueItem->status);
        $this->assertNotNull($queueItem->last_error);

        Queue::assertPushed(PushSolutionsListToConnectWise::class, fn ($job) => $job->syncQueueItemId === $queueItem->id);
    }

    public function test_retry_job_succeeds_once_connectwise_is_back(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->syncedToConnectWise()->create();

        $list = SolutionsList::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'status' => 'submitted',
            'total' => 40,
        ]);
        $list->items()->create([
            'description' => 'Retail item', 'quantity' => 1, 'unit_price' => 40, 'line_total' => 40, 'taxable' => true,
        ]);

        $queueItem = SyncQueueItem::create([
            'solutions_list_id' => $list->id,
            'status' => 'pending',
            'next_attempt_at' => now(),
            'last_error' => 'previous failure',
        ]);

        Http::fake([
            '*/finance/invoices/*' => Http::response(['id' => 99, 'closedFlag' => true], 200),
            '*/finance/invoices' => Http::response(['id' => 99, 'invoiceNumber' => 'INV-99'], 200),
        ]);

        (new PushSolutionsListToConnectWise($queueItem->id))->handle(app(\App\Services\ConnectWise\InvoiceService::class));

        $this->assertSame('invoiced', $list->fresh()->status);
        $this->assertSame('succeeded', $queueItem->fresh()->status);
    }
}
