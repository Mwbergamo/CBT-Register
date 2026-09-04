<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local cache of the ConnectWise product catalog, filtered down to the
     * "Retail" warehouse at sync time (see App\Jobs\SyncCatalogFromConnectWise
     * and App\Services\ConnectWise\CatalogService). Searching this table
     * instead of calling the ConnectWise catalog API on every keystroke is
     * what keeps product search fast against a <1,000 item retail subset
     * instead of the full ~17,000 item catalog.
     */
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cw_catalog_id')->unique();

            $table->string('identifier')->index(); // ConnectWise SKU/identifier
            $table->string('description');
            $table->text('customer_description')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('item_type')->nullable(); // Product, Service, Bundle, ...

            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('taxable')->default(true);

            $table->unsignedInteger('quantity_on_hand')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('cw_synced_at')->nullable();
            $table->timestamps();

            $table->index('description');
            // Note: with well under 1,000 retail rows a plain LIKE search
            // (see CatalogItem::search()) is fast without a FULLTEXT index,
            // which keeps this schema portable between MySQL (production)
            // and SQLite (local dev). Add a MySQL FULLTEXT index yourself
            // later if the catalog grows enough to want ranked search.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
