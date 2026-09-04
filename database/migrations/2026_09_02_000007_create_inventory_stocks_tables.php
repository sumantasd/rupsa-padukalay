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
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            
            // Stock Scope Dimensions (NOT NULL with default 0 for unassigned dimension to enforce MySQL UNIQUE index strictly)
            $table->unsignedBigInteger('store_id')->default(0)->index();
            $table->unsignedBigInteger('warehouse_id')->default(0)->index();
            $table->unsignedBigInteger('stock_location_id')->default(0)->index();
            
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(3);
            $table->timestamps();

            // Strict UNIQUE index enforcing 1 Stock Record per SKU + Store + Warehouse + Location scope
            $table->unique(['product_variant_size_id', 'store_id', 'warehouse_id', 'stock_location_id'], 'uniq_inv_stock_scope');
            $table->index(['store_id', 'stock_quantity']);
            $table->index(['warehouse_id', 'stock_quantity']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
            $table->string('reason', 50)->default('physical_count');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->onDelete('cascade');
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->integer('old_quantity');
            $table->integer('new_quantity');
            $table->integer('quantity_adjusted'); // Difference (+ or -)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('inventory_stocks');
    }
};
