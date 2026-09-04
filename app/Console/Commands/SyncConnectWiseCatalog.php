<?php

namespace App\Console\Commands;

use App\Services\ConnectWise\CatalogService;
use Illuminate\Console\Command;

class SyncConnectWiseCatalog extends Command
{
    protected $signature = 'connectwise:sync-catalog';

    protected $description = 'Sync the Retail-warehouse product catalog from ConnectWise Manage into the local cache';

    public function handle(CatalogService $catalog): int
    {
        $this->info('Syncing ConnectWise Retail catalog...');

        $count = $catalog->sync();

        $this->info("Synced {$count} catalog items.");

        return self::SUCCESS;
    }
}
