<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceive;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiveController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceive::with([
            'supplier',
            'purchaseOrder',
            'store',
            'createdBy',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', (int) $request->input('supplier_id'));
        }

        if ($request->has('purchase_order_id')) {
            $query->where('purchase_order_id', (int) $request->input('purchase_order_id'));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'LIKE', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('company_name', 'LIKE', "%{$search}%");
                  })->orWhereHas('purchaseOrder', function ($pq) use ($search) {
                      $pq->where('po_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        $formatted = collect($paginated->items())->map(function ($grn) {
            return [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'purchase_order_id' => $grn->purchase_order_id,
                'po_number' => $grn->purchaseOrder?->po_number ?? 'N/A',
                'supplier_id' => $grn->supplier_id,
                'supplier_name' => $grn->supplier?->name ?? 'N/A',
                'supplier_company' => $grn->supplier?->company_name,
                'supplier_phone' => $grn->supplier?->phone,
                'store_name' => $grn->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
                'received_date' => $grn->received_date?->format('Y-m-d'),
                'total_items_received' => (int) $grn->total_items_received,
                'total_cost' => (float) $grn->total_cost,
                'supplier_invoice_number' => $grn->supplier_invoice_number,
                'notes' => $grn->notes,
                'created_by_name' => $grn->createdBy?->name ?? 'System',
                'created_at' => $grn->created_at?->toIso8601String(),
                'items' => collect($grn->items)->map(function ($item) {
                    $pvs = $item->variantSize;
                    $variant = $pvs?->variant;
                    $product = $variant?->product;
                    $sizeNum = $pvs?->size?->size_number ?? 'N/A';
                    $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

                    return [
                        'id' => $item->id,
                        'product_variant_size_id' => $item->product_variant_size_id,
                        'article_number' => $product?->article_number ?? 'N/A',
                        'product_name' => $product?->name ?? 'Footwear Item',
                        'brand_name' => $product?->brand?->name ?? 'Generic Brand',
                        'color_name' => $variant?->color?->name ?? 'Std',
                        'size_display' => $sizeDisplay,
                        'sku' => $pvs?->sku ?? 'N/A',
                        'quantity_ordered' => (int) $item->quantity_ordered,
                        'quantity_received' => (int) $item->quantity_received,
                        'cost_price' => (float) $item->cost_price,
                        'total_cost' => (float) $item->total_cost,
                    ];
                }),
            ];
        });

        return $this->successResponse([
            'items' => $formatted,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Goods Receive Notes (GRN) retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $grn = GoodsReceive::with([
            'supplier',
            'purchaseOrder',
            'store',
            'createdBy',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $grn) {
            return $this->errorResponse('Goods Receive Note not found.', 404);
        }

        return $this->successResponse([
            'id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'purchase_order_id' => $grn->purchase_order_id,
            'po_number' => $grn->purchaseOrder?->po_number ?? 'N/A',
            'supplier_id' => $grn->supplier_id,
            'supplier_name' => $grn->supplier?->name ?? 'N/A',
            'supplier_company' => $grn->supplier?->company_name,
            'supplier_phone' => $grn->supplier?->phone,
            'supplier_gstin' => $grn->supplier?->gstin,
            'store_name' => $grn->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'received_date' => $grn->received_date?->format('Y-m-d'),
            'total_items_received' => (int) $grn->total_items_received,
            'total_cost' => (float) $grn->total_cost,
            'supplier_invoice_number' => $grn->supplier_invoice_number,
            'notes' => $grn->notes,
            'created_by_name' => $grn->createdBy?->name ?? 'System',
            'created_at' => $grn->created_at?->toIso8601String(),
            'items' => collect($grn->items)->map(function ($item) {
                $pvs = $item->variantSize;
                $variant = $pvs?->variant;
                $product = $variant?->product;
                $sizeNum = $pvs?->size?->size_number ?? 'N/A';
                $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

                return [
                    'id' => $item->id,
                    'product_variant_size_id' => $item->product_variant_size_id,
                    'article_number' => $product?->article_number ?? 'N/A',
                    'product_name' => $product?->name ?? 'Footwear Item',
                    'brand_name' => $product?->brand?->name ?? 'Generic Brand',
                    'color_name' => $variant?->color?->name ?? 'Std',
                    'size_display' => $sizeDisplay,
                    'sku' => $pvs?->sku ?? 'N/A',
                    'quantity_ordered' => (int) $item->quantity_ordered,
                    'quantity_received' => (int) $item->quantity_received,
                    'cost_price' => (float) $item->cost_price,
                    'total_cost' => (float) $item->total_cost,
                ];
            }),
        ], 'Goods Receive Note details retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received_now' => ['required', 'integer', 'min:1'],
        ]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        try {
            $grn = $this->purchaseService->receiveGoods(
                $po,
                $validated['items'],
                $request->user(),
                $validated['notes'] ?? null,
                $validated['supplier_invoice_number'] ?? null
            );

            return $this->successResponse(
                $grn,
                'Goods received successfully! Inventory stock and GRN records updated.',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
