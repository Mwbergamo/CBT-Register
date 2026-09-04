<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbox/retry ledger for the hybrid checkout workflow: a checkout
     * always tries to push to ConnectWise in real time first; if that call
     * fails (ConnectWise API down/timeout), a row lands here and
     * App\Jobs\PushSolutionsListToConnectWise retries it with backoff so the
     * sale still completes locally and the invoice catches up automatically.
     * This table is also a clean audit trail of every push attempt.
     */
    public function up(): void
    {
        Schema::create('sync_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solutions_list_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending, processing, succeeded, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue_items');
    }
};
