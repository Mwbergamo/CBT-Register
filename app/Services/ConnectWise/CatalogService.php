<?php

namespace App\Services\ConnectWise;

use App\Models\CatalogItem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the ConnectWise product catalog, scoped to the Retail warehouse,
 * into the local `catalog_items` cache so register search stays fast
 * against <1,000 rows instead of the full ~17,000 item catalog. Run via
 * `php artisan connectwise:sync-catalog` (see App\Console\Commands and
 * App\Jobs\SyncCatalogFromConnectWise), on a schedule and on demand.
 *
 * The exact ConnectWise query used to scope to one warehouse depends on how
 * your catalog is organized — either a Category/Subcategory used as the
 * "Retail" marker, or a join against inventory-by-warehouse. This class
 * uses `connectwise.catalog_filter_conditions` (config/connectwise.php) so
 * that choice is a config change, not a code change, once you can see your
 * real catalog data — see docs/CONNECTWISE_SETUP.md.
 */
class CatalogService
{
    public function __construct(protected ConnectWiseClient $client) {}

    public function sync(): int
    {
        $pageSize = (int) Config::get('connectwise.catalog_sync_page_size', 200);
        $conditions = Config::get('connectwise.catalog_filter_conditions');
        $warehouseId = Config::get('connectwise.retail_warehouse_id');

        $page = 1;
        $synced = 0;
        $seenIds = [];

        do {
            $items = $this->client->get('procurement/catalog', array_filter([
                'conditions' => $conditions,
                'page' => $page,
                'pageSize' => $pageSize,
                // If your Manage version exposes warehouse on the catalog
                // item directly, this narrows the API call itself rather
                // than relying only on `conditions`.
                'warehouseId' => $warehouseId ?: null,
            ]));

            foreach ($items as $item) {
                $this->upsert($item);
                $seenIds[] = $item['id'];
                $synced++;
            }

            $page++;
        } while (count($items) === $pageSize);

        // Anything previously synced but no longer returned is no longer in
        // the Retail warehouse (or was deactivated) — hide it locally.
        if ($seenIds !== []) {
            CatalogItem::whereNotIn('cw_catalog_id', $seenIds)->update(['is_active' => false]);
        }

        Log::info('ConnectWise catalog sync complete', ['synced' => $synced]);

        return $synced;
    }

    protected function upsert(array $item): CatalogItem
    {
        return CatalogItem::updateOrCreate(
            ['cw_catalog_id' => $item['id']],
            [
                'identifier' => $item['identifier'] ?? '',
                'description' => $item['description'] ?? '',
                'customer_description' => $item['customerDescription'] ?? null,
                'category' => $item['category']['name'] ?? null,
                'subcategory' => $item['subcategory']['name'] ?? null,
                'item_type' => $item['type']['name'] ?? null,
                'price' => $item['price'] ?? 0,
                'cost' => $item['cost'] ?? null,
                'taxable' => $item['taxableFlag'] ?? true,
                'quantity_on_hand' => $item['quantityOnHand'] ?? null,
                'is_active' => ! ($item['inactiveFlag'] ?? false),
                'cw_synced_at' => now(),
            ]
        );
    }
}
