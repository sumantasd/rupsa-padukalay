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
        if (! Schema::hasTable('goods_receives')) {
            Schema::create('goods_receives', function (Blueprint $table) {
                $table->id();
                $table->string('grn_number', 50)->unique();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
                $table->date('received_date');
                $table->integer('total_items_received')->default(0);
                $table->decimal('total_cost', 12, 2)->default(0.00);
                $table->string('supplier_invoice_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('goods_receive_items')) {
            Schema::create('goods_receive_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receive_id')->constrained('goods_receives')->onDelete('cascade');
                $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->onDelete('set null');
                $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
                $table->integer('quantity_ordered');
                $table->integer('quantity_received');
                $table->decimal('cost_price', 12, 2);
                $table->decimal('total_cost', 12, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_bills')) {
            Schema::create('purchase_bills', function (Blueprint $table) {
                $table->id();
                $table->string('bill_number', 50)->unique();
                $table->string('supplier_invoice_number', 100)->nullable();
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('set null');
                $table->foreignId('goods_receive_id')->nullable()->constrained('goods_receives')->onDelete('set null');
                $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
                $table->date('bill_date');
                $table->date('due_date')->nullable();
                $table->string('payment_terms', 100)->nullable()->default('Net 30 Days');
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->decimal('paid_amount', 12, 2)->default(0.00);
                $table->decimal('due_amount', 12, 2)->default(0.00);
                $table->string('payment_status', 30)->default('unpaid')->index(); // 'unpaid', 'partially_paid', 'paid'
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_bill_items')) {
            Schema::create('purchase_bill_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->onDelete('cascade');
                $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
                $table->integer('quantity');
                $table->decimal('cost_price', 12, 2);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('total_cost', 12, 2);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('supplier_payments') && ! Schema::hasColumn('supplier_payments', 'purchase_bill_id')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->foreignId('purchase_bill_id')->nullable()->after('purchase_order_id')->constrained('purchase_bills')->onDelete('set null');
            });
        }

        if (Schema::hasTable('purchase_returns') && ! Schema::hasColumn('purchase_returns', 'purchase_bill_id')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->foreignId('purchase_order_id')->nullable()->change();
                $table->foreignId('purchase_bill_id')->nullable()->after('purchase_order_id')->constrained('purchase_bills')->onDelete('set null');
                $table->string('refund_mode', 50)->nullable()->after('total_return_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_returns') && Schema::hasColumn('purchase_returns', 'purchase_bill_id')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->dropForeign(['purchase_bill_id']);
                $table->dropColumn(['purchase_bill_id', 'refund_mode']);
            });
        }

        if (Schema::hasTable('supplier_payments') && Schema::hasColumn('supplier_payments', 'purchase_bill_id')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->dropForeign(['purchase_bill_id']);
                $table->dropColumn('purchase_bill_id');
            });
        }

        Schema::dropIfExists('purchase_bill_items');
        Schema::dropIfExists('purchase_bills');
        Schema::dropIfExists('goods_receive_items');
        Schema::dropIfExists('goods_receives');
    }
};
