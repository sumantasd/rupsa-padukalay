<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GoodsReceiveItem;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseBillItem;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use App\Models\StockAdjustmentItem;
use App\Models\StockDamageItem;
use App\Models\StockMovement;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecycleBinController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', 'products')));
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 15);

        switch ($type) {
            case 'products':
                $query = Product::onlyTrashed()
                    ->with(['brand', 'category', 'variants.sizes.inventoryStocks']);

                if (! empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('article_number', 'LIKE', "%{$search}%");
                    });
                }

                $paginated = $query->orderBy('deleted_at', 'desc')->paginate($perPage);

                $items = collect($paginated->items())->map(function ($product) {
                    $variantIds = $product->variants->pluck('id');
                    $sizes = ProductVariantSize::whereIn('product_variant_id', $variantIds)->with('inventoryStocks')->get();
                    $totalStock = $sizes->sum(function ($s) {
                        return $s->inventoryStocks->sum('stock_quantity');
                    });

                    return [
                        'id' => $product->id,
                        'type' => 'products',
                        'name' => $product->name,
                        'code_or_sku' => $product->article_number,
                        'category_name' => $product->category?->name ?? 'Uncategorized',
                        'brand_name' => $product->brand?->name ?? 'N/A',
                        'deleted_at' => $product->deleted_at?->toIso8601String(),
                        'current_stock' => $totalStock,
                        'details' => "Article #{$product->article_number} | " . (is_object($product->gender) && property_exists($product->gender, 'value') ? $product->gender->value : (string) ($product->gender ?? 'unisex')) . " | Stock: {$totalStock}",
                    ];
                });
                break;

            case 'customers':
                $query = Customer::onlyTrashed();
                if (! empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                    });
                }
                $paginated = $query->orderBy('deleted_at', 'desc')->paginate($perPage);
                $items = collect($paginated->items())->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'type' => 'customers',
                        'name' => $c->name,
                        'code_or_sku' => $c->mobile_number,
                        'category_name' => 'Customer',
                        'brand_name' => $c->city ?? 'N/A',
                        'deleted_at' => $c->deleted_at?->toIso8601String(),
                        'current_stock' => null,
                        'details' => "Mobile: {$c->mobile_number} | Email: " . ($c->email ?? 'N/A'),
                    ];
                });
                break;

            case 'suppliers':
                $query = Supplier::onlyTrashed();
                if (! empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('company_name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
                    });
                }
                $paginated = $query->orderBy('deleted_at', 'desc')->paginate($perPage);
                $items = collect($paginated->items())->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'type' => 'suppliers',
                        'name' => $s->name . ($s->company_name ? " ({$s->company_name})" : ''),
                        'code_or_sku' => $s->code,
                        'category_name' => 'Supplier',
                        'brand_name' => $s->city ?? 'N/A',
                        'deleted_at' => $s->deleted_at?->toIso8601String(),
                        'current_stock' => null,
                        'details' => "Code: {$s->code} | Phone: {$s->phone}",
                    ];
                });
                break;

            case 'categories':
                $query = Category::onlyTrashed();
                if (! empty($search)) {
                    $query->where('name', 'LIKE', "%{$search}%");
                }
                $paginated = $query->orderBy('deleted_at', 'desc')->paginate($perPage);
                $items = collect($paginated->items())->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'type' => 'categories',
                        'name' => $cat->name,
                        'code_or_sku' => $cat->slug,
                        'category_name' => 'Category',
                        'brand_name' => 'N/A',
                        'deleted_at' => $cat->deleted_at?->toIso8601String(),
                        'current_stock' => null,
                        'details' => "Slug: {$cat->slug}",
                    ];
                });
                break;

            case 'brands':
                $query = Brand::onlyTrashed();
                if (! empty($search)) {
                    $query->where('name', 'LIKE', "%{$search}%");
                }
                $paginated = $query->orderBy('deleted_at', 'desc')->paginate($perPage);
                $items = collect($paginated->items())->map(function ($b) {
                    return [
                        'id' => $b->id,
                        'type' => 'brands',
                        'name' => $b->name,
                        'code_or_sku' => $b->slug,
                        'category_name' => 'Brand',
                        'brand_name' => 'N/A',
                        'deleted_at' => $b->deleted_at?->toIso8601String(),
                        'current_stock' => null,
                        'details' => "Slug: {$b->slug}",
                    ];
                });
                break;

            default:
                return $this->errorResponse('Unsupported recycle bin entity type.', 422);
        }

        return $this->successResponse([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 'Recycle bin items retrieved successfully.');
    }

    public function restore(string $type, int $id): JsonResponse
    {
        $type = strtolower(trim($type));

        switch ($type) {
            case 'products':
                $item = Product::onlyTrashed()->find($id);
                if (! $item) return $this->errorResponse('Trashed product not found.', 404);
                $item->is_active = true;
                $item->save();
                $item->restore();
                $name = $item->name;
                break;

            case 'customers':
                $item = Customer::onlyTrashed()->find($id);
                if (! $item) return $this->errorResponse('Trashed customer not found.', 404);
                $item->restore();
                $name = $item->name;
                break;

            case 'suppliers':
                $item = Supplier::onlyTrashed()->find($id);
                if (! $item) return $this->errorResponse('Trashed supplier not found.', 404);
                $item->is_active = true;
                $item->save();
                $item->restore();
                $name = $item->name;
                break;

            case 'categories':
                $item = Category::onlyTrashed()->find($id);
                if (! $item) return $this->errorResponse('Trashed category not found.', 404);
                $item->is_active = true;
                $item->save();
                $item->restore();
                $name = $item->name;
                break;

            case 'brands':
                $item = Brand::onlyTrashed()->find($id);
                if (! $item) return $this->errorResponse('Trashed brand not found.', 404);
                $item->is_active = true;
                $item->save();
                $item->restore();
                $name = $item->name;
                break;

            default:
                return $this->errorResponse('Invalid entity type for restoration.', 422);
        }

        try {
            $this->auditService->logEvent([
                'module' => 'recycle_bin',
                'event_type' => 'restore',
                'auditable_type' => get_class($item),
                'auditable_id' => $item->id,
                'reason_notes' => "Restored {$type} '{$name}' from Recycle Bin.",
            ]);
        } catch (\Throwable $e) {}

        return $this->successResponse(null, "{$name} restored successfully.");
    }

    public function forceDelete(string $type, int $id): JsonResponse
    {
        $type = strtolower(trim($type));

        switch ($type) {
            case 'products':
                $product = Product::onlyTrashed()->find($id);
                if (! $product) return $this->errorResponse('Trashed product not found.', 404);

                $variantIds = $product->variants()->pluck('id');
                $sizeIds = ProductVariantSize::whereIn('product_variant_id', $variantIds)->pluck('id')->toArray();

                // Strict transactional history check
                $hasInvoices = ! empty($sizeIds) && InvoiceItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasReturns = ! empty($sizeIds) && ReturnItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasPOs = ! empty($sizeIds) && PurchaseOrderItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasGRNs = ! empty($sizeIds) && GoodsReceiveItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasBills = ! empty($sizeIds) && PurchaseBillItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasPurchaseReturns = ! empty($sizeIds) && PurchaseReturnItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasStockMovements = ! empty($sizeIds) && StockMovement::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasStockAdjustments = ! empty($sizeIds) && StockAdjustmentItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasStockTransfers = ! empty($sizeIds) && StockTransferItem::whereIn('product_variant_size_id', $sizeIds)->exists();
                $hasStockDamages = ! empty($sizeIds) && StockDamageItem::whereIn('product_variant_size_id', $sizeIds)->exists();

                if ($hasInvoices || $hasReturns || $hasPOs || $hasGRNs || $hasBills || $hasPurchaseReturns || $hasStockMovements || $hasStockAdjustments || $hasStockTransfers || $hasStockDamages) {
                    return $this->errorResponse(
                        "Cannot permanently delete product '{$product->name}' because historical transactional records (invoices, POs, inventory movements, returns, etc.) depend on it. Keep it archived in the system.",
                        422
                    );
                }

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

                $name = $product->name;
                break;

            case 'customers':
                $customer = Customer::onlyTrashed()->find($id);
                if (! $customer) return $this->errorResponse('Trashed customer not found.', 404);

                if ($customer->invoices()->exists() || $customer->payments()->exists()) {
                    return $this->errorResponse("Cannot permanently delete customer '{$customer->name}' because billing or payment records exist for this customer.", 422);
                }

                $name = $customer->name;
                $customer->forceDelete();
                break;

            case 'suppliers':
                $supplier = Supplier::onlyTrashed()->find($id);
                if (! $supplier) return $this->errorResponse('Trashed supplier not found.', 404);

                if ($supplier->purchaseOrders()->exists() || $supplier->purchaseBills()->exists() || $supplier->payments()->exists() || $supplier->purchaseReturns()->exists()) {
                    return $this->errorResponse("Cannot permanently delete supplier '{$supplier->name}' because purchase orders, bills or payment history exist.", 422);
                }

                $name = $supplier->name;
                $supplier->forceDelete();
                break;

            case 'categories':
                $category = Category::onlyTrashed()->find($id);
                if (! $category) return $this->errorResponse('Trashed category not found.', 404);

                if (Product::withTrashed()->where('category_id', $category->id)->exists()) {
                    return $this->errorResponse("Cannot permanently delete category '{$category->name}' because products (active or archived) are linked to it.", 422);
                }

                $name = $category->name;
                $category->forceDelete();
                break;

            case 'brands':
                $brand = Brand::onlyTrashed()->find($id);
                if (! $brand) return $this->errorResponse('Trashed brand not found.', 404);

                if (Product::withTrashed()->where('brand_id', $brand->id)->exists()) {
                    return $this->errorResponse("Cannot permanently delete brand '{$brand->name}' because products (active or archived) are linked to it.", 422);
                }

                $name = $brand->name;
                $brand->forceDelete();
                break;

            default:
                return $this->errorResponse('Invalid entity type for permanent deletion.', 422);
        }

        try {
            $this->auditService->logEvent([
                'module' => 'recycle_bin',
                'event_type' => 'permanent_delete',
                'reason_notes' => "Permanently deleted {$type} '{$name}' (ID: {$id}) from Recycle Bin.",
            ]);
        } catch (\Throwable $e) {}

        return $this->successResponse(null, "{$name} permanently deleted.");
    }
}
