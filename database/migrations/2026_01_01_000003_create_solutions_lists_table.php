<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "Solutions List" is the working cart/checkout ticket an associate
     * builds at the register. Once submitted it becomes (or queues to
     * become) a Miscellaneous invoice in ConnectWise, closed at creation.
     */
    public function up(): void
    {
        Schema::create('solutions_lists', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // human-facing, e.g. RC-000123

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();

            // draft: still being built at the register.
            // submitted: associate hit checkout, invoice push is in flight/queued.
            // invoiced: ConnectWise invoice created + closed successfully.
            // failed: queued push exhausted retries — needs manual attention.
            // voided: cancelled before invoicing.
            $table->string('status')->default('draft');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('payment_method')->nullable(); // card / cash / check, informational
            $table->string('payment_reference')->nullable(); // e.g. terminal auth code

            $table->unsignedBigInteger('cw_invoice_id')->nullable()->index();
            $table->string('cw_invoice_number')->nullable();
            $table->timestamp('invoiced_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solutions_lists');
    }
};
