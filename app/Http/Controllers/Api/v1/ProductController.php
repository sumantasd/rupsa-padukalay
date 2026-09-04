<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductRequest;
use App\Http\Requests\Master\StoreProductVariantRequest;
use App\Http\Requests\Master\StoreVariantSizeRequest;
use App\Http\Requests\Master\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Http\Resources\ProductVariantSizeResource;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Size;
use App\Enums\StockMovementType;

use App\Models\ProductImage;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;




class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Product::with([
            'brand',
            'category',
            'hsnCode',
            'images',
            'variants.color',
            'variants.sizes.size',
            'variants.sizes.inventoryStocks',
        ]);

        if ($request->has('article_number')) {
            $article = trim((string) $request->input('article_number'));
            $query->where('article_number', 'LIKE', "{$article}%");
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('article_number', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', (int) $request->input('brand_id'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $products = $query->orderBy('article_number')->get();

        return $this->successResponse(
            ProductResource::collection($products),
            'Products retrieved successfully.'
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $article = strtoupper(trim($request->input('article_number')));
        $name = trim($request->input('name'));
        $slug = Str::slug("{$article}-{$name}");

        $originalSlug = $slug;
        $count = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $product = Product::create([
            'article_number' => $article,
            'name' => $name,
            'slug' => $slug,
            'brand_id' => $request->input('brand_id'),
            'category_id' => $request->input('category_id'),
            'hsn_code_id' => $request->input('hsn_code_id'),
            'size_chart_id' => $request->input('size_chart_id'),
            'gender' => $request->input('gender', 'unisex'),
            'upper_material' => $request->input('upper_material'),
            'sole_material' => $request->input('sole_material'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
            'is_visible_on_web' => $request->boolean('is_visible_on_web', true),
        ]);

        return $this->successResponse(
            new ProductResource($product->load(['brand', 'category', 'hsnCode', 'variants'])),
            'Product created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with([
            'brand',
            'category',
            'hsnCode',
            'sizeChart.columns',
            'sizeChart.rows.values',
            'variants.color',
            'variants.sizes.size',
            'variants.sizes.inventoryStocks',
        ])->find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        return $this->successResponse(
            new ProductResource($product),
            'Product details retrieved successfully.'
        );
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $article = strtoupper(trim($request->input('article_number')));
        $name = trim($request->input('name'));

        if ($product->article_number !== $article || $product->name !== $name) {
            $slug = Str::slug("{$article}-{$name}");
            $originalSlug = $slug;
            $count = 1;
            while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
            $product->slug = $slug;
        }

        $product->article_number = $article;
        $product->name = $name;
        $product->brand_id = $request->input('brand_id');
        $product->category_id = $request->input('category_id');

        if ($request->has('size_chart_id')) {
            $product->size_chart_id = $request->input('size_chart_id');
        }

        if ($request->has('hsn_code_id')) {
            $product->hsn_code_id = $request->input('hsn_code_id');
        }
        if ($request->has('gender')) {
            $product->gender = $request->input('gender');
        }
        if ($request->has('upper_material')) {
            $product->upper_material = $request->input('upper_material');
        }
        if ($request->has('sole_material')) {
            $product->sole_material = $request->input('sole_material');
        }
        if ($request->has('description')) {
            $product->description = $request->input('description');
        }
        if ($request->has('is_active')) {
            $product->is_active = $request->boolean('is_active');
        }
        if ($request->has('is_visible_on_web')) {
            $product->is_visible_on_web = $request->boolean('is_visible_on_web');
        }

        $product->save();

        return $this->successResponse(
            new ProductResource($product->load(['brand', 'category', 'hsnCode', 'variants'])),
            'Product updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $product->is_active = ! $product->is_active;
        $product->save();

        $statusText = $product->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new ProductResource($product),
            "Product {$statusText} successfully."
        );
    }

    public function storeVariant(StoreProductVariantRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $colorId = (int) $request->input('color_id');

        // Check duplicate color variant for same product
        $existing = ProductVariant::where('product_id', $product->id)
            ->where('color_id', $colorId)
            ->first();

        if ($existing) {
            return $this->errorResponse(
                'This product already has a variant in the selected colour.',
                422,
                ['color_id' => ['Duplicate colour variant for this product.']]
            );
        }

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $colorId,
            'is_active' => true,
        ]);

        return $this->successResponse(
            new ProductVariantResource($variant->load(['color', 'sizes'])),
            'Product colour variant created successfully.',
            201
        );
    }

    public function storeVariantSize(StoreVariantSizeRequest $request, int $id, int $variantId): JsonResponse
    {
        $product = Product::find($id);
        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)->find($variantId);
        if (! $variant) {
            return $this->errorResponse('Product variant not found.', 404);
        }

        $sizeId = (int) $request->input('size_id');

        // Duplicate size check
        $existingSize = ProductVariantSize::where('product_variant_id', $variant->id)
            ->where('size_id', $sizeId)
            ->first();

        if ($existingSize) {
            return $this->errorResponse(
                'The same variant cannot have the same size twice.',
                422,
                ['size_id' => ['Duplicate size for this product variant.']]
            );
        }

        $color = Color::findOrFail($variant->color_id);
        $size = Size::findOrFail($sizeId);

        // Auto-generate SKU if not manually specified: [ARTICLE]-[COLOR_CODE]-[SIZE]
        $sku = $request->input('sku');
        if (empty($sku)) {
            $artCode = preg_replace('/[^A-Za-z0-9]/', '', $product->article_number);
            $colorCode = strtoupper($color->code);
            $sizeNum = str_pad($size->size_number, 2, '0', STR_PAD_LEFT);
            $sku = "{$artCode}-{$colorCode}-{$sizeNum}";
        } else {
            $sku = strtoupper(trim($sku));
        }

        // Validate SKU uniqueness
        if (ProductVariantSize::where('sku', $sku)->exists()) {
            return $this->errorResponse(
                'Duplicate SKU code detected.',
                422,
                ['sku' => ['The SKU code must be unique across all products.']]
            );
        }

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => $sku,
            'barcode' => $request->input('barcode'),
            'cost_price' => (float) ($request->input('cost_price', 0.00)),
            'mrp' => (float) ($request->input('mrp', 0.00)),
            'selling_price' => (float) ($request->input('selling_price', 0.00)),
            'is_active' => true,
        ]);

        return $this->successResponse(
            new ProductVariantSizeResource($variantSize->load('size')),
            'Size SKU created successfully.',
            201
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);
        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $variantIds = $product->variants()->pluck('id');
        $sizeIds = ProductVariantSize::whereIn('product_variant_id', $variantIds)->pluck('id')->toArray();

        // Transactional dependency checks
        $hasInvoiceItems = ! empty($sizeIds) && \App\Models\InvoiceItem::whereIn('product_variant_size_id', $sizeIds)->exists();
        $hasReturnItems = ! empty($sizeIds) && \App\Models\ReturnItem::whereIn('product_variant_size_id', $sizeIds)->exists();
        $hasPurchaseOrderItems = ! empty($sizeIds) && \App\Models\PurchaseOrderItem::whereIn('product_variant_size_id', $sizeIds)->exists();
        $hasPurchaseReturnItems = ! empty($sizeIds) && \App\Models\PurchaseReturnItem::whereIn('product_variant_size_id', $sizeIds)->exists();
        $hasStockMovements = ! empty($sizeIds) && \App\Models\StockMovement::whereIn('product_variant_size_id', $sizeIds)->exists();
        $hasActiveStock = ! empty($sizeIds) && \App\Models\InventoryStock::whereIn('product_variant_size_id', $sizeIds)->where('stock_quantity', '>', 0)->exists();

        if ($hasInvoiceItems || $hasReturnItems || $hasPurchaseOrderItems || $hasPurchaseReturnItems || $hasStockMovements || $hasActiveStock) {
            return $this->errorResponse(
                'This product cannot be deleted because transaction/history records exist. You can deactivate this product instead.',
                422,
                [
                    'reason' => 'Transactional records exist for this product.',
                    'can_deactivate' => true,
                ]
            );
        }

        // Perform safe atomic database transaction
        DB::transaction(function () use ($product, $variantIds, $sizeIds) {
            if (! empty($sizeIds)) {
                \App\Models\InventoryStock::whereIn('product_variant_size_id', $sizeIds)->delete();
                ProductVariantSize::whereIn('id', $sizeIds)->delete();
            }

            if ($variantIds->isNotEmpty()) {
                ProductVariant::whereIn('id', $variantIds)->delete();
            }

            ProductImage::where('product_id', $product->id)->delete();
            $product->forceDelete();
        });

        return $this->successResponse(null, 'Product deleted successfully.');
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'article_number' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'hsn_code_id' => ['nullable', 'integer', 'exists:hsn_codes,id'],
            'gender' => ['nullable', 'string', 'in:men,women,kids,unisex'],
            'upper_material' => ['nullable', 'string', 'max:255'],
            'sole_material' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $savedImagePath = null;
        $user = $request->user();

        try {
            $product = DB::transaction(function () use ($request, $user, &$savedImagePath) {
                $article = strtoupper(trim($request->input('article_number')));
                $name = trim($request->input('name'));
                $slug = Str::slug("{$article}-{$name}");

                $originalSlug = $slug;
                $count = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = "{$originalSlug}-{$count}";
                    $count++;
                }

                // 1. Create Parent Product Article
                $product = Product::create([
                    'article_number' => $article,
                    'name' => $name,
                    'slug' => $slug,
                    'brand_id' => (int) $request->input('brand_id'),
                    'category_id' => (int) $request->input('category_id'),
                    'size_chart_id' => $request->input('size_chart_id') ? (int) $request->input('size_chart_id') : null,
                    'hsn_code_id' => $request->input('hsn_code_id') ? (int) $request->input('hsn_code_id') : null,
                    'gender' => $request->input('gender', 'unisex'),
                    'upper_material' => $request->input('upper_material'),
                    'sole_material' => $request->input('sole_material'),
                    'description' => $request->input('description'),
                    'is_active' => $request->boolean('is_active', true),
                    'is_visible_on_web' => $request->boolean('is_visible_on_web', true),
                ]);

                // 2. Upload Image File if present
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('products', 'public');
                    $imagePath = '/storage/' . $path;
                    $savedImagePath = $path;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                        'is_primary' => true,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]);
                }

                // 3. Process Color Variants & Size SKUs
                $rawColorBlocks = $request->input('color_blocks');
                if (is_string($rawColorBlocks)) {
                    $colorBlocks = json_decode($rawColorBlocks, true) ?: [];
                } else {
                    $colorBlocks = (array) $rawColorBlocks;
                }

                $createdSkusForOpeningStock = [];

                foreach ($colorBlocks as $block) {
                    $colorId = (int) ($block['color_id'] ?? 0);
                    if (! $colorId) continue;

                    $color = \App\Models\Color::find($colorId);
                    if (! $color) continue;

                    $variant = ProductVariant::firstOrCreate([
                        'product_id' => $product->id,
                        'color_id' => $colorId,
                    ], [
                        'is_active' => true,
                    ]);

                    $sizeRows = (array) ($block['size_rows'] ?? []);
                    foreach ($sizeRows as $row) {
                        $sizeId = (int) ($row['size_id'] ?? 0);
                        if (! $sizeId) continue;

                        $size = \App\Models\Size::find($sizeId);
                        if (! $size) continue;

                        $artCode = preg_replace('/[^A-Za-z0-9]/', '', $article);
                        $colorCode = strtoupper($color->code);
                        $sizeNum = str_pad($size->size_number, 2, '0', STR_PAD_LEFT);
                        $sku = ! empty($row['sku']) ? strtoupper(trim($row['sku'])) : "{$artCode}-{$colorCode}-{$sizeNum}";

                        $variantSize = ProductVariantSize::create([
                            'product_variant_id' => $variant->id,
                            'size_id' => $size->id,
                            'sku' => $sku,
                            'barcode' => $row['barcode'] ?? null,
                            'cost_price' => (float) ($row['cost_price'] ?? $request->input('cost_price', 0.00)),
                            'mrp' => (float) ($row['mrp'] ?? $request->input('mrp', 0.00)),
                            'selling_price' => (float) ($row['selling_price'] ?? $request->input('selling_price', 0.00)),
                            'low_stock_threshold' => isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : null,
                            'reorder_quantity' => isset($row['reorder_quantity']) ? (int) $row['reorder_quantity'] : null,
                            'is_active' => true,
                        ]);

                        $opStock = (int) ($row['opening_stock'] ?? 0);
                        if ($opStock > 0) {
                            $createdSkusForOpeningStock[] = [
                                'variant_size' => $variantSize,
                                'quantity' => $opStock,
                            ];
                        }
                    }
                }

                // 4. Record Opening Stock under Store 1 (STR-001)
                if (! empty($createdSkusForOpeningStock)) {
                    $adjNumber = 'ADJ-' . date('Ymd') . '-' . strtoupper(Str::random(6));

                    $adjRecord = StockAdjustment::create([
                        'adjustment_number' => $adjNumber,
                        'store_id' => 1, // Store ID 1 (STR-001)
                        'warehouse_id' => null,
                        'reason' => 'opening_stock',
                        'notes' => "Initial opening stock entry for article {$article}",
                        'created_by' => $user?->id ?? 1,
                    ]);

                    foreach ($createdSkusForOpeningStock as $item) {
                        $vSize = $item['variant_size'];
                        $qty = $item['quantity'];

                        $this->inventoryService->addStock(
                            $vSize->id,
                            $qty,
                            StockMovementType::ADJUSTMENT_ADD,
                            StockAdjustment::class,
                            $adjRecord->id,
                            1, // Store ID 1 (STR-001)
                            0,
                            0,
                            $user,
                            "Initial opening stock entry"
                        );

                        StockAdjustmentItem::create([
                            'stock_adjustment_id' => $adjRecord->id,
                            'product_variant_size_id' => $vSize->id,
                            'old_quantity' => 0,
                            'new_quantity' => $qty,
                            'quantity_adjusted' => $qty,
                        ]);
                    }
                }

                return $product->load([
                    'brand',
                    'category',
                    'hsnCode',
                    'images',
                    'variants.color',
                    'variants.sizes.size',
                    'variants.sizes.inventoryStocks',
                ]);
            });

            return $this->successResponse(
                new ProductResource($product),
                'Product created successfully with variants & opening stock.',
                201
            );

        } catch (\Throwable $e) {
            if ($savedImagePath && Storage::disk('public')->exists($savedImagePath)) {
                Storage::disk('public')->delete($savedImagePath);
            }

            return $this->errorResponse('Product creation failed: ' . $e->getMessage(), 422);
        }
    }

    public function bulkUpdate(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);
        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $request->validate([
            'article_number' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'hsn_code_id' => ['nullable', 'integer', 'exists:hsn_codes,id'],
            'gender' => ['nullable', 'string', 'in:men,women,kids,unisex'],
            'upper_material' => ['nullable', 'string', 'max:255'],
            'sole_material' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $savedImagePath = null;
        $user = $request->user();

        try {
            $updatedProduct = DB::transaction(function () use ($request, $product, $user, &$savedImagePath) {
                $article = strtoupper(trim($request->input('article_number')));
                $name = trim($request->input('name'));

                if ($product->article_number !== $article || $product->name !== $name) {
                    $slug = Str::slug("{$article}-{$name}");
                    $originalSlug = $slug;
                    $count = 1;
                    while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                        $slug = "{$originalSlug}-{$count}";
                        $count++;
                    }
                    $product->slug = $slug;
                }

                $product->article_number = $article;
                $product->name = $name;
                $product->brand_id = (int) $request->input('brand_id');
                $product->category_id = (int) $request->input('category_id');
                $product->size_chart_id = $request->input('size_chart_id') ? (int) $request->input('size_chart_id') : null;
                $product->hsn_code_id = $request->input('hsn_code_id') ? (int) $request->input('hsn_code_id') : null;
                $product->gender = $request->input('gender', 'unisex');
                $product->upper_material = $request->input('upper_material');
                $product->sole_material = $request->input('sole_material');
                $product->description = $request->input('description');
                if ($request->has('is_active')) {
                    $product->is_active = $request->boolean('is_active');
                }
                if ($request->has('is_visible_on_web')) {
                    $product->is_visible_on_web = $request->boolean('is_visible_on_web');
                }
                $product->save();

                // Process Image Upload if present
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('products', 'public');
                    $imagePath = '/storage/' . $path;
                    $savedImagePath = $path;

                    ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                        'is_primary' => true,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]);
                }

                // Process Color Blocks & Size SKUs
                $rawColorBlocks = $request->input('color_blocks');
                if (is_string($rawColorBlocks)) {
                    $colorBlocks = json_decode($rawColorBlocks, true) ?: [];
                } else {
                    $colorBlocks = (array) $rawColorBlocks;
                }

                $stockAdjustmentsToApply = [];

                foreach ($colorBlocks as $block) {
                    $colorId = (int) ($block['color_id'] ?? 0);
                    if (! $colorId) continue;

                    $color = \App\Models\Color::find($colorId);
                    if (! $color) continue;

                    $variant = ProductVariant::firstOrCreate([
                        'product_id' => $product->id,
                        'color_id' => $colorId,
                    ], [
                        'is_active' => true,
                    ]);

                    $sizeRows = (array) ($block['size_rows'] ?? []);
                    foreach ($sizeRows as $row) {
                        $sizeId = (int) ($row['size_id'] ?? 0);
                        if (! $sizeId) continue;

                        $size = \App\Models\Size::find($sizeId);
                        if (! $size) continue;

                        $artCode = preg_replace('/[^A-Za-z0-9]/', '', $article);
                        $colorCode = strtoupper($color->code);
                        $sizeNum = str_pad($size->size_number, 2, '0', STR_PAD_LEFT);
                        $sku = ! empty($row['sku']) ? strtoupper(trim($row['sku'])) : "{$artCode}-{$colorCode}-{$sizeNum}";

                        $variantSize = ProductVariantSize::where('product_variant_id', $variant->id)
                            ->where('size_id', $size->id)
                            ->first();

                        if (! $variantSize) {
                            $variantSize = ProductVariantSize::create([
                                'product_variant_id' => $variant->id,
                                'size_id' => $size->id,
                                'sku' => $sku,
                                'barcode' => $row['barcode'] ?? null,
                                'cost_price' => (float) ($row['cost_price'] ?? $request->input('cost_price', 0.00)),
                                'mrp' => (float) ($row['mrp'] ?? $request->input('mrp', 0.00)),
                                'selling_price' => (float) ($row['selling_price'] ?? $request->input('selling_price', 0.00)),
                                'is_active' => true,
                            ]);
                        } else {
                            $variantSize->sku = $sku;
                            if (isset($row['mrp'])) $variantSize->mrp = (float) $row['mrp'];
                            if (isset($row['selling_price'])) $variantSize->selling_price = (float) $row['selling_price'];
                            if (isset($row['cost_price'])) $variantSize->cost_price = (float) $row['cost_price'];
                            if (array_key_exists('low_stock_threshold', $row)) $variantSize->low_stock_threshold = $row['low_stock_threshold'] !== null ? (int) $row['low_stock_threshold'] : null;
                            if (array_key_exists('reorder_quantity', $row)) $variantSize->reorder_quantity = $row['reorder_quantity'] !== null ? (int) $row['reorder_quantity'] : null;
                            $variantSize->save();
                        }

                        if (isset($row['opening_stock']) && is_numeric($row['opening_stock'])) {
                            $newStockQty = (int) $row['opening_stock'];
                            $currentStockObj = $this->inventoryService->getStockRecord($variantSize->id, 1, 0, 0);
                            $currentQty = $currentStockObj->stock_quantity;
                            $diff = $newStockQty - $currentQty;

                            if ($diff != 0) {
                                $stockAdjustmentsToApply[] = [
                                    'variant_size' => $variantSize,
                                    'diff' => $diff,
                                    'old_qty' => $currentQty,
                                    'new_qty' => $newStockQty,
                                ];
                            }
                        }
                    }
                }

                if (! empty($stockAdjustmentsToApply)) {
                    $adjNumber = 'ADJ-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                    $adjRecord = StockAdjustment::create([
                        'adjustment_number' => $adjNumber,
                        'store_id' => 1,
                        'warehouse_id' => null,
                        'reason' => 'physical_count',
                        'notes' => "Stock adjustment during product edit for article {$article}",
                        'created_by' => $user?->id ?? 1,
                    ]);

                    foreach ($stockAdjustmentsToApply as $item) {
                        $vSize = $item['variant_size'];
                        $diff = $item['diff'];

                        if ($diff > 0) {
                            $this->inventoryService->addStock(
                                $vSize->id,
                                $diff,
                                StockMovementType::ADJUSTMENT_ADD,
                                StockAdjustment::class,
                                $adjRecord->id,
                                1,
                                0,
                                0,
                                $user,
                                "Stock updated during product edit"
                            );
                        } else {
                            $this->inventoryService->deductStock(
                                $vSize->id,
                                abs($diff),
                                StockMovementType::ADJUSTMENT_DEDUCT,
                                StockAdjustment::class,
                                $adjRecord->id,
                                1,
                                0,
                                0,
                                $user,
                                true,
                                "Stock updated during product edit"
                            );
                        }

                        StockAdjustmentItem::create([
                            'stock_adjustment_id' => $adjRecord->id,
                            'product_variant_size_id' => $vSize->id,
                            'old_quantity' => $item['old_qty'],
                            'new_quantity' => $item['new_qty'],
                            'quantity_adjusted' => $diff,
                        ]);
                    }
                }

                return $product->load([
                    'brand',
                    'category',
                    'hsnCode',
                    'images',
                    'variants.color',
                    'variants.sizes.size',
                    'variants.sizes.inventoryStocks',
                ]);
            });

            return $this->successResponse(
                new ProductResource($updatedProduct),
                'Product updated successfully.',
                200
            );

        } catch (\Throwable $e) {
            if ($savedImagePath && Storage::disk('public')->exists($savedImagePath)) {
                Storage::disk('public')->delete($savedImagePath);
            }

            return $this->errorResponse('Product update failed: ' . $e->getMessage(), 422);
        }
    }

    public function sizeMatrixSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $storeId = (int) $request->input('store_id', 1);

        $query = Product::with([
            'brand',
            'category',
            'images',
            'variants.color',
            'variants.sizes.size',
            'variants.sizes.inventoryStocks' => function ($sq) use ($storeId) {
                $sq->where('store_id', $storeId);
            },
        ])->where('is_active', true);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('article_number', 'LIKE', "%{$q}%")
                    ->orWhereHas('brand', function ($bq) use ($q) {
                        $bq->where('name', 'LIKE', "%{$q}%");
                    })->orWhereHas('category', function ($cq) use ($q) {
                        $cq->where('name', 'LIKE', "%{$q}%");
                    })->orWhereHas('variants.color', function ($colq) use ($q) {
                        $colq->where('name', 'LIKE', "%{$q}%");
                    })->orWhereHas('variants.sizes', function ($vsq) use ($q) {
                        $vsq->where('sku', 'LIKE', "%{$q}%")
                            ->orWhere('barcode', 'LIKE', "%{$q}%");
                    });
            });
        }

        $products = $query->take(20)->get();

        $result = $products->map(function ($product) {
            $primaryImage = $product->images ? ($product->images->where('is_primary', true)->first() ?? $product->images->first()) : null;
            $imageUrl = null;
            if ($primaryImage && ! empty($primaryImage->image_path)) {
                $path = trim((string) $primaryImage->image_path);
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
                    $imageUrl = $path;
                } else {
                    $imageUrl = '/storage/' . ltrim($path, '/');
                }
            }

            $variants = $product->variants->map(function ($variant) {
                $sizes = $variant->sizes->map(function ($pvs) {
                    $stockObj = $pvs->inventoryStocks->first();
                    $currentStock = $stockObj ? (int) $stockObj->stock_quantity : 0;
                    $sizeNum = $pvs->size?->size_number ?? 'N/A';
                    $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND')
                        ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum)
                        : $sizeNum;

                    return [
                        'product_variant_size_id' => $pvs->id,
                        'sku' => $pvs->sku,
                        'barcode' => $pvs->barcode ?? null,
                        'size_id' => $pvs->size_id,
                        'size_number' => $sizeNum,
                        'size_display' => $sizeDisplay,
                        'current_stock' => $currentStock,
                        'cost_price' => (float) ($pvs->cost_price ?? 0),
                        'mrp' => (float) ($pvs->mrp ?? 0),
                        'selling_price' => (float) ($pvs->selling_price ?? 0),
                    ];
                })->sortBy(function ($s) {
                    return (int) preg_replace('/[^0-9]/', '', $s['size_number']) ?: 99;
                })->values();

                return [
                    'variant_id' => $variant->id,
                    'color_name' => $variant->color?->name ?? 'Default Color',
                    'sizes' => $sizes,
                ];
            });

            return [
                'id' => $product->id,
                'article_number' => $product->article_number ?? 'N/A',
                'product_name' => $product->name,
                'brand_name' => $product->brand?->name ?? 'Generic Brand',
                'category_name' => $product->category?->name ?? 'Footwear',
                'image_url' => $imageUrl,
                'primary_image_url' => $imageUrl,
                'product_image' => $imageUrl,
                'variants' => $variants,
            ];
        });

        return $this->successResponse($result, 'Products size matrix search results retrieved successfully.');
    }
}

