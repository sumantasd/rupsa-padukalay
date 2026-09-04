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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->onDelete('set null');
            $table->string('movement_type', 50)->index();
            $table->string('reference_type', 100)->nullable(); // e.g. App\Models\Invoice, App\Models\PurchaseOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('quantity_change'); // Positive (+) for additions, Negative (-) for deductions
            $table->integer('stock_before')->default(0);
            $table->integer('stock_after')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['product_variant_size_id', 'store_id', 'created_at'], 'idx_stock_mov_pvs_store_date');
            $table->index(['product_variant_size_id', 'warehouse_id', 'created_at'], 'idx_stock_mov_pvs_wh_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
