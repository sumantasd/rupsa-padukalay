<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreExchangeRequest;
use App\Http\Resources\ExchangeResource;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ProductVariantSize;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExchangeController extends Controller
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
        ])->where(function ($q) {
            $q->where('refund_mode', 'exchange_offset')
                ->orWhere('reason', 'LIKE', '%Exchange%')
                ->orWhere('price_difference', '!=', 0);
        });

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
        $exchanges = $query->orderBy('id', 'desc')->paginate($perPage);

        // Load replacement stock movements for each exchange
        $exchanges->getCollection()->transform(function ($exc) {
            $exc->setRelation('replacementMovements', StockMovement::where('reference_type', ReturnSale::class)
                ->where('reference_id', $exc->id)
                ->where('movement_type', StockMovementType::SALE_POS)
                ->with(['variantSize.variant.product.brand', 'variantSize.variant.product.category', 'variantSize.variant.color', 'variantSize.size'])
                ->get());

            return $exc;
        });

        return $this->successResponse(
            [
                'items' => ExchangeResource::collection($exchanges->items()),
                'pagination' => [
                    'current_page' => $exchanges->currentPage(),
                    'per_page' => $exchanges->perPage(),
                    'total' => $exchanges->total(),
                    'last_page' => $exchanges->lastPage(),
                ],
            ],
            'Exchanges retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $exchange = ReturnSale::with([
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

        if (! $exchange) {
            return $this->errorResponse('Exchange record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($exchange->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view exchanges for this store.', 403);
            }
        }

        $exchange->setRelation('replacementMovements', StockMovement::where('reference_type', ReturnSale::class)
            ->where('reference_id', $exchange->id)
            ->where('movement_type', StockMovementType::SALE_POS)
            ->with(['variantSize.variant.product.brand', 'variantSize.variant.product.category', 'variantSize.variant.color', 'variantSize.size'])
            ->get());

        return $this->successResponse(
            new ExchangeResource($exchange),
            'Exchange details retrieved successfully.'
        );
    }

    public function store(int $id, StoreExchangeRequest $request): JsonResponse
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
                return $this->errorResponse('Forbidden: You are not authorized to process exchanges for this store.', 403);
            }
        }

        $invStatus = is_object($invoiceCheck->status) ? $invoiceCheck->status->value : $invoiceCheck->status;
        if ($invStatus === InvoiceStatus::CANCELLED->value || $invStatus === 'cancelled') {
            return $this->errorResponse('Cannot process exchange for a cancelled invoice.', 422);
        }

        try {
            $exchangeRecord = DB::transaction(function () use ($id, $request, $user) {
                $invoice = Invoice::with(['items'])->lockForUpdate()->find($id);

                $exchangeNumber = 'EXC-'.date('Ymd').'-'.strtoupper(Str::random(6));
                $returnedTotal = 0.0;
                $replacementTotal = 0.0;

                $preparedReturnedItems = [];
                $preparedReplacementItems = [];

                // 1. Process Returned Items
                foreach ($request->input('returned_items', []) as $itemInput) {
                    $invoiceItem = null;

                    if (! empty($itemInput['invoice_item_id'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => (int) $i->id === (int) $itemInput['invoice_item_id']);
                    } elseif (! empty($itemInput['product_variant_size_id'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => (int) $i->product_variant_size_id === (int) $itemInput['product_variant_size_id']);
                    } elseif (! empty($itemInput['sku'])) {
                        $invoiceItem = $invoice->items->first(fn ($i) => trim($i->sku_snapshot) === trim($itemInput['sku']));
                    }

                    if (! $invoiceItem) {
                        throw new \InvalidArgumentException("Invalid returned item specified for invoice #{$invoice->invoice_number}.");
                    }

                    $returnQty = (int) $itemInput['quantity'];
                    if ($returnQty <= 0) {
                        throw new \InvalidArgumentException('Returned item quantity must be greater than zero.');
                    }

                    // Calculate already returned/exchanged quantity for this invoice item
                    $alreadyReturnedQty = (int) ReturnItem::where('invoice_item_id', $invoiceItem->id)->sum('quantity');
                    $remainingExchangeable = $invoiceItem->quantity - $alreadyReturnedQty;

                    if ($returnQty > $remainingExchangeable) {
                        throw new \RuntimeException(
                            "Exchange return quantity ({$returnQty}) exceeds maximum available quantity ({$remainingExchangeable}) for SKU {$invoiceItem->sku_snapshot}."
                        );
                    }

                    $unitPrice = round((float) $invoiceItem->subtotal / (float) $invoiceItem->quantity, 2);
                    $lineSubtotal = round($unitPrice * $returnQty, 2);
                    $returnedTotal += $lineSubtotal;

                    $condition = strtolower(trim((string) ($itemInput['restock_condition'] ?? 'resellable')));

                    $preparedReturnedItems[] = [
                        'invoiceItem' => $invoiceItem,
                        'returnQty' => $returnQty,
                        'unitPrice' => $unitPrice,
                        'condition' => $condition,
                        'lineSubtotal' => $lineSubtotal,
                    ];
                }

                // 2. Process Replacement Items
                foreach ($request->input('replacement_items', []) as $repInput) {
                    $variantSize = null;

                    if (! empty($repInput['product_variant_size_id'])) {
                        $variantSize = ProductVariantSize::with(['variant.product', 'variant.color', 'size'])->find((int) $repInput['product_variant_size_id']);
                    } elseif (! empty($repInput['sku'])) {
                        $variantSize = ProductVariantSize::with(['variant.product', 'variant.color', 'size'])->where('sku', trim($repInput['sku']))->first();
                    }

                    if (! $variantSize) {
                        throw new \InvalidArgumentException('Invalid replacement product variant/SKU specified.');
                    }

                    $repQty = (int) $repInput['quantity'];
                    if ($repQty <= 0) {
                        throw new \InvalidArgumentException('Replacement item quantity must be greater than zero.');
                    }

                    // Lock and verify physical inventory stock
                    $stock = InventoryStock::where('product_variant_size_id', $variantSize->id)
                        ->where('store_id', $invoice->store_id)
                        ->lockForUpdate()
                        ->first();

                    $availQty = $stock ? (int) $stock->stock_quantity : 0;
                    if ($availQty < $repQty) {
                        throw new \RuntimeException("Insufficient stock for selected replacement SKU.");
                    }

                    $unitSellingPrice = isset($repInput['unit_price']) ? (float) $repInput['unit_price'] : (float) $variantSize->selling_price;
                    $lineSubtotal = round($unitSellingPrice * $repQty, 2);
                    $replacementTotal += $lineSubtotal;

                    $preparedReplacementItems[] = [
                        'variantSize' => $variantSize,
                        'repQty' => $repQty,
                        'unitPrice' => $unitSellingPrice,
                        'lineSubtotal' => $lineSubtotal,
                    ];
                }

                $returnedTotal = round($returnedTotal, 2);
                $replacementTotal = round($replacementTotal, 2);
                $priceDifference = round($replacementTotal - $returnedTotal, 2);

                $paymentMethod = null;
                $amountPaid = 0.00;
                $refundAmount = 0.00;

                // 3. Price Difference & Payment/Refund Validation
                if ($priceDifference > 0) {
                    $paymentMethod = $request->input('payment_method');
                    if (! $paymentMethod || ! in_array($paymentMethod, ['cash', 'upi', 'card', 'store_credit', 'other'])) {
                        throw new \InvalidArgumentException('A valid payment_method (cash, upi, card, store_credit, other) is required when replacement total exceeds returned total.');
                    }

                    $amountPaid = $priceDifference;
                    $refundMode = in_array($paymentMethod, ['cash', 'upi', 'store_credit', 'exchange_offset']) ? $paymentMethod : 'exchange_offset';
                } elseif ($priceDifference < 0) {
                    $refundMode = $request->input('refund_mode', 'cash');
                    if (! in_array($refundMode, ['cash', 'upi', 'store_credit', 'exchange_offset'])) {
                        throw new \InvalidArgumentException('A valid refund_mode (cash, upi, store_credit) is required when returned total exceeds replacement total.');
                    }
                    $refundAmount = abs($priceDifference);
                } else {
                    $refundMode = 'exchange_offset';
                }

                // 4. Create ReturnSale Header Record
                $retSale = ReturnSale::create([
                    'return_number' => $exchangeNumber,
                    'client_return_uuid' => $request->input('client_exchange_uuid') ?? (string) Str::uuid(),
                    'original_invoice_id' => $invoice->id,
                    'store_id' => $invoice->store_id,
                    'customer_id' => $invoice->customer_id,
                    'total_refund_amount' => $refundAmount,
                    'price_difference' => $priceDifference,
                    'payment_method' => $paymentMethod,
                    'amount_paid' => $amountPaid,
                    'refund_mode' => $refundMode,
                    'reason' => $request->input('reason', 'Product Exchange'),
                    'processed_by' => $user->id,
                ]);

                // Issue Store Credit to Customer if refund_mode is store_credit & price difference is negative
                if (strtolower($retSale->refund_mode) === 'store_credit' && $retSale->customer_id && $refundAmount > 0) {
                    $scAccount = \App\Models\StoreCreditAccount::firstOrCreate(
                        ['customer_id' => $retSale->customer_id],
                        ['current_balance' => 0.00, 'total_issued' => 0.00, 'total_used' => 0.00, 'status' => 'active']
                    );
                    $before = (float) $scAccount->current_balance;
                    $after = round($before + $refundAmount, 2);

                    $scAccount->update([
                        'current_balance' => $after,
                        'total_issued' => round((float) $scAccount->total_issued + $refundAmount, 2),
                    ]);

                    \App\Models\StoreCreditTransaction::create([
                        'customer_id' => $retSale->customer_id,
                        'store_id' => $retSale->store_id,
                        'transaction_type' => 'issue_refund',
                        'amount' => $refundAmount,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference_type' => ReturnSale::class,
                        'reference_id' => $retSale->id,
                        'client_trans_uuid' => (string) Str::uuid(),
                        'performed_by' => $user->id,
                        'notes' => "Store credit issued for exchange #{$exchangeNumber}",
                    ]);
                }

                // 5. Create ReturnItem Records & Restock Resellable Returned Inventory
                foreach ($preparedReturnedItems as $prep) {
                    $invoiceItem = $prep['invoiceItem'];
                    $returnQty = $prep['returnQty'];
                    $condition = $prep['condition'];

                    ReturnItem::create([
                        'return_id' => $retSale->id,
                        'invoice_item_id' => $invoiceItem->id,
                        'product_variant_size_id' => $invoiceItem->product_variant_size_id,
                        'quantity' => $returnQty,
                        'refund_unit_price' => $prep['unitPrice'],
                        'restock_condition' => $condition,
                        'subtotal' => $prep['lineSubtotal'],
                    ]);

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
                            "Exchange returned item restored for Exchange #{$exchangeNumber}"
                        );
                    }
                }

                // 6. Deduct Replacement Inventory Stock
                foreach ($preparedReplacementItems as $prep) {
                    $variantSize = $prep['variantSize'];
                    $repQty = $prep['repQty'];

                    $this->inventoryService->deductStock(
                        $variantSize->id,
                        $repQty,
                        StockMovementType::SALE_POS,
                        ReturnSale::class,
                        $retSale->id,
                        $invoice->store_id,
                        0,
                        0,
                        $user,
                        false,
                        "Exchange replacement item issued for Exchange #{$exchangeNumber}"
                    );
                }

                // Update invoice status
                $invoice->status = 'partially_returned';
                $invoice->save();

                $retSale->load([
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

                $retSale->setRelation('replacementMovements', StockMovement::where('reference_type', ReturnSale::class)
                    ->where('reference_id', $retSale->id)
                    ->where('movement_type', StockMovementType::SALE_POS)
                    ->with(['variantSize.variant.product.brand', 'variantSize.variant.product.category', 'variantSize.variant.color', 'variantSize.size'])
                    ->get());

                return $retSale;
            });

            return $this->successResponse(
                new ExchangeResource($exchangeRecord),
                'Exchange processed successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process exchange: '.$e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $exchange = ReturnSale::find($id);

        if (! $exchange) {
            return $this->errorResponse('Exchange record not found.', 404);
        }

        try {
            DB::transaction(function () use ($exchange) {
                ReturnItem::where('return_id', $exchange->id)->delete();
                StockMovement::where('reference_type', ReturnSale::class)
                    ->where('reference_id', $exchange->id)
                    ->delete();
                $exchange->delete();
            });

            return $this->successResponse(null, 'Exchange record deleted successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete exchange record: '.$e->getMessage(), 500);
        }
    }
}
