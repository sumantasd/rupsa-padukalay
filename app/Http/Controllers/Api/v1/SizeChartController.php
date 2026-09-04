<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use App\Models\SizeChart;
use App\Models\SizeChartColumn;
use App\Models\SizeChartRow;
use App\Models\SizeChartValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SizeChartController extends Controller
{
    /**
     * Display a listing of size charts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.view') && ! $user->hasPermissionTo('products.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to view size charts.',
            ], 403);
        }

        $query = SizeChart::with(['category', 'columns', 'rows.values']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $charts = $query->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($chart) {
                return $this->formatChartPayload($chart);
            });

        return response()->json([
            'success' => true,
            'data' => $charts,
            'message' => 'Size charts retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created size chart.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.create') && ! $user->hasPermissionTo('size_charts.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to create size charts.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'gender' => 'nullable|string|max:20',
            'age_group' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'columns' => 'required|array|min:1',
            'columns.*.name' => 'required|string|max:100',
            'columns.*.data_type' => 'nullable|string|in:text,number',
            'rows' => 'nullable|array',
        ]);

        try {
            $chart = DB::transaction(function () use ($validated, $user) {
                if (! empty($validated['is_default'])) {
                    if (! empty($validated['category_id'])) {
                        SizeChart::where('category_id', $validated['category_id'])->update(['is_default' => false]);
                    } else {
                        SizeChart::whereNull('category_id')->update(['is_default' => false]);
                    }
                }

                $chart = SizeChart::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'age_group' => $validated['age_group'] ?? null,
                    'unit' => $validated['unit'] ?? 'CM',
                    'is_default' => ! empty($validated['is_default']),
                    'is_active' => $validated['is_active'] ?? true,
                    'created_by' => $user->id,
                ]);

                // Create Columns
                $colMap = [];
                foreach ($validated['columns'] as $idx => $colData) {
                    $colName = trim($colData['name']);
                    $key = strtolower(preg_replace('/[^a-z0-9_]/', '_', $colName));
                    $column = SizeChartColumn::create([
                        'size_chart_id' => $chart->id,
                        'name' => $colName,
                        'key' => $key,
                        'data_type' => $colData['data_type'] ?? 'text',
                        'sort_order' => $idx + 1,
                    ]);
                    $colMap[$idx] = $column->id;
                    if (isset($colData['key'])) {
                        $colMap[$colData['key']] = $column->id;
                    }
                }

                // Create Rows & Values
                if (! empty($validated['rows'])) {
                    foreach ($validated['rows'] as $rIdx => $rowData) {
                        $sizeVal = $rowData['size_value'] ?? ($rowData['values'][0] ?? null);
                        
                        // Try to link size_id if matching size master exists
                        $sizeId = null;
                        if ($sizeVal !== null && trim((string) $sizeVal) !== '') {
                            $sizeValStr = trim((string) $sizeVal);
                            $sizeMaster = Size::firstOrCreate(
                                ['size_number' => $sizeValStr, 'size_system' => 'UK/IND'],
                                ['sort_order' => $rIdx + 1, 'is_active' => true]
                            );
                            $sizeId = $sizeMaster->id;
                        }

                        $row = SizeChartRow::create([
                            'size_chart_id' => $chart->id,
                            'size_id' => $sizeId,
                            'size_value' => $sizeVal,
                            'sort_order' => $rIdx + 1,
                        ]);

                        $vals = $rowData['values'] ?? [];
                        foreach ($vals as $cKey => $val) {
                            $colId = $colMap[$cKey] ?? null;
                            if ($colId) {
                                SizeChartValue::create([
                                    'size_chart_row_id' => $row->id,
                                    'size_chart_column_id' => $colId,
                                    'value' => (string) $val,
                                ]);
                            }
                        }
                    }
                }

                return $chart->fresh(['category', 'columns', 'rows.values']);
            });

            return response()->json([
                'success' => true,
                'data' => $this->formatChartPayload($chart),
                'message' => "Size chart '{$chart->name}' created successfully.",
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create size chart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified size chart.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.view') && ! $user->hasPermissionTo('products.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to view size charts.',
            ], 403);
        }

        $chart = SizeChart::with(['category', 'columns', 'rows.values'])->find($id);
        if (! $chart) {
            return response()->json([
                'success' => false,
                'message' => 'Size chart not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatChartPayload($chart),
            'message' => 'Size chart details retrieved successfully.',
        ]);
    }

    /**
     * Update the specified size chart.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.edit') && ! $user->hasPermissionTo('size_charts.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to edit size charts.',
            ], 403);
        }

        $chart = SizeChart::find($id);
        if (! $chart) {
            return response()->json([
                'success' => false,
                'message' => 'Size chart not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'gender' => 'nullable|string|max:20',
            'age_group' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'columns' => 'required|array|min:1',
            'columns.*.name' => 'required|string|max:100',
            'columns.*.data_type' => 'nullable|string|in:text,number',
            'rows' => 'nullable|array',
        ]);

        try {
            $updatedChart = DB::transaction(function () use ($chart, $validated) {
                if (! empty($validated['is_default'])) {
                    if (! empty($validated['category_id'])) {
                        SizeChart::where('category_id', $validated['category_id'])
                            ->where('id', '!=', $chart->id)
                            ->update(['is_default' => false]);
                    } else {
                        SizeChart::whereNull('category_id')
                            ->where('id', '!=', $chart->id)
                            ->update(['is_default' => false]);
                    }
                }

                $chart->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'age_group' => $validated['age_group'] ?? null,
                    'unit' => $validated['unit'] ?? 'CM',
                    'is_default' => ! empty($validated['is_default']),
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Delete old columns, rows & values for simple atomic refresh
                SizeChartColumn::where('size_chart_id', $chart->id)->delete();
                SizeChartRow::where('size_chart_id', $chart->id)->delete();

                // Re-create Columns
                $colMap = [];
                foreach ($validated['columns'] as $idx => $colData) {
                    $colName = trim($colData['name']);
                    $key = strtolower(preg_replace('/[^a-z0-9_]/', '_', $colName));
                    $column = SizeChartColumn::create([
                        'size_chart_id' => $chart->id,
                        'name' => $colName,
                        'key' => $key,
                        'data_type' => $colData['data_type'] ?? 'text',
                        'sort_order' => $idx + 1,
                    ]);
                    $colMap[$idx] = $column->id;
                    if (isset($colData['key'])) {
                        $colMap[$colData['key']] = $column->id;
                    }
                }

                // Re-create Rows & Values
                if (! empty($validated['rows'])) {
                    foreach ($validated['rows'] as $rIdx => $rowData) {
                        $sizeVal = $rowData['size_value'] ?? ($rowData['values'][0] ?? null);

                        $sizeId = null;
                        if ($sizeVal !== null && trim((string) $sizeVal) !== '') {
                            $sizeValStr = trim((string) $sizeVal);
                            $sizeMaster = Size::firstOrCreate(
                                ['size_number' => $sizeValStr, 'size_system' => 'UK/IND'],
                                ['sort_order' => $rIdx + 1, 'is_active' => true]
                            );
                            $sizeId = $sizeMaster->id;
                        }

                        $row = SizeChartRow::create([
                            'size_chart_id' => $chart->id,
                            'size_id' => $sizeId,
                            'size_value' => $sizeVal,
                            'sort_order' => $rIdx + 1,
                        ]);

                        $vals = $rowData['values'] ?? [];
                        foreach ($vals as $cKey => $val) {
                            $colId = $colMap[$cKey] ?? null;
                            if ($colId) {
                                SizeChartValue::create([
                                    'size_chart_row_id' => $row->id,
                                    'size_chart_column_id' => $colId,
                                    'value' => (string) $val,
                                ]);
                            }
                        }
                    }
                }

                return $chart->fresh(['category', 'columns', 'rows.values']);
            });

            return response()->json([
                'success' => true,
                'data' => $this->formatChartPayload($updatedChart),
                'message' => "Size chart '{$updatedChart->name}' updated successfully.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update size chart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the specified size chart (with safety check).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to delete size charts.',
            ], 403);
        }

        $chart = SizeChart::find($id);
        if (! $chart) {
            return response()->json([
                'success' => false,
                'message' => 'Size chart not found.',
            ], 404);
        }

        // Unsafe Deletion Check: attached products
        $attachedProductsCount = Product::where('size_chart_id', $chart->id)->count();
        if ($attachedProductsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete size chart '{$chart->name}'. It is currently assigned to {$attachedProductsCount} active product(s). Reassign those products first.",
            ], 422);
        }

        $chartName = $chart->name;
        $chart->delete();

        return response()->json([
            'success' => true,
            'message' => "Size chart '{$chartName}' deleted successfully.",
        ]);
    }

    /**
     * Duplicate/Clone an existing size chart.
     */
    public function duplicate(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to duplicate size charts.',
            ], 403);
        }

        $original = SizeChart::with(['columns', 'rows.values'])->find($id);
        if (! $original) {
            return response()->json([
                'success' => false,
                'message' => 'Original size chart not found.',
            ], 404);
        }

        try {
            $cloned = DB::transaction(function () use ($original, $user) {
                $newChart = SizeChart::create([
                    'name' => $original->name . ' (Copy)',
                    'description' => $original->description,
                    'category_id' => $original->category_id,
                    'gender' => $original->gender,
                    'age_group' => $original->age_group,
                    'unit' => $original->unit,
                    'is_default' => false,
                    'is_active' => true,
                    'created_by' => $user->id,
                ]);

                $colMap = [];
                foreach ($original->columns as $col) {
                    $newCol = SizeChartColumn::create([
                        'size_chart_id' => $newChart->id,
                        'name' => $col->name,
                        'key' => $col->key,
                        'data_type' => $col->data_type,
                        'sort_order' => $col->sort_order,
                    ]);
                    $colMap[$col->id] = $newCol->id;
                }

                foreach ($original->rows as $row) {
                    $newRow = SizeChartRow::create([
                        'size_chart_id' => $newChart->id,
                        'size_id' => $row->size_id,
                        'size_value' => $row->size_value,
                        'sort_order' => $row->sort_order,
                    ]);

                    foreach ($row->values as $val) {
                        if (isset($colMap[$val->size_chart_column_id])) {
                            SizeChartValue::create([
                                'size_chart_row_id' => $newRow->id,
                                'size_chart_column_id' => $colMap[$val->size_chart_column_id],
                                'value' => $val->value,
                            ]);
                        }
                    }
                }

                return $newChart->fresh(['category', 'columns', 'rows.values']);
            });

            return response()->json([
                'success' => true,
                'data' => $this->formatChartPayload($cloned),
                'message' => "Size chart duplicated successfully as '{$cloned->name}'.",
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate size chart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active status of a size chart.
     */
    public function toggleStatus(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to toggle status.',
            ], 403);
        }

        $chart = SizeChart::find($id);
        if (! $chart) {
            return response()->json([
                'success' => false,
                'message' => 'Size chart not found.',
            ], 404);
        }

        $chart->is_active = ! $chart->is_active;
        $chart->save();

        $statusText = $chart->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'data' => $this->formatChartPayload($chart),
            'message' => "Size chart '{$chart->name}' has been {$statusText}.",
        ]);
    }

    /**
     * Set a size chart as default.
     */
    public function setDefault(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to set default size chart.',
            ], 403);
        }

        $chart = SizeChart::find($id);
        if (! $chart) {
            return response()->json([
                'success' => false,
                'message' => 'Size chart not found.',
            ], 404);
        }

        DB::transaction(function () use ($chart) {
            if ($chart->category_id) {
                SizeChart::where('category_id', $chart->category_id)->update(['is_default' => false]);
            } else {
                SizeChart::whereNull('category_id')->update(['is_default' => false]);
            }

            $chart->is_default = true;
            $chart->save();
        });

        return response()->json([
            'success' => true,
            'data' => $this->formatChartPayload($chart->fresh(['category', 'columns', 'rows.values'])),
            'message' => "Size chart '{$chart->name}' set as default.",
        ]);
    }

    /**
     * Get suggested size chart for a given category.
     */
    public function getCategoryDefault(int $categoryId): JsonResponse
    {
        $category = Category::with('defaultSizeChart.columns', 'defaultSizeChart.rows.values')->find($categoryId);
        
        $chart = null;
        if ($category && $category->defaultSizeChart) {
            $chart = $category->defaultSizeChart;
        } else {
            // Fallback 1: Default chart for category
            $chart = SizeChart::with(['columns', 'rows.values'])
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            // Fallback 2: Any chart for category
            if (! $chart) {
                $chart = SizeChart::with(['columns', 'rows.values'])
                    ->where('category_id', $categoryId)
                    ->where('is_active', true)
                    ->first();
            }

            // Fallback 3: Global default chart
            if (! $chart) {
                $chart = SizeChart::with(['columns', 'rows.values'])
                    ->whereNull('category_id')
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->first();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $chart ? $this->formatChartPayload($chart) : null,
            'message' => $chart ? 'Suggested size chart retrieved.' : 'No size chart assigned.',
        ]);
    }

    /**
     * Assign default size chart to a category.
     */
    public function assignCategoryDefault(int $categoryId, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('size_charts.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to assign size chart to category.',
            ], 403);
        }

        $category = Category::find($categoryId);
        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        $validated = $request->validate([
            'size_chart_id' => 'nullable|exists:size_charts,id',
        ]);

        $category->default_size_chart_id = $validated['size_chart_id'] ?? null;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Default size chart assigned to category successfully.',
        ]);
    }

    /**
     * Format payload for API response.
     */
    private function formatChartPayload(SizeChart $chart): array
    {
        $columns = $chart->columns->map(function ($col) {
            return [
                'id' => $col->id,
                'name' => $col->name,
                'key' => $col->key,
                'data_type' => $col->data_type,
                'sort_order' => $col->sort_order,
            ];
        })->values();

        $rows = $chart->rows->map(function ($row) {
            $vals = [];
            foreach ($row->values as $v) {
                $vals[$v->size_chart_column_id] = $v->value;
            }
            return [
                'id' => $row->id,
                'size_id' => $row->size_id,
                'size_value' => $row->size_value,
                'sort_order' => $row->sort_order,
                'values' => $vals,
            ];
        })->values();

        return [
            'id' => $chart->id,
            'name' => $chart->name,
            'description' => $chart->description,
            'category_id' => $chart->category_id,
            'category_name' => $chart->category ? $chart->category->name : null,
            'gender' => $chart->gender,
            'age_group' => $chart->age_group,
            'unit' => $chart->unit,
            'is_default' => (bool) $chart->is_default,
            'is_active' => (bool) $chart->is_active,
            'column_count' => $columns->count(),
            'row_count' => $rows->count(),
            'attached_products_count' => Product::where('size_chart_id', $chart->id)->count(),
            'columns' => $columns,
            'rows' => $rows,
            'created_at' => $chart->created_at ? $chart->created_at->toDateTimeString() : null,
            'updated_at' => $chart->updated_at ? $chart->updated_at->toDateTimeString() : null,
        ];
    }
}
