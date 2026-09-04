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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('from_store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('to_store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
            $table->enum('status', ['draft', 'pending', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->dateTime('transfer_date');
            $table->dateTime('received_date')->nullable();
            $table->foreignId('transferred_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->onDelete('cascade');
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->integer('quantity_sent');
            $table->integer('quantity_received')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
