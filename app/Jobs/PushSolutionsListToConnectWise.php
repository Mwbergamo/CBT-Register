<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\SolutionsList;
use App\Models\SyncQueueItem;
use App\Services\ConnectWise\ConnectWiseException;
use App\Services\ConnectWise\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The retry half of the hybrid checkout workflow. Checkout (see
 * App\Livewire\Checkout::checkout()) always tries InvoiceService synchronously
 * first so an associate gets an immediate invoice number when ConnectWise is
 * healthy. This job only runs when that first attempt failed: it re-attempts
 * with backoff, tracked in `sync_queue_items` so support staff can see what's
 * outstanding and why.
 */
class PushSolutionsListToConnectWise implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public int $timeout = 60;

    /**
     * Backoff: 1m, 5m, 15m, 30m, then hourly — gives ConnectWise time to
     * come back after a maintenance window without hammering it, while
     * still catching up quickly from a brief blip.
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600, 3600, 3600, 3600];
    }

    public function __construct(public int $syncQueueItemId) {}

    public function handle(InvoiceService $invoices): void
    {
        $queueItem = SyncQueueItem::findOrFail($this->syncQueueItemId);
        $list = SolutionsList::findOrFail($queueItem->solutions_list_id);

        if ($list->status === 'invoiced') {
            $queueItem->update(['status' => 'succeeded']);

            return;
        }

        $queueItem->update([
            'status' => 'processing',
            'attempts' => $queueItem->attempts + 1,
            'last_attempted_at' => now(),
        ]);

        try {
            $invoices->pushToConnectWise($list);

            $queueItem->update(['status' => 'succeeded', 'last_error' => null]);
        } catch (ConnectWiseException $e) {
            $queueItem->update(['status' => 'pending', 'last_error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $queueItem = SyncQueueItem::find($this->syncQueueItemId);
        $queueItem?->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

        if ($queueItem) {
            SolutionsList::where('id', $queueItem->solutions_list_id)->update(['status' => 'failed']);
        }

        AuditLog::record('invoice.push_failed', context: [
            'sync_queue_item_id' => $this->syncQueueItemId,
            'error' => $e->getMessage(),
        ]);
    }
}
