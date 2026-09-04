<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solutions_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solutions_list_id')->constrained()->cascadeOnDelete();

            // Nullable: a line can be a free-text/manual item that isn't in
            // the catalog (e.g. a misc labor charge) as well as a catalog hit.
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();

            $table->string('identifier')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->boolean('taxable')->default(true);
            $table->decimal('line_total', 12, 2);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solutions_list_items');
    }
};
