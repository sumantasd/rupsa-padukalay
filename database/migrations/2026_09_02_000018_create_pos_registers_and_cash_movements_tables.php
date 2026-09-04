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
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index(['store_id', 'is_active']);
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreignId('pos_register_id')->nullable()->after('client_session_uuid')->constrained('pos_registers')->onDelete('set null');
        });

        Schema::create('pos_register_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->onDelete('cascade');
            $table->foreignId('pos_register_id')->constrained('pos_registers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('movement_type', ['cash_in', 'cash_out', 'drawer_drop']);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->string('reference_number', 100)->nullable();
            $table->char('client_trans_uuid', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['pos_session_id', 'movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_register_cash_movements');

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropForeign(['pos_register_id']);
            $table->dropColumn('pos_register_id');
        });

        Schema::dropIfExists('pos_registers');
    }
};
