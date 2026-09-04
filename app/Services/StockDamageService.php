<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\ProductVariantSize;
use App\Models\StockDamageItem;
use App\Models\StockDamageTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockDamageService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Process & Confirm Stock Out – Damage Transaction Atomically
     */
    public function processDamageTransaction(array $data, User $user): StockDamageTransaction
    {
        $storeId = (int) ($data['store_id'] ?? 1);
        $reason = strtolower(trim($data['reason'] ?? 'damaged'));
        $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;
        $itemsData = $data['items'] ?? [];

        if (empty($itemsData) || ! is_array($itemsData)) {
            throw new \InvalidArgumentException('At least one item must be provided for stock damage.');
        }

        if ($reason === 'other' && (empty($remarks) || strlen($remarks) < 3)) {
            throw new \InvalidArgumentException('Remarks/explanation is mandatory when reason is Other.');
        }

        return DB::transaction(function () use ($storeId, $reason, $remarks, $itemsData, $user) {
            $damageNumber = 'DMG-'.date('Ymd').'-'.str_pad((string) (StockDamageTransaction::count() + 1), 4, '0', STR_PAD_LEFT);
            $totalQuantity = 0;

            // 1. Lock and validate all stock rows before making changes
            $validatedItems = [];
            foreach ($itemsData as $item) {
                $sizeId = (int) ($item['product_variant_size_id'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 0);

                if ($sizeId <= 0 || $qty <= 0) {
                    throw new \InvalidArgumentException('Invalid size or damage quantity.');
                }

                $size = ProductVariantSize::with(['productVariant.product', 'size'])->find($sizeId);
                if (! $size) {
                    throw new \InvalidArgumentException("Product variant size ID {$sizeId} not found.");
                }

                // Lock stock row to get true available quantity
                $stockRecord = $this->inventoryService->getStockRecord($sizeId, $storeId, 0, 0, true);
                $availableBefore = (int) $stockRecord->stock_quantity;

                if ($qty > $availableBefore) {
                    $sizeName = $size->size?->name ?? 'Selected Size';
                    $productName = $size->productVariant?->product?->name ?? 'Product';
                    throw new \InvalidArgumentException("Damage quantity ({$qty}) cannot exceed available stock ({$availableBefore}) for {$productName} (Size: {$sizeName}).");
                }

                $totalQuantity += $qty;
                $validatedItems[] = [
                    'size_model' => $size,
                    'quantity' => $qty,
                    'stock_before' => $availableBefore,
                ];
            }

            // 2. Create Header Record
            $damageTx = StockDamageTransaction::create([
                'damage_number' => $damageNumber,
                'store_id' => $storeId,
                'reason' => $reason,
                'remarks' => $remarks,
                'total_quantity' => $totalQuantity,
                'created_by' => $user->id,
            ]);

            // 3. Deduct stock and create item records
            foreach ($validatedItems as $vItem) {
                $size = $vItem['size_model'];
                $qty = $vItem['quantity'];
                $stockBefore = $vItem['stock_before'];

                // Deduct stock using InventoryService (which updates inventory_stocks, creates StockMovement & handles Low Stock check)
                $updatedStock = $this->inventoryService->deductStock(
                    productVariantSizeId: $size->id,
                    quantity: $qty,
                    movementType: StockMovementType::STOCK_OUT_DAMAGE,
                    referenceType: StockDamageTransaction::class,
                    referenceId: $damageTx->id,
                    storeId: $storeId,
                    warehouseId: 0,
                    stockLocationId: 0,
                    user: $user,
                    allowNegativeOverride: false
                );

                $stockAfter = (int) $updatedStock->stock_quantity;

                StockDamageItem::create([
                    'stock_damage_transaction_id' => $damageTx->id,
                    'product_variant_size_id' => $size->id,
                    'quantity' => $qty,
                    'available_stock_before' => $stockBefore,
                    'available_stock_after' => $stockAfter,
                ]);
            }

            // 4. Audit Log
            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $storeId,
                'module' => 'INVENTORY',
                'event_type' => 'STOCK_OUT_DAMAGE',
                'auditable_type' => StockDamageTransaction::class,
                'auditable_id' => $damageTx->id,
                'after_state' => json_encode($damageTx->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Recorded Stock Out – Damage #{$damageNumber} for {$totalQuantity} items (Reason: {$reason})",
            ]);

            return $damageTx->load(['store', 'creator', 'items.productVariantSize.productVariant.product', 'items.productVariantSize.size']);
        });
    }
}
