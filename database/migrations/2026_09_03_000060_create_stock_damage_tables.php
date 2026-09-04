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
        Schema::create('stock_damage_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('damage_number', 50)->unique();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('reason', 50)->default('damaged');
            $table->text('remarks')->nullable();
            $table->integer('total_quantity')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('stock_damage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_damage_transaction_id')->constrained('stock_damage_transactions')->onDelete('cascade');
            $table->foreignId('product_variant_size_id')->constrained('product_variant_sizes')->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('available_stock_before');
            $table->integer('available_stock_after');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_damage_items');
        Schema::dropIfExists('stock_damage_transactions');
    }
};
