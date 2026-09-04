<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductImageRequest;
use App\Http\Requests\Master\UpdateProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $productId): JsonResponse
    {
        $product = Product::find($productId);
        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $query = ProductImage::where('product_id', $product->id);

        if ($request->has('product_variant_id')) {
            $query->where('product_variant_id', (int) $request->input('product_variant_id'));
        }

        $images = $query->orderBy('sort_order')->orderBy('id')->get();

        return $this->successResponse(
            ProductImageResource::collection($images),
            'Product images retrieved successfully.'
        );
    }

    public function store(StoreProductImageRequest $request, int $productId): JsonResponse
    {
        $product = Product::find($productId);
        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $isPrimary = $request->boolean('is_primary', false);
        $variantId = $request->input('product_variant_id');

        if ($isPrimary) {
            ProductImage::where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->update(['is_primary' => false]);
        }

        $imagePath = $request->input('image_path');
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imagePath = '/storage/'.$path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('products', 'public');
            $imagePath = '/storage/'.$path;
        }

        $image = ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'image_path' => trim((string) $imagePath),
            'alt_text' => $request->input('alt_text'),
            'is_primary' => $isPrimary,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new ProductImageResource($image),
            'Product image attached successfully.',
            201
        );
    }

    public function update(UpdateProductImageRequest $request, int $imageId): JsonResponse
    {
        $image = ProductImage::find($imageId);
        if (! $image) {
            return $this->errorResponse('Product image not found.', 404);
        }

        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            ProductImage::where('product_id', $image->product_id)
                ->where('product_variant_id', $image->product_variant_id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);
            $image->is_primary = true;
        }

        if ($request->has('image_path')) {
            $image->image_path = trim($request->input('image_path'));
        }
        if ($request->has('alt_text')) {
            $image->alt_text = $request->input('alt_text');
        }
        if ($request->has('sort_order')) {
            $image->sort_order = (int) $request->input('sort_order');
        }
        if ($request->has('is_active')) {
            $image->is_active = $request->boolean('is_active');
        }

        $image->save();

        return $this->successResponse(
            new ProductImageResource($image),
            'Product image metadata updated successfully.'
        );
    }

    public function setPrimary(int $imageId): JsonResponse
    {
        $image = ProductImage::find($imageId);
        if (! $image) {
            return $this->errorResponse('Product image not found.', 404);
        }

        ProductImage::where('product_id', $image->product_id)
            ->where('product_variant_id', $image->product_variant_id)
            ->update(['is_primary' => false]);

        $image->is_primary = true;
        $image->save();

        return $this->successResponse(
            new ProductImageResource($image),
            'Product image set as primary successfully.'
        );
    }

    public function toggleStatus(int $imageId): JsonResponse
    {
        $image = ProductImage::find($imageId);
        if (! $image) {
            return $this->errorResponse('Product image not found.', 404);
        }

        $image->is_active = ! $image->is_active;
        $image->save();

        $statusText = $image->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new ProductImageResource($image),
            "Product image {$statusText} successfully."
        );
    }
}
