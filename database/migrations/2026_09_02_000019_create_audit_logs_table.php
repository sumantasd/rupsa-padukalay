<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audit_uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
            $table->foreignId('pos_register_id')->nullable()->constrained('pos_registers')->nullOnDelete();
            $table->string('module', 50)->index();
            $table->string('event_type', 50)->index();
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('client_trans_uuid', 36)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('status', 20)->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->text('reason_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Composite indexes for fast query filtering
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['store_id', 'module', 'event_type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['client_trans_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
