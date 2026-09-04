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
        Schema::create('pos_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->char('client_trans_uuid', 36)->index();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Cashier/Creator
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->string('conflict_type', 50); // e.g. insufficient_stock, invalid_sku, closed_pos_session, duplicate_uuid, invalid_payment, store_mismatch, other
            $table->text('conflict_reason');
            $table->json('payload_snapshot');
            $table->enum('status', ['unresolved', 'resolved', 'dismissed'])->default('unresolved');
            $table->string('resolution_action', 50)->nullable(); // reprocessed_sync, force_stock_override, dismissed_duplicate, manual_adjustment
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('conflict_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sync_conflicts');
    }
};
