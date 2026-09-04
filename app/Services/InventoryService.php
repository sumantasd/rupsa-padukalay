<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        protected LowStockService $lowStockService
    ) {}

    /**
     * Get or create current inventory stock record scoped by SKU and location.
     */
    public function getStockRecord(
        int $productVariantSizeId,
        int $storeId = 0,
        int $warehouseId = 0,
        int $stockLocationId = 0,
        bool $lock = false
    ): InventoryStock {
        $query = InventoryStock::where('product_variant_size_id', $productVariantSizeId)
            ->where('store_id', $storeId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock_location_id', $stockLocationId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $stock = $query->first();

        if (! $stock) {
            $stock = InventoryStock::create([
                'product_variant_size_id' => $productVariantSizeId,
                'store_id' => $storeId,
                'warehouse_id' => $warehouseId,
                'stock_location_id' => $stockLocationId,
                'stock_quantity' => 0,
                'reorder_level' => 3,
            ]);

            if ($lock) {
                // Re-lock newly created row
                $stock = InventoryStock::where('id', $stock->id)->lockForUpdate()->first();
            }
        }

        return $stock;
    }

    /**
     * Deduct stock atomically with pessimistic row-locking & strict zero-stock enforcement.
     */
    public function deductStock(
        int $productVariantSizeId,
        int $quantity,
        StockMovementType $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        int $storeId = 0,
        int $warehouseId = 0,
        int $stockLocationId = 0,
        ?User $user = null,
        bool $allowNegativeOverride = false,
        ?string $overrideNotes = null
    ): InventoryStock {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Deduction quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $productVariantSizeId,
            $quantity,
            $movementType,
            $referenceType,
            $referenceId,
            $storeId,
            $warehouseId,
            $stockLocationId,
            $user,
            $allowNegativeOverride,
            $overrideNotes
        ) {
            $stock = $this->getStockRecord($productVariantSizeId, $storeId, $warehouseId, $stockLocationId, true);
            $stockBefore = $stock->stock_quantity;
            $stockAfter = $stockBefore - $quantity;

            // Zero-stock rejection policy check
            if ($stockAfter < 0 && ! $allowNegativeOverride) {
                throw new \RuntimeException(
                    "Insufficient stock for SKU ID {$productVariantSizeId}. Current stock: {$stockBefore}, Requested: {$quantity}."
                );
            }

            // If negative override requested, verify user authorization
            if ($stockAfter < 0 && $allowNegativeOverride) {
                if (! $user || ! $user->hasPermissionTo('pos.stock.override_negative')) {
                    throw new \UnauthorizedException(
                        'User is not authorized to override negative stock.'
                    );
                }
            }

            // Update stock
            $stock->stock_quantity = $stockAfter;
            $stock->save();

            // Log immutable movement ledger
            StockMovement::create([
                'product_variant_size_id' => $productVariantSizeId,
                'store_id' => $storeId > 0 ? $storeId : null,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'stock_location_id' => $stockLocationId > 0 ? $stockLocationId : null,
                'movement_type' => $movementType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity_change' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => $overrideNotes ?? 'Stock deducted',
                'created_by' => $user?->id ?? 1,
            ]);

            // Trigger centralized low stock & notification lifecycle update
            try {
                $this->lowStockService->checkAndUpdateAlerts($productVariantSizeId, $storeId);
            } catch (\Throwable $e) {
                // Log exception gracefully
            }

            return $stock;
        });
    }

    /**
     * Add stock atomically and log movement ledger.
     */
    public function addStock(
        int $productVariantSizeId,
        int $quantity,
        StockMovementType $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        int $storeId = 0,
        int $warehouseId = 0,
        int $stockLocationId = 0,
        ?User $user = null,
        ?string $notes = null
    ): InventoryStock {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Addition quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $productVariantSizeId,
            $quantity,
            $movementType,
            $referenceType,
            $referenceId,
            $storeId,
            $warehouseId,
            $stockLocationId,
            $user,
            $notes
        ) {
            $stock = $this->getStockRecord($productVariantSizeId, $storeId, $warehouseId, $stockLocationId, true);
            $stockBefore = $stock->stock_quantity;
            $stockAfter = $stockBefore + $quantity;

            $stock->stock_quantity = $stockAfter;
            $stock->save();

            StockMovement::create([
                'product_variant_size_id' => $productVariantSizeId,
                'store_id' => $storeId > 0 ? $storeId : null,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'stock_location_id' => $stockLocationId > 0 ? $stockLocationId : null,
                'movement_type' => $movementType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity_change' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => $notes ?? 'Stock added',
                'created_by' => $user?->id ?? 1,
            ]);

            // Trigger centralized low stock & notification lifecycle update
            try {
                $this->lowStockService->checkAndUpdateAlerts($productVariantSizeId, $storeId);
            } catch (\Throwable $e) {
                // Log exception gracefully
            }

            return $stock;
        });
    }

    /**
     * Set stock to an exact quantity atomically and log appropriate movement.
     */
    public function setStock(
        int $productVariantSizeId,
        int $targetQuantity,
        StockMovementType $movementType = StockMovementType::STOCK_CORRECTION,
        ?string $referenceType = null,
        ?int $referenceId = null,
        int $storeId = 0,
        int $warehouseId = 0,
        int $stockLocationId = 0,
        ?User $user = null,
        ?string $notes = null
    ): InventoryStock {
        if ($targetQuantity < 0) {
            throw new \InvalidArgumentException('Target stock quantity cannot be negative.');
        }

        return DB::transaction(function () use (
            $productVariantSizeId,
            $targetQuantity,
            $movementType,
            $referenceType,
            $referenceId,
            $storeId,
            $warehouseId,
            $stockLocationId,
            $user,
            $notes
        ) {
            $stock = $this->getStockRecord($productVariantSizeId, $storeId, $warehouseId, $stockLocationId, true);
            $stockBefore = $stock->stock_quantity;
            $delta = $targetQuantity - $stockBefore;

            if ($delta === 0) {
                // Still ensure alerts are updated for current state
                try {
                    $this->lowStockService->checkAndUpdateAlerts($productVariantSizeId, $storeId);
                } catch (\Throwable $e) {
                    // Log exception gracefully
                }
                return $stock;
            }

            if ($delta > 0) {
                return $this->addStock(
                    $productVariantSizeId,
                    $delta,
                    $movementType,
                    $referenceType,
                    $referenceId,
                    $storeId,
                    $warehouseId,
                    $stockLocationId,
                    $user,
                    $notes ?? "Stock reconciled to {$targetQuantity}"
                );
            } else {
                return $this->deductStock(
                    $productVariantSizeId,
                    abs($delta),
                    $movementType,
                    $referenceType,
                    $referenceId,
                    $storeId,
                    $warehouseId,
                    $stockLocationId,
                    $user,
                    false,
                    $notes ?? "Stock reconciled to {$targetQuantity}"
                );
            }
        });
    }
}
