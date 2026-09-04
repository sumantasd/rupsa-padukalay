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
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->onDelete('cascade');
            $table->integer('available_points')->default(0);
            $table->integer('lifetime_earned_points')->default(0);
            $table->integer('lifetime_redeemed_points')->default(0);
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->enum('transaction_type', ['earn', 'redeem', 'expire', 'adjust', 'reversal']);
            $table->integer('points');
            $table->integer('points_balance_before');
            $table->integer('points_balance_after');
            $table->string('reference_type', 50)->nullable(); // invoice, return, manual_adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->char('client_trans_uuid', 36)->nullable()->index();
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 100);
            $table->decimal('earn_rate_amount', 10, 2)->default(100.00); // Amount spent per earn_points
            $table->integer('earn_points')->default(1);
            $table->decimal('redeem_point_value', 10, 2)->default(1.00); // Currency value per point
            $table->decimal('min_qualifying_amount', 10, 2)->default(0.00);
            $table->integer('min_redemption_points')->default(10);
            $table->integer('max_redemption_points')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->onDelete('cascade');
            $table->decimal('current_balance', 12, 2)->default(0.00);
            $table->decimal('total_issued', 12, 2)->default(0.00);
            $table->decimal('total_used', 12, 2)->default(0.00);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });

        Schema::create('store_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->enum('transaction_type', ['issue_refund', 'issue_adjustment', 'payment_used', 'reversal']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type', 50)->nullable(); // invoice, return, manual_adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->char('client_trans_uuid', 36)->nullable()->index();
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_credit_transactions');
        Schema::dropIfExists('store_credit_accounts');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
    }
};
