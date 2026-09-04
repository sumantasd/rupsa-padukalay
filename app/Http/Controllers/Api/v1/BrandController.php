<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreBrandRequest;
use App\Http\Requests\Master\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Brand::withCount('products');

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $brands = $query->orderBy('name')->get();

        return $this->successResponse(
            BrandResource::collection($brands),
            'Brands retrieved successfully.'
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $name = trim($request->input('name'));
        $slug = Str::slug($name);

        // Ensure unique slug
        $originalSlug = $slug;
        $count = 1;
        while (Brand::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $logoUrl = $request->input('logo_url');
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $logoUrl = '/storage/'.$path;
        } elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $logoUrl = '/storage/'.$path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('brands', 'public');
            $logoUrl = '/storage/'.$path;
        }

        $brand = Brand::create([
            'name' => $name,
            'slug' => $slug,
            'logo_url' => $logoUrl,
            'is_featured_on_web' => $request->boolean('is_featured_on_web', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new BrandResource($brand),
            'Brand created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $brand = Brand::withCount('products')->find($id);

        if (! $brand) {
            return $this->errorResponse('Brand not found.', 404);
        }

        return $this->successResponse(
            new BrandResource($brand),
            'Brand details retrieved successfully.'
        );
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return $this->errorResponse('Brand not found.', 404);
        }

        $name = trim($request->input('name'));
        if ($brand->name !== $name) {
            $slug = Str::slug($name);
            $originalSlug = $slug;
            $count = 1;
            while (Brand::where('slug', $slug)->where('id', '!=', $brand->id)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
            $brand->slug = $slug;
        }

        $brand->name = $name;
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $brand->logo_url = '/storage/'.$path;
        } elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $brand->logo_url = '/storage/'.$path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('brands', 'public');
            $brand->logo_url = '/storage/'.$path;
        } elseif ($request->has('logo_url')) {
            $brand->logo_url = $request->input('logo_url');
        }
        if ($request->has('is_featured_on_web')) {
            $brand->is_featured_on_web = $request->boolean('is_featured_on_web');
        }
        if ($request->has('is_active')) {
            $brand->is_active = $request->boolean('is_active');
        }

        $brand->save();

        return $this->successResponse(
            new BrandResource($brand),
            'Brand updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return $this->errorResponse('Brand not found.', 404);
        }

        $brand->is_active = ! $brand->is_active;
        $brand->save();

        $statusText = $brand->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new BrandResource($brand),
            "Brand {$statusText} successfully."
        );
    }
}
