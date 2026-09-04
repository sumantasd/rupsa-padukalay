<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceive;
use App\Models\GoodsReceiveItem;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Validate mandatory supplier association.
     */
    protected function validateSupplier(?int $supplierId): Supplier
    {
        if (! $supplierId || $supplierId <= 0) {
            throw new InvalidArgumentException('Supplier is required for every purchase transaction.');
        }

        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            throw new InvalidArgumentException('Supplier is required for every purchase transaction.');
        }

        return $supplier;
    }

    /**
     * Create a new Purchase Order. Physical stock is NOT increased at PO creation.
     */
    public function createPurchaseOrder(array $data, User $creator): PurchaseOrder
    {
        $supplier = $this->validateSupplier($data['supplier_id'] ?? null);

        return DB::transaction(function () use ($data, $supplier, $creator) {
            $poNumber = 'PO-'.date('Ymd').'-'.str_pad((string) (PurchaseOrder::count() + 1), 5, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_id' => $supplier->id,
                'store_id' => $data['store_id'] ?? 1,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'status' => PurchaseStatus::ORDERED->value,
                'subtotal' => 0.00,
                'tax_amount' => (float) ($data['tax_amount'] ?? 0.00),
                'discount_amount' => (float) ($data['discount_amount'] ?? 0.00),
                'grand_total' => 0.00,
                'paid_amount' => (float) ($data['paid_amount'] ?? 0.00),
                'due_amount' => 0.00,
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            $subtotal = 0.00;

            foreach ($data['items'] as $item) {
                $pvsId = (int) $item['product_variant_size_id'];
                $qtyOrdered = (int) ($item['quantity_ordered'] ?? $item['quantity'] ?? 0);
                if ($qtyOrdered <= 0) {
                    throw new InvalidArgumentException('Order quantity must be greater than zero.');
                }

                $costPrice = (float) ($item['cost_price'] ?? 0.00);
                $mrp = (float) ($item['mrp'] ?? $costPrice * 1.5);
                $sellingPrice = (float) ($item['selling_price'] ?? $costPrice * 1.3);
                $itemTotal = round($qtyOrdered * $costPrice, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_variant_size_id' => $pvsId,
                    'quantity_ordered' => $qtyOrdered,
                    'quantity_received' => 0,
                    'cost_price' => $costPrice,
                    'mrp' => $mrp,
                    'selling_price' => $sellingPrice,
                    'total_cost' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            $grandTotal = max(0.00, round($subtotal + $po->tax_amount - $po->discount_amount, 2));
            $dueAmount = max(0.00, round($grandTotal - $po->paid_amount, 2));

            $po->update([
                'subtotal' => round($subtotal, 2),
                'grand_total' => $grandTotal,
                'due_amount' => $dueAmount,
            ]);

            return $po->load(['items.variantSize.variant.product', 'supplier', 'store']);
        });
    }

    /**
     * Goods Receive Note (GRN) confirmation. Physical stock increases ONLY when goods are received.
     */
    public function receiveGoods(PurchaseOrder $po, array $receivedItems, User $receiver, ?string $notes = null, ?string $supplierInvoiceNumber = null): GoodsReceive
    {
        $this->validateSupplier($po->supplier_id);

        $statusStr = is_object($po->status) && property_exists($po->status, 'value') ? $po->status->value : (string) $po->status;
        if (in_array($statusStr, [PurchaseStatus::RECEIVED->value, 'fully_received', 'received', PurchaseStatus::CANCELLED->value, 'cancelled'])) {
            throw new InvalidArgumentException("Purchase Order {$po->po_number} is already fully received or cancelled.");
        }

        return DB::transaction(function () use ($po, $receivedItems, $receiver, $notes, $supplierInvoiceNumber) {
            $grnNumber = 'GRN-'.date('Ymd').'-'.str_pad((string) (GoodsReceive::count() + 1), 5, '0', STR_PAD_LEFT);

            $grn = GoodsReceive::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'store_id' => $po->store_id ?? 1,
                'received_date' => now()->toDateString(),
                'total_items_received' => 0,
                'total_cost' => 0.00,
                'supplier_invoice_number' => $supplierInvoiceNumber ?? $po->supplier_invoice_number,
                'notes' => $notes,
                'created_by' => $receiver->id,
            ]);

            $totalQtyReceived = 0;
            $totalGrnCost = 0.00;
            $allFullyReceived = true;

            foreach ($receivedItems as $rec) {
                $itemId = (int) ($rec['purchase_order_item_id'] ?? 0);
                $qtyReceivedNow = (int) ($rec['quantity_received_now'] ?? $rec['quantity_received'] ?? 0);

                if ($qtyReceivedNow <= 0) {
                    continue;
                }

                $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)->findOrFail($itemId);
                $remainingQty = $poItem->quantity_ordered - $poItem->quantity_received;

                if ($qtyReceivedNow > $remainingQty) {
                    throw new InvalidArgumentException("Cannot receive {$qtyReceivedNow} units. Maximum remaining to receive for item is {$remainingQty}.");
                }

                $poItem->increment('quantity_received', $qtyReceivedNow);

                $itemTotalCost = round($qtyReceivedNow * (float) $poItem->cost_price, 2);

                GoodsReceiveItem::create([
                    'goods_receive_id' => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_size_id' => $poItem->product_variant_size_id,
                    'quantity_ordered' => $poItem->quantity_ordered,
                    'quantity_received' => $qtyReceivedNow,
                    'cost_price' => $poItem->cost_price,
                    'total_cost' => $itemTotalCost,
                ]);

                // Update size cost_price if provided without altering mrp or selling_price
                $pvs = \App\Models\ProductVariantSize::find($poItem->product_variant_size_id);
                if ($pvs && (float) $poItem->cost_price > 0) {
                    $pvs->cost_price = (float) $poItem->cost_price;
                    $pvs->save();
                }

                $totalQtyReceived += $qtyReceivedNow;
                $totalGrnCost += $itemTotalCost;

                // Inventory increases atomically via authoritative InventoryService!
                $storeId = (int) ($po->store_id > 0 ? $po->store_id : 1);
                $warehouseId = (int) ($po->warehouse_id ?? 0);

                $this->inventoryService->addStock(
                    $poItem->product_variant_size_id,
                    $qtyReceivedNow,
                    StockMovementType::PURCHASE,
                    GoodsReceive::class,
                    $grn->id,
                    $storeId,
                    $warehouseId,
                    0,
                    $receiver,
                    "Goods received GRN {$grnNumber} for PO {$po->po_number}"
                );
            }

            $grn->update([
                'total_items_received' => $totalQtyReceived,
                'total_cost' => round($totalGrnCost, 2),
            ]);

            // Update PO status based on remaining quantities across all items
            $po->refresh();
            foreach ($po->items as $checkItem) {
                if ($checkItem->quantity_received < $checkItem->quantity_ordered) {
                    $allFullyReceived = false;
                    break;
                }
            }

            $poStatus = $allFullyReceived ? PurchaseStatus::RECEIVED->value : PurchaseStatus::PARTIAL->value;
            $po->update([
                'status' => $poStatus,
                'received_date' => now()->toDateString(),
            ]);

            // Automatically create linked Purchase Bill for received goods
            $this->createPurchaseBillFromGrn($grn, $receiver);

            return $grn->load(['items.variantSize.variant.product', 'supplier', 'purchaseOrder']);
        });
    }

    /**
     * Create a Purchase Bill from a confirmed GRN.
     */
    public function createPurchaseBillFromGrn(GoodsReceive $grn, User $creator): PurchaseBill
    {
        $this->validateSupplier($grn->supplier_id);

        $billNumber = 'BILL-'.date('Ymd').'-'.str_pad((string) (PurchaseBill::count() + 1), 5, '0', STR_PAD_LEFT);

        $subtotal = 0.00;
        $itemsData = [];

        foreach ($grn->items as $grnItem) {
            $itemTotal = (float) $grnItem->total_cost;
            $subtotal += $itemTotal;

            $itemsData[] = [
                'product_variant_size_id' => $grnItem->product_variant_size_id,
                'quantity' => $grnItem->quantity_received,
                'cost_price' => $grnItem->cost_price,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_cost' => $itemTotal,
            ];
        }

        $grandTotal = round($subtotal, 2);

        $bill = PurchaseBill::create([
            'bill_number' => $billNumber,
            'supplier_invoice_number' => $grn->supplier_invoice_number,
            'supplier_id' => $grn->supplier_id,
            'purchase_order_id' => $grn->purchase_order_id,
            'goods_receive_id' => $grn->id,
            'store_id' => $grn->store_id ?? 1,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'payment_terms' => 'Net 30 Days',
            'subtotal' => $grandTotal,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'grand_total' => $grandTotal,
            'paid_amount' => 0.00,
            'due_amount' => $grandTotal,
            'payment_status' => 'unpaid',
            'notes' => "Purchase bill automatically generated from GRN #{$grn->grn_number}",
            'created_by' => $creator->id,
        ]);

        foreach ($itemsData as $bItem) {
            PurchaseBillItem::create([
                'purchase_bill_id' => $bill->id,
                'product_variant_size_id' => $bItem['product_variant_size_id'],
                'quantity' => $bItem['quantity'],
                'cost_price' => $bItem['cost_price'],
                'discount_amount' => $bItem['discount_amount'],
                'tax_amount' => $bItem['tax_amount'],
                'total_cost' => $bItem['total_cost'],
            ]);
        }

        return $bill;
    }

    /**
     * Create a standalone Purchase Bill.
     */
    public function createPurchaseBill(array $data, User $creator): PurchaseBill
    {
        $supplier = $this->validateSupplier($data['supplier_id'] ?? null);

        return DB::transaction(function () use ($data, $supplier, $creator) {
            $billNumber = 'BILL-'.date('Ymd').'-'.str_pad((string) (PurchaseBill::count() + 1), 5, '0', STR_PAD_LEFT);

            $paidNow = (float) ($data['paid_amount'] ?? 0.00);

            $bill = PurchaseBill::create([
                'bill_number' => $billNumber,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receive_id' => $data['goods_receive_id'] ?? null,
                'store_id' => $data['store_id'] ?? 1,
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_terms' => $data['payment_terms'] ?? 'Net 30 Days',
                'subtotal' => 0.00,
                'discount_amount' => (float) ($data['discount_amount'] ?? 0.00),
                'tax_amount' => (float) ($data['tax_amount'] ?? 0.00),
                'grand_total' => 0.00,
                'paid_amount' => 0.00,
                'due_amount' => 0.00,
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            $subtotal = 0.00;

            foreach ($data['items'] as $item) {
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Bill item quantity must be greater than zero.');
                }
                $costPrice = (float) $item['cost_price'];
                $discount = (float) ($item['discount_amount'] ?? 0.00);
                $tax = (float) ($item['tax_amount'] ?? 0.00);
                $itemTotal = round(($qty * $costPrice) - $discount + $tax, 2);

                PurchaseBillItem::create([
                    'purchase_bill_id' => $bill->id,
                    'product_variant_size_id' => (int) $item['product_variant_size_id'],
                    'quantity' => $qty,
                    'cost_price' => $costPrice,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'total_cost' => $itemTotal,
                ]);

                $subtotal += ($qty * $costPrice);
            }

            $grandTotal = max(0.00, round($subtotal + $bill->tax_amount - $bill->discount_amount, 2));

            $bill->update([
                'subtotal' => round($subtotal, 2),
                'grand_total' => $grandTotal,
                'due_amount' => $grandTotal,
            ]);

            // Handle optional initial payment if provided
            if ($paidNow > 0) {
                $this->recordSupplierPayment([
                    'supplier_id' => $supplier->id,
                    'purchase_bill_id' => $bill->id,
                    'amount' => $paidNow,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                    'notes' => 'Initial payment at purchase bill creation',
                ], $creator);
            }

            return $bill->fresh(['items.variantSize.variant.product', 'supplier', 'payments']);
        });
    }

    /**
     * Record a Supplier Payment and update Purchase Bill & Supplier Due balances.
     */
    public function recordSupplierPayment(array $data, User $recorder): SupplierPayment
    {
        $supplier = $this->validateSupplier($data['supplier_id'] ?? null);

        $amount = (float) ($data['amount'] ?? 0.00);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($data, $supplier, $amount, $recorder) {
            $paymentNumber = 'PAY-'.date('Ymd').'-'.str_pad((string) (SupplierPayment::count() + 1), 5, '0', STR_PAD_LEFT);

            $billId = isset($data['purchase_bill_id']) ? (int) $data['purchase_bill_id'] : null;
            $poId = isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;

            $payment = SupplierPayment::create([
                'payment_number' => $paymentNumber,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $poId,
                'purchase_bill_id' => $billId,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $recorder->id,
            ]);

            // If allocated to a specific Purchase Bill, update bill status
            if ($billId) {
                $bill = PurchaseBill::where('supplier_id', $supplier->id)->find($billId);
                if ($bill) {
                    $newPaid = round($bill->paid_amount + $amount, 2);
                    $newDue = max(0.00, round($bill->grand_total - $newPaid, 2));

                    $status = 'unpaid';
                    if ($newDue <= 0) {
                        $status = 'paid';
                    } elseif ($newPaid > 0) {
                        $status = 'partially_paid';
                    }

                    $bill->update([
                        'paid_amount' => $newPaid,
                        'due_amount' => $newDue,
                        'payment_status' => $status,
                    ]);
                }
            }

            return $payment->load(['supplier', 'purchaseBill']);
        });
    }

    /**
     * Create a Purchase Return. Stock decreases ONLY via authoritative InventoryService.
     */
    public function createPurchaseReturn(array $data, User $processor): PurchaseReturn
    {
        $supplier = $this->validateSupplier($data['supplier_id'] ?? null);

        return DB::transaction(function () use ($data, $supplier, $processor) {
            $returnNumber = 'PR-'.date('Ymd').'-'.str_pad((string) (PurchaseReturn::count() + 1), 5, '0', STR_PAD_LEFT);

            $poId = isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;
            $billId = isset($data['purchase_bill_id']) ? (int) $data['purchase_bill_id'] : null;

            $returnRecord = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'purchase_order_id' => $poId,
                'purchase_bill_id' => $billId,
                'supplier_id' => $supplier->id,
                'store_id' => $data['store_id'] ?? 1,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'total_return_amount' => 0.00,
                'refund_mode' => $data['refund_mode'] ?? 'supplier_credit',
                'reason' => $data['reason'] ?? 'Damaged/Defective',
                'processed_by' => $processor->id,
            ]);

            $totalReturnAmount = 0.00;

            foreach ($data['items'] as $item) {
                $pvsId = (int) $item['product_variant_size_id'];
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Return quantity must be greater than zero.');
                }

                $costPrice = isset($item['cost_price']) ? (float) $item['cost_price'] : (float) (\App\Models\ProductVariantSize::find($pvsId)?->cost_price ?? 0.00);
                $subtotal = round($qty * $costPrice, 2);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $returnRecord->id,
                    'product_variant_size_id' => $pvsId,
                    'quantity' => $qty,
                    'cost_price' => $costPrice,
                    'subtotal' => $subtotal,
                ]);

                $totalReturnAmount += $subtotal;

                // Inventory DECREASES atomically via authoritative InventoryService!
                $storeId = (int) ($returnRecord->store_id > 0 ? $returnRecord->store_id : 1);
                $warehouseId = (int) ($returnRecord->warehouse_id ?? 0);

                $this->inventoryService->deductStock(
                    $pvsId,
                    $qty,
                    StockMovementType::PURCHASE_RETURN,
                    PurchaseReturn::class,
                    $returnRecord->id,
                    $storeId,
                    $warehouseId,
                    0,
                    $processor,
                    false,
                    "Purchase Return #{$returnNumber} for Supplier {$supplier->name}"
                );
            }

            $returnRecord->update([
                'total_return_amount' => round($totalReturnAmount, 2),
            ]);

            // If return linked to Purchase Bill, adjust bill due amount
            if ($billId) {
                $bill = PurchaseBill::find($billId);
                if ($bill) {
                    $newDue = max(0.00, round($bill->grand_total - $bill->paid_amount - $totalReturnAmount, 2));
                    $status = $newDue <= 0 ? 'paid' : ($bill->paid_amount > 0 ? 'partially_paid' : 'unpaid');

                    $bill->update([
                        'due_amount' => $newDue,
                        'payment_status' => $status,
                    ]);
                }
            } elseif (($data['refund_mode'] ?? 'supplier_credit') === 'supplier_credit') {
                // Adjust open unpaid bills for the supplier
                $unpaidBills = PurchaseBill::where('supplier_id', $supplier->id)
                    ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                    ->orderBy('id', 'asc')
                    ->get();

                $remainingCreditToApply = round($totalReturnAmount, 2);
                foreach ($unpaidBills as $uBill) {
                    if ($remainingCreditToApply <= 0) {
                        break;
                    }
                    $due = (float) $uBill->due_amount;
                    $adjustment = min($due, $remainingCreditToApply);
                    $newDue = max(0.00, round($due - $adjustment, 2));
                    $status = $newDue <= 0 ? 'paid' : ($uBill->paid_amount > 0 ? 'partially_paid' : 'unpaid');

                    $uBill->update([
                        'due_amount' => $newDue,
                        'payment_status' => $status,
                    ]);

                    $remainingCreditToApply = round($remainingCreditToApply - $adjustment, 2);
                }
            }

            return $returnRecord->load(['items.variantSize.variant.product', 'supplier']);
        });
    }
}
