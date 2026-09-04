<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'code')) {
                $table->string('code', 50)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('suppliers', 'alternate_mobile')) {
                $table->string('alternate_mobile', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('suppliers', 'pan')) {
                $table->string('pan', 20)->nullable()->after('gstin');
            }
            if (! Schema::hasColumn('suppliers', 'state')) {
                $table->string('state', 100)->nullable()->default('West Bengal')->after('city');
            }
            if (! Schema::hasColumn('suppliers', 'pincode')) {
                $table->string('pincode', 10)->nullable()->after('state');
            }
            if (! Schema::hasColumn('suppliers', 'opening_balance')) {
                $table->decimal('opening_balance', 12, 2)->default(0.00)->after('current_balance');
            }
            if (! Schema::hasColumn('suppliers', 'opening_balance_type')) {
                $table->enum('opening_balance_type', ['payable', 'advance'])->default('payable')->after('opening_balance');
            }
            if (! Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->string('payment_terms', 100)->nullable()->default('Net 30 Days')->after('opening_balance_type');
            }
            if (! Schema::hasColumn('suppliers', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->default(0.00)->after('payment_terms');
            }
            if (! Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable()->after('credit_limit');
            }
            if (! Schema::hasColumn('suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });

        if (! Schema::hasTable('supplier_payments')) {
            Schema::create('supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 50)->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('set null');
                $table->date('payment_date');
                $table->decimal('amount', 12, 2);
                $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'card', 'other'])->default('cash');
                $table->string('transaction_reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'alternate_mobile',
                'pan',
                'state',
                'pincode',
                'opening_balance',
                'opening_balance_type',
                'payment_terms',
                'credit_limit',
                'notes',
                'is_active',
            ]);
        });
    }
};
