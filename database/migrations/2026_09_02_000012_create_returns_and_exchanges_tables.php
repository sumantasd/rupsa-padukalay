<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->char('client_return_uuid', 36)->unique(); // Offline sync idempotency UUID
            $table->foreignId('original_invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('total_refund_amount', 12, 2)->default(0.00);
            $table->enum('refund_mode', ['cash', 'upi', 'store_credit', 'exchange_offset'])->default('cash');
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->onDelete('cascade');
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->onDelete('cascade');
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('refund_unit_price', 12, 2);
            $table->enum('restock_condition', ['resellable', 'damaged'])->default('resellable');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
