<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesReturnController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ReturnSale::with([
            'originalInvoice',
            'store',
            'customer',
            'processor',
            'items.invoiceItem',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('original_invoice_id')) {
            $query->where('original_invoice_id', (int) $request->input('original_invoice_id'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        if ($request->has('processed_by')) {
            $query->where('processed_by', (int) $request->input('processed_by'));
        }

        if ($request->has('refund_mode')) {
            $query->where('refund_mode', trim((string) $request->input('refund_mode')));
        }

        if ($request->has('sku')) {
            $sku = trim((string) $request->input('sku'));
            $query->whereHas('items.variantSize', function ($q) use ($sku) {
                $q->where('sku', 'LIKE', "%{$sku}%");
            });
        }

        if ($request->has('article_number')) {
            $article = trim((string) $request->input('article_number'));
            $query->whereHas('items.variantSize.variant.product', function ($q) use ($article) {
                $q->where('article_number', 'LIKE', "%{$article}%");
            });
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'LIKE', "%{$search}%")
                    ->orWhere('client_return_uuid', 'LIKE', "%{$search}%")
                    ->orWhereHas('originalInvoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $returns = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => SalesReturnResource::collection($returns->items()),
                'pagination' => [
                    'current_page' => $returns->currentPage(),
                    'per_page' => $returns->perPage(),
                    'total' => $returns->total(),
                    'last_page' => $returns->lastPage(),
                ],
            ],
            'Sales returns retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $returnSale = ReturnSale::with([
            'originalInvoice',
            'store',
            'customer',
            'processor',
            'items.invoiceItem',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $returnSale) {
            return $this->errorResponse('Sales return record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($returnSale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view sales returns for this store.', 403);
            }
        }

        return $this->successResponse(
            new SalesReturnResource($returnSale),
            'Sales return details retrieved successfully.'
        );
    }

    public function store(int $id, StoreSalesReturnRequest $request): JsonResponse
    {
        $user = $request->user();

        $invoiceCheck = Invoice::find($id);
        if (! $invoiceCheck) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($invoiceCheck->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to process returns for this store.', 403);
            }
        }

        $invStatus = is_object($invoiceCheck->status) ? $invoiceCheck->status->value : $invoiceCheck->status;
        if ($invStatus === InvoiceStatus::CANCELLED->value || $invStatus === 'cancelled') {
            return $this->errorResponse('Cannot process sales return for a cancelled invoice.', 422);
        }

        try {
            $returnRecord = DB::transaction(function () use ($id, $request, $user) {
                $invoice = Invoice::with(['items'])->lockForUpdate()->find($id);

                $returnNumber = 'RET-'.date('Ymd').'-'.strtoupper(Str::random(6));
                $totalRefund = 0.0;
                $preparedReturnItems = [];

                foreach ($request->input('items', []) as $itemInput) {
                    $invoiceItem = null;

                    if (! empty($itemInput['invoice_item_id'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => (int) $i->id === (int) $itemInput['invoice_item_id']);
                    } elseif (! empty($itemInput['product_variant_size_id'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => (int) $i->product_variant_size_id === (int) $itemInput['product_variant_size_id']);
                    } elseif (! empty($itemInput['sku'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => trim($i->sku_snapshot) === trim($itemInput['sku']));
                    }

                    if (! $invoiceItem) {
                        throw new \InvalidArgumentException("Invalid return item specified for invoice #{$invoice->invoice_number}.");
                    }

                    $returnQty = (int) $itemInput['quantity'];
                    if ($returnQty <= 0) {
                        throw new \InvalidArgumentException('Return quantity must be greater than zero.');
                    }

                    // Calculate already returned quantity for this invoice item
                    $alreadyReturnedQty = (int) ReturnItem::where('invoice_item_id', $invoiceItem->id)->sum('quantity');
                    $remainingReturnable = $invoiceItem->quantity - $alreadyReturnedQty;

                    if ($returnQty > $remainingReturnable) {
                        throw new \RuntimeException(
                            "Return quantity ({$returnQty}) exceeds maximum returnable quantity ({$remainingReturnable}) for SKU {$invoiceItem->sku_snapshot}."
                        );
                    }

                    // Compute unit refund price & subtotal
                    $unitRefundPrice = round((float) $invoiceItem->subtotal / (float) $invoiceItem->quantity, 2);
                    $lineSubtotal = round($unitRefundPrice * $returnQty, 2);
                    $totalRefund += $lineSubtotal;

                    $condition = strtolower(trim((string) ($itemInput['restock_condition'] ?? 'resellable')));

                    $preparedReturnItems[] = [
                        'invoiceItem' => $invoiceItem,
                        'returnQty' => $returnQty,
                        'unitRefundPrice' => $unitRefundPrice,
                        'condition' => $condition,
                        'lineSubtotal' => $lineSubtotal,
                    ];
                }

                $totalRefund = round($totalRefund, 2);

                // Create ReturnSale Header Record
                $retSale = ReturnSale::create([
                    'return_number' => $returnNumber,
                    'client_return_uuid' => $request->input('client_return_uuid') ?? (string) Str::uuid(),
                    'original_invoice_id' => $invoice->id,
                    'store_id' => $invoice->store_id,
                    'customer_id' => $invoice->customer_id,
                    'total_refund_amount' => $totalRefund,
                    'refund_mode' => strtolower(trim((string) ($request->input('refund_mode', 'cash')))),
                    'reason' => $request->input('reason'),
                    'processed_by' => $user->id,
                ]);

                // Issue Store Credit to Customer if refund_mode is store_credit
                if (strtolower($retSale->refund_mode) === 'store_credit' && $retSale->customer_id && $totalRefund > 0) {
                    $scAccount = \App\Models\StoreCreditAccount::firstOrCreate(
                        ['customer_id' => $retSale->customer_id],
                        ['current_balance' => 0.00, 'total_issued' => 0.00, 'total_used' => 0.00, 'status' => 'active']
                    );
                    $before = (float) $scAccount->current_balance;
                    $after = round($before + $totalRefund, 2);

                    $scAccount->update([
                        'current_balance' => $after,
                        'total_issued' => round((float) $scAccount->total_issued + $totalRefund, 2),
                    ]);

                    \App\Models\StoreCreditTransaction::create([
                        'customer_id' => $retSale->customer_id,
                        'store_id' => $retSale->store_id,
                        'transaction_type' => 'issue_refund',
                        'amount' => $totalRefund,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference_type' => ReturnSale::class,
                        'reference_id' => $retSale->id,
                        'client_trans_uuid' => (string) Str::uuid(),
                        'performed_by' => $user->id,
                        'notes' => "Store credit issued for sales return #{$returnNumber}",
                    ]);
                }

                // Create ReturnItem Records & Restock Inventory if resellable
                foreach ($preparedReturnItems as $prep) {
                    $invoiceItem = $prep['invoiceItem'];
                    $returnQty = $prep['returnQty'];
                    $condition = $prep['condition'];

                    ReturnItem::create([
                        'return_id' => $retSale->id,
                        'invoice_item_id' => $invoiceItem->id,
                        'product_variant_size_id' => $invoiceItem->product_variant_size_id,
                        'quantity' => $returnQty,
                        'refund_unit_price' => $prep['unitRefundPrice'],
                        'restock_condition' => $condition,
                        'subtotal' => $prep['lineSubtotal'],
                    ]);

                    // Inventory Increase ONLY if resellable
                    if ($condition === 'resellable') {
                        $this->inventoryService->addStock(
                            $invoiceItem->product_variant_size_id,
                            $returnQty,
                            StockMovementType::SALE_RETURN,
                            ReturnSale::class,
                            $retSale->id,
                            $invoice->store_id,
                            0,
                            0,
                            $user,
                            "Sales return #{$returnNumber} for invoice #{$invoice->invoice_number}"
                        );
                    }
                }

                // Calculate total return progress across entire invoice
                $totalOriginalSold = (int) InvoiceItem::where('invoice_id', $invoice->id)->sum('quantity');
                $totalReturnedAll = (int) ReturnItem::whereHas('returnSale', function ($q) use ($invoice) {
                    $q->where('original_invoice_id', $invoice->id);
                })->sum('quantity');

                if ($totalReturnedAll >= $totalOriginalSold) {
                    $invoice->status = 'returned';
                } else {
                    $invoice->status = 'partially_returned';
                }
                $invoice->save();

                return $retSale->load([
                    'originalInvoice',
                    'store',
                    'customer',
                    'processor',
                    'items.invoiceItem',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new SalesReturnResource($returnRecord),
                'Sales return processed successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process sales return: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // RBAC check: user must have permission to delete sales returns
        if (! $user->hasPermissionTo('sales_returns.delete') && ! $user->hasPermissionTo('sales.delete') && ! $user->hasPermissionTo('products.delete')) {
            return $this->errorResponse('Forbidden: You do not have permission to delete sales return transactions.', 403);
        }

        $returnSale = ReturnSale::with(['items', 'originalInvoice', 'customer'])->find($id);

        if (! $returnSale) {
            return $this->errorResponse('Sales return record not found.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($returnSale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete sales returns for this store.', 403);
            }
        }

        // Pre-deletion Blocker 1: Check store credit dependency if refund_mode is store_credit
        if (strtolower($returnSale->refund_mode) === 'store_credit' && $returnSale->customer_id && $returnSale->total_refund_amount > 0) {
            $scAccount = \App\Models\StoreCreditAccount::where('customer_id', $returnSale->customer_id)->first();
            if ($scAccount) {
                $currentBal = (float) $scAccount->current_balance;
                $refundAmt = (float) $returnSale->total_refund_amount;
                if ($currentBal < $refundAmt) {
                    return $this->errorResponse(
                        "Cannot delete sales return: The store credit issued (₹{$refundAmt}) has already been redeemed by the customer and current balance (₹{$currentBal}) is insufficient.",
                        422
                    );
                }
            }
        }

        // Pre-deletion Blocker 2: Day Closing check
        $returnDate = $returnSale->created_at ? $returnSale->created_at->format('Y-m-d') : now()->format('Y-m-d');
        $isClosed = \App\Models\DayClosing::where('store_id', $returnSale->store_id)
            ->whereDate('closing_date', $returnDate)
            ->whereIn('status', ['closed', 'completed'])
            ->exists();

        if ($isClosed && ! $user->hasPermissionTo('day_closing.reopen')) {
            return $this->errorResponse("Cannot delete a sales return from a closed day (Date: {$returnDate}). Reopen the day closing first.", 422);
        }

        try {
            DB::transaction(function () use ($returnSale, $user) {
                // 1. Reverse Store Credit issued if applicable
                if (strtolower($returnSale->refund_mode) === 'store_credit' && $returnSale->customer_id && $returnSale->total_refund_amount > 0) {
                    $scAccount = \App\Models\StoreCreditAccount::where('customer_id', $returnSale->customer_id)->first();
                    if ($scAccount) {
                        $before = (float) $scAccount->current_balance;
                        $refundAmt = (float) $returnSale->total_refund_amount;
                        $after = max(0.00, round($before - $refundAmt, 2));

                        $scAccount->update([
                            'current_balance' => $after,
                            'total_issued' => max(0.00, round((float) $scAccount->total_issued - $refundAmt, 2)),
                        ]);

                        \App\Models\StoreCreditTransaction::create([
                            'customer_id' => $returnSale->customer_id,
                            'store_id' => $returnSale->store_id,
                            'transaction_type' => 'adjustment_deduct',
                            'amount' => $refundAmt,
                            'balance_before' => $before,
                            'balance_after' => $after,
                            'reference_type' => ReturnSale::class,
                            'reference_id' => $returnSale->id,
                            'client_trans_uuid' => (string) Str::uuid(),
                            'performed_by' => $user->id,
                            'notes' => "Reversed store credit from deleted sales return #{$returnSale->return_number}",
                        ]);
                    }
                }

                // 2. Reverse Inventory Stock for returned items (if restock_condition was resellable)
                foreach ($returnSale->items as $item) {
                    if (strtolower(trim((string) $item->restock_condition)) === 'resellable' && $item->product_variant_size_id && $item->quantity > 0) {
                        $this->inventoryService->deductStock(
                            (int) $item->product_variant_size_id,
                            (int) $item->quantity,
                            StockMovementType::STOCK_CORRECTION,
                            ReturnSale::class,
                            $returnSale->id,
                            (int) $returnSale->store_id,
                            0,
                            0,
                            $user,
                            true,
                            "Reversal: Deletion of sales return #{$returnSale->return_number}"
                        );
                    }
                }

                // 3. Delete Stock Movements created by this return
                \App\Models\StockMovement::where('reference_type', ReturnSale::class)
                    ->where('reference_id', $returnSale->id)
                    ->delete();

                // 4. Recalculate original invoice return status
                $originalInvoiceId = $returnSale->original_invoice_id;

                // Soft Delete ReturnSale (ReturnItems are preserved for Recycle Bin recovery)
                $returnSale->delete();

                if ($originalInvoiceId) {
                    $invoice = Invoice::find($originalInvoiceId);
                    if ($invoice) {
                        $totalOriginalSold = (int) InvoiceItem::where('invoice_id', $invoice->id)->sum('quantity');
                        $totalReturnedRemaining = (int) ReturnItem::whereHas('returnSale', function ($q) use ($invoice) {
                            $q->where('original_invoice_id', $invoice->id);
                        })->sum('quantity');

                        if ($totalReturnedRemaining <= 0) {
                            $invoice->status = InvoiceStatus::COMPLETED->value;
                        } elseif ($totalReturnedRemaining >= $totalOriginalSold) {
                            $invoice->status = InvoiceStatus::RETURNED->value;
                        } else {
                            $invoice->status = InvoiceStatus::PARTIALLY_RETURNED->value;
                        }
                        $invoice->save();
                    }
                }
            });

            return $this->successResponse(null, "Sales return #{$returnSale->return_number} deleted successfully and all transaction effects reversed.");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete sales return: '.$e->getMessage(), 500);
        }
    }
}
