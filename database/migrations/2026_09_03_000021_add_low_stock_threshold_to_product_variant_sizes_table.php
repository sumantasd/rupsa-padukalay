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
        Schema::table('product_variant_sizes', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->nullable()->after('selling_price');
            $table->integer('reorder_quantity')->nullable()->after('low_stock_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variant_sizes', function (Blueprint $table) {
            $table->dropColumn(['low_stock_threshold', 'reorder_quantity']);
        });
    }
};
