<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Customer Payments Table (For due collections & invoice payments)
        if (! Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 50)->unique();
                $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
                $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 50)->default('cash'); // cash, upi, card, bank_transfer, cheque, other
                $table->string('transaction_reference', 100)->nullable();
                $table->dateTime('payment_date');
                $table->text('notes')->nullable();
                $table->foreignId('collected_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['store_id', 'customer_id']);
                $table->index('payment_date');
            });
        }

        // 2. Customer Refunds Table (Transaction-linked refunds)
        if (! Schema::hasTable('customer_refunds')) {
            Schema::create('customer_refunds', function (Blueprint $table) {
                $table->id();
                $table->string('refund_number', 50)->unique();
                $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
                $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
                $table->decimal('amount', 12, 2);
                $table->string('refund_method', 50)->default('cash'); // cash, upi, card, bank_transfer, store_credit, other
                $table->string('transaction_reference', 100)->nullable();
                $table->string('reason', 255);
                $table->text('notes')->nullable();
                $table->foreignId('processed_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['store_id', 'created_at']);
            });
        }

        // 3. Day Closings Table (End-of-day financial snapshot & reconciliation)
        if (! Schema::hasTable('day_closings')) {
            Schema::create('day_closings', function (Blueprint $table) {
                $table->id();
                $table->string('closing_number', 50)->unique();
                $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
                $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
                $table->date('closing_date');
                $table->decimal('total_sales_cash', 12, 2)->default(0.00);
                $table->decimal('total_sales_card', 12, 2)->default(0.00);
                $table->decimal('total_sales_upi', 12, 2)->default(0.00);
                $table->decimal('total_sales_bank', 12, 2)->default(0.00);
                $table->decimal('total_sales_other', 12, 2)->default(0.00);
                $table->decimal('total_sales_grand', 12, 2)->default(0.00);
                $table->decimal('total_collections_cash', 12, 2)->default(0.00);
                $table->decimal('total_collections_digital', 12, 2)->default(0.00);
                $table->decimal('total_collections_grand', 12, 2)->default(0.00);
                $table->decimal('total_refunds_cash', 12, 2)->default(0.00);
                $table->decimal('total_refunds_digital', 12, 2)->default(0.00);
                $table->decimal('total_refunds_grand', 12, 2)->default(0.00);
                $table->decimal('total_expenses', 12, 2)->default(0.00);
                $table->decimal('opening_cash', 12, 2)->default(0.00);
                $table->decimal('cash_received', 12, 2)->default(0.00);
                $table->decimal('cash_paid_out', 12, 2)->default(0.00);
                $table->decimal('expected_cash', 12, 2)->default(0.00);
                $table->decimal('actual_cash', 12, 2)->default(0.00);
                $table->decimal('variance', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->foreignId('closed_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('reopened_by')->nullable()->constrained('users')->onDelete('set null');
                $table->dateTime('reopened_at')->nullable();
                $table->text('reopen_reason')->nullable();
                $table->enum('status', ['closed', 'reopened'])->default('closed');
                $table->json('snapshot_data')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['store_id', 'closing_date']);
                $table->index(['store_id', 'closing_date', 'status']);
            });
        }

        // 4. Alter pos_register_cash_movements movement_type to VARCHAR(50) if MySQL ENUM
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE pos_register_cash_movements MODIFY COLUMN movement_type VARCHAR(50) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closings');
        Schema::dropIfExists('customer_refunds');
        Schema::dropIfExists('customer_payments');
    }
};
