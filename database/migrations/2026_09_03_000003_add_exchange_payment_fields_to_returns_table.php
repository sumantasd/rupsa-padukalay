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
        Schema::table('returns', function (Blueprint $table) {
            $table->decimal('price_difference', 12, 2)->default(0.00)->after('total_refund_amount');
            $table->string('payment_method', 50)->nullable()->after('price_difference');
            $table->decimal('amount_paid', 12, 2)->default(0.00)->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn(['price_difference', 'payment_method', 'amount_paid']);
        });
    }
};
