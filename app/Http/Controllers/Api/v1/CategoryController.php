<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCategoryRequest;
use App\Http\Requests\Master\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Category::with(['parent', 'children']);

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->boolean('root_only', false)) {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('name')->get();

        return $this->successResponse(
            CategoryResource::collection($categories),
            'Categories retrieved successfully.'
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $name = trim($request->input('name'));
        $slug = Str::slug($name);

        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $imageUrl = '/storage/'.$path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('categories', 'public');
            $imageUrl = '/storage/'.$path;
        }

        $category = Category::create([
            'parent_id' => $request->input('parent_id'),
            'name' => $name,
            'slug' => $slug,
            'image_url' => $imageUrl,
            'is_visible_on_web' => $request->boolean('is_visible_on_web', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new CategoryResource($category->load(['parent', 'children'])),
            'Category created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $category = Category::with(['parent', 'children'])->find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        return $this->successResponse(
            new CategoryResource($category),
            'Category details retrieved successfully.'
        );
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        $name = trim($request->input('name'));
        if ($category->name !== $name) {
            $slug = Str::slug($name);
            $originalSlug = $slug;
            $count = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
            $category->slug = $slug;
        }

        $category->name = $name;
        if ($request->has('parent_id')) {
            $category->parent_id = $request->input('parent_id');
        }
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $category->image_url = '/storage/'.$path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('categories', 'public');
            $category->image_url = '/storage/'.$path;
        } elseif ($request->has('image_url')) {
            $category->image_url = $request->input('image_url');
        }
        if ($request->has('is_visible_on_web')) {
            $category->is_visible_on_web = $request->boolean('is_visible_on_web');
        }
        if ($request->has('is_active')) {
            $category->is_active = $request->boolean('is_active');
        }

        $category->save();

        return $this->successResponse(
            new CategoryResource($category->load(['parent', 'children'])),
            'Category updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        $category->is_active = ! $category->is_active;
        $category->save();

        $statusText = $category->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new CategoryResource($category),
            "Category {$statusText} successfully."
        );
    }
}
