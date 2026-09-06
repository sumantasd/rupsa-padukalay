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
        Schema::table('expense_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_categories', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }
            if (! Schema::hasColumn('expense_categories', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (! Schema::hasColumn('expense_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
            if (! Schema::hasColumn('expense_categories', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'expense_number')) {
                $table->string('expense_number', 50)->nullable()->after('id');
            }
            if (! Schema::hasColumn('expenses', 'payee_name')) {
                $table->string('payee_name', 255)->nullable()->after('expense_category_id');
            }
            if (! Schema::hasColumn('expenses', 'status')) {
                $table->enum('status', ['draft', 'paid', 'cancelled'])->default('paid')->after('payment_method');
            }
            if (! Schema::hasColumn('expenses', 'attachment_url')) {
                $table->string('attachment_url', 500)->nullable()->after('description');
            }
            if (! Schema::hasColumn('expenses', 'notes')) {
                $table->text('notes')->nullable()->after('attachment_url');
            }
            if (! Schema::hasColumn('expenses', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['expense_number', 'payee_name', 'status', 'attachment_url', 'notes', 'deleted_at']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'is_active', 'deleted_at']);
        });
    }
};
