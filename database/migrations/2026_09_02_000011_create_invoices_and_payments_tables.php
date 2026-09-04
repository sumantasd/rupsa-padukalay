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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->char('client_trans_uuid', 36)->unique(); // Offline sync idempotency UUID
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            
            // Tax / GST Snapshot Fields (Supports GST ON/OFF without breaking history)
            $table->boolean('is_gst_enabled')->default(false);
            $table->decimal('taxable_amount', 12, 2)->default(0.00);
            $table->decimal('total_cgst', 12, 2)->default(0.00);
            $table->decimal('total_sgst', 12, 2)->default(0.00);
            $table->decimal('total_igst', 12, 2)->default(0.00);
            $table->decimal('total_tax', 12, 2)->default(0.00);
            
            $table->decimal('grand_total', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('change_returned', 12, 2)->default(0.00);
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->default('paid');
            $table->enum('sale_type', ['pos_counter', 'web_lead', 'wholesale'])->default('pos_counter');
            $table->enum('status', ['completed', 'returned', 'partially_returned', 'cancelled'])->default('completed');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->string('sku_snapshot', 100);
            $table->string('article_number_snapshot', 100);
            $table->string('product_name_snapshot', 200);
            $table->string('color_name_snapshot', 50);
            $table->string('size_number_snapshot', 20);
            $table->string('hsn_code_snapshot', 20)->nullable();
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('mrp', 12, 2)->default(0.00);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->integer('quantity')->default(1);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            
            // Tax Breakdown Per Line Item
            $table->decimal('tax_rate_percentage', 5, 2)->default(0.00);
            $table->decimal('taxable_value', 12, 2)->default(0.00);
            $table->decimal('cgst_amount', 12, 2)->default(0.00);
            $table->decimal('sgst_amount', 12, 2)->default(0.00);
            $table->decimal('igst_amount', 12, 2)->default(0.00);
            $table->decimal('total_tax_amount', 12, 2)->default(0.00);
            
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->enum('payment_method', ['cash', 'upi', 'card', 'store_credit', 'other'])->default('cash');
            $table->decimal('amount', 12, 2);
            $table->string('transaction_reference', 100)->nullable(); // UPI UTR or Card Auth Code
            $table->string('notes', 255)->nullable();
            $table->dateTime('payment_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
