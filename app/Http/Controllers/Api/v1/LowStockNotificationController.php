<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\LowStockNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LowStockNotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = LowStockNotification::with([
            'product.brand',
            'product.category',
            'variant.color',
            'variantSize.size',
            'store',
        ]);

        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($request->has('notification_type')) {
            $query->where('notification_type', trim((string) $request->input('notification_type')));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('article_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('is_read', 'asc')->orderBy('id', 'desc')->paginate($perPage);

        $formattedItems = collect($paginated->items())->map(function ($n) {
            $product = $n->product;
            $variant = $n->variant;
            $variantSize = $n->variantSize;

            $sizeNum = $variantSize?->size?->size_number ?? 'N/A';
            $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND')
                ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum)
                : $sizeNum;

            return [
                'id' => $n->id,
                'store_id' => $n->store_id,
                'product_id' => $n->product_id,
                'product_variant_size_id' => $n->product_variant_size_id,
                'article_number' => $product?->article_number ?? 'N/A',
                'product_name' => $product?->name ?? 'Footwear Product',
                'brand_name' => $product?->brand?->name ?? 'Generic Brand',
                'category_name' => $product?->category?->name ?? 'Footwear',
                'color_name' => $variant?->color?->name ?? 'Std',
                'size_number' => $sizeNum,
                'size_display' => $sizeDisplay,
                'sku' => $variantSize?->sku ?? 'N/A',
                'notification_type' => $n->notification_type,
                'current_quantity' => (int) $n->current_quantity,
                'threshold_quantity' => (int) $n->threshold_quantity,
                'reorder_quantity' => (int) $n->reorder_quantity,
                'title' => $n->title,
                'message' => $n->message,
                'is_read' => (bool) $n->is_read,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ];
        });

        $unreadCount = LowStockNotification::where('is_read', false)->count();

        return $this->successResponse([
            'unread_count' => $unreadCount,
            'items' => $formattedItems,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Low stock notifications retrieved successfully.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = LowStockNotification::where('is_read', false)->count();

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Unread notification count retrieved successfully.');
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = LowStockNotification::find($id);

        if (! $notification) {
            return $this->errorResponse('Notification record not found.', 404);
        }

        if (! $notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        $unreadCount = LowStockNotification::where('is_read', false)->count();

        return $this->successResponse([
            'id' => $notification->id,
            'is_read' => true,
            'unread_count' => $unreadCount,
        ], 'Notification marked as read successfully.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        LowStockNotification::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->successResponse([
            'unread_count' => 0,
        ], 'All notifications marked as read successfully.');
    }
}
