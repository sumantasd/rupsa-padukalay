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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->unique()->index();
            $table->enum('promotion_type', ['percentage', 'fixed_amount', 'bogo'])->default('percentage');
            $table->enum('discount_scope', ['cart', 'product', 'category', 'brand'])->default('cart');
            $table->decimal('discount_value', 10, 2)->default(0.00); // e.g. 15.00 for 15% or 200.00 for ₹200
            $table->integer('buy_quantity')->default(1); // BOGO buy quantity
            $table->integer('get_quantity')->default(1); // BOGO get quantity
            $table->decimal('get_discount_percentage', 5, 2)->default(100.00); // BOGO get discount % (100 = free)
            $table->decimal('min_cart_amount', 10, 2)->default(0.00);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('allow_stacking')->default(false);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_customer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'store_id']);
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->string('target_type', 50); // product, category, brand
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->index(['promotion_id', 'target_type', 'target_id']);
        });

        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->decimal('discount_amount_applied', 10, 2)->default(0.00);
            $table->char('client_trans_uuid', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['promotion_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
