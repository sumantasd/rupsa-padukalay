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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique();
            $table->string('logo_url', 255)->nullable();
            $table->boolean('is_featured_on_web')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('name', 100);
            $table->string('slug', 110)->unique();
            $table->string('image_url', 255)->nullable();
            $table->boolean('is_visible_on_web')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('code', 20)->unique(); // e.g. BLK, BRN, TAN, BLU
            $table->string('hex_code', 10)->nullable(); // e.g. #000000
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('size_number', 20); // e.g. 6, 7, 8, 9, 10
            $table->string('size_system', 20)->default('UK/IND'); // UK/IND, EURO, US
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['size_number', 'size_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
