<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnExchangeService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Process sales returns and exchanges in an atomic database transaction.
     */
    public function processReturn(array $data, User $processor): ReturnSale
    {
        $clientUuid = $data['client_return_uuid'] ?? (string) Str::uuid();

        // Idempotency check for offline sync or duplicate posting
        $existingReturn = ReturnSale::where('client_return_uuid', $clientUuid)->first();
        if ($existingReturn) {
            return $existingReturn;
        }

        return DB::transaction(function () use ($data, $processor, $clientUuid) {
            $invoice = Invoice::findOrFail($data['original_invoice_id']);
            $storeId = (int) ($data['store_id'] ?? $invoice->store_id);

            $returnNumber = 'RET-'.date('Ymd').'-'.str_pad((string) (ReturnSale::count() + 1), 5, '0', STR_PAD_LEFT);

            $returnSale = ReturnSale::create([
                'return_number' => $returnNumber,
                'client_return_uuid' => $clientUuid,
                'original_invoice_id' => $invoice->id,
                'store_id' => $storeId,
                'customer_id' => $invoice->customer_id,
                'total_refund_amount' => 0.00,
                'refund_mode' => $data['refund_mode'] ?? 'cash',
                'reason' => $data['reason'] ?? null,
                'processed_by' => $processor->id,
            ]);

            $totalRefund = 0.00;

            foreach ($data['items'] as $item) {
                $invoiceItemId = (int) $item['invoice_item_id'];
                $qtyReturn = (int) $item['quantity'];
                $refundUnitPrice = (float) $item['refund_unit_price'];
                $condition = $item['restock_condition'] ?? 'resellable';

                $invItem = InvoiceItem::where('invoice_id', $invoice->id)->findOrFail($invoiceItemId);
                $subtotal = round($qtyReturn * $refundUnitPrice, 2);

                ReturnItem::create([
                    'return_id' => $returnSale->id,
                    'invoice_item_id' => $invItem->id,
                    'product_variant_size_id' => $invItem->product_variant_size_id,
                    'quantity' => $qtyReturn,
                    'refund_unit_price' => $refundUnitPrice,
                    'restock_condition' => $condition,
                    'subtotal' => $subtotal,
                ]);

                $totalRefund += $subtotal;

                // Restock inventory ONLY if returned item is in resellable condition
                if ($condition === 'resellable') {
                    $this->inventoryService->addStock(
                        $invItem->product_variant_size_id,
                        $qtyReturn,
                        StockMovementType::SALE_RETURN,
                        ReturnSale::class,
                        $returnSale->id,
                        $storeId,
                        0,
                        0,
                        $processor,
                        "Returned item restored (Return {$returnNumber})"
                    );
                }
            }

            // If an exchange item is being taken in the same transaction
            if (! empty($data['exchange_items'])) {
                foreach ($data['exchange_items'] as $exItem) {
                    $exPvsId = (int) $exItem['product_variant_size_id'];
                    $exQty = (int) $exItem['quantity'];

                    // Deduct stock for the new exchanged size
                    $this->inventoryService->deductStock(
                        $exPvsId,
                        $exQty,
                        StockMovementType::SALE_POS,
                        ReturnSale::class,
                        $returnSale->id,
                        $storeId,
                        0,
                        0,
                        $processor,
                        false,
                        "Exchange item issued (Return {$returnNumber})"
                    );
                }
            }

            $returnSale->update([
                'total_refund_amount' => round($totalRefund, 2),
            ]);

            $invoice->update(['status' => 'partially_returned']);

            return $returnSale->load(['items', 'originalInvoice']);
        });
    }
}
