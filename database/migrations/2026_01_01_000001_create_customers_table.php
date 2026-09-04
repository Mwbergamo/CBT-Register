<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local cache of ConnectWise company + contact records, keyed by the
     * ConnectWise company/contact IDs. This is what the associate searches
     * against for speed, and what gets written to immediately when a new
     * walk-in customer is entered — the ConnectWise API call happens in the
     * same request when possible, or is queued (see sync_queue_items) if
     * ConnectWise is unreachable, so the register never blocks a sale.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Null until ConnectWise create/match succeeds.
            $table->unsignedBigInteger('cw_company_id')->nullable()->index();
            $table->unsignedBigInteger('cw_contact_id')->nullable()->index();
            $table->unsignedBigInteger('cw_site_id')->nullable();

            $table->string('company_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();

            $table->string('phone', 25)->nullable();
            $table->string('email')->nullable();

            // Set true once ConnectWise confirms the auto-approved status/type
            // was applied (see App\Services\ConnectWise\CompanyService).
            $table->boolean('cw_approved')->default(false);

            $table->timestamp('cw_synced_at')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
