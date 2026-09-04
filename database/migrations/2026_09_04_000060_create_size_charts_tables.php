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
        if (! Schema::hasTable('size_charts')) {
            Schema::create('size_charts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
                $table->string('gender', 20)->nullable(); // men, women, kids, unisex
                $table->string('age_group', 50)->nullable(); // adult, junior, infant, etc.
                $table->string('unit', 20)->default('CM');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('size_chart_columns')) {
            Schema::create('size_chart_columns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('size_chart_id')->constrained('size_charts')->onDelete('cascade');
                $table->string('name', 100);
                $table->string('key', 100);
                $table->string('data_type', 20)->default('text'); // text, number
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('size_chart_rows')) {
            Schema::create('size_chart_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('size_chart_id')->constrained('size_charts')->onDelete('cascade');
                $table->foreignId('size_id')->nullable()->constrained('sizes')->onDelete('set null');
                $table->string('size_value', 50)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('size_chart_values')) {
            Schema::create('size_chart_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('size_chart_row_id')->constrained('size_chart_rows')->onDelete('cascade');
                $table->foreignId('size_chart_column_id')->constrained('size_chart_columns')->onDelete('cascade');
                $table->string('value', 255)->nullable();
                $table->timestamps();
            });
        }

        // Add size_chart_id to products if not present
        if (! Schema::hasColumn('products', 'size_chart_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('size_chart_id')->nullable()->constrained('size_charts')->onDelete('set null');
            });
        }

        // Add default_size_chart_id to categories if not present
        if (! Schema::hasColumn('categories', 'default_size_chart_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('default_size_chart_id')->nullable()->constrained('size_charts')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('categories', 'default_size_chart_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['default_size_chart_id']);
                $table->dropColumn('default_size_chart_id');
            });
        }

        if (Schema::hasColumn('products', 'size_chart_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['size_chart_id']);
                $table->dropColumn('size_chart_id');
            });
        }

        Schema::dropIfExists('size_chart_values');
        Schema::dropIfExists('size_chart_rows');
        Schema::dropIfExists('size_chart_columns');
        Schema::dropIfExists('size_charts');
    }
};
