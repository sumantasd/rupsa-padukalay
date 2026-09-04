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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('article_number', 100)->index();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('hsn_code_id')->nullable()->constrained('hsn_codes')->onDelete('set null');
            $table->enum('gender', ['men', 'women', 'boys', 'girls', 'unisex'])->default('unisex');
            $table->string('upper_material', 100)->nullable();
            $table->string('sole_material', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible_on_web')->default(true);
            $table->boolean('is_featured_on_web')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['article_number', 'brand_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('color_id')->constrained('colors')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'color_id']);
        });

        Schema::create('product_variant_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('size_id')->constrained('sizes')->onDelete('cascade');
            $table->string('sku', 100)->unique(); // ART805-BLK-08
            $table->string('barcode', 100)->unique()->nullable(); // Optional scan code
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('mrp', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_variant_id', 'size_id']);
            $table->index(['product_variant_id', 'size_id']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->string('image_path', 255);
            $table->string('alt_text', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variant_sizes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
