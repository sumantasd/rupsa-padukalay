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
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 150)->unique();
            $table->string('file_path', 255);
            $table->unsignedBigInteger('file_size');
            $table->string('status', 20)->default('completed'); // completed, failed
            $table->string('type', 30)->default('manual'); // manual, auto_pre_reset
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('database_reset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reset_type', 50); // demo_data_reset, business_data_reset, custom_reset
            $table->json('selected_categories');
            $table->string('backup_filename', 150)->nullable();
            $table->unsignedBigInteger('backup_file_size')->nullable();
            $table->json('cleared_tables');
            $table->json('preserved_tables');
            $table->string('status', 20)->default('success'); // success, failed
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_reset_logs');
        Schema::dropIfExists('database_backups');
    }
};
