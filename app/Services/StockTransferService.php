<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Execute an atomic inter-store or store <-> warehouse stock transfer.
     */
    public function createAndExecuteTransfer(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $fromStoreId = (int) ($data['from_store_id'] ?? 0);
            $fromWarehouseId = (int) ($data['from_warehouse_id'] ?? 0);
            $toStoreId = (int) ($data['to_store_id'] ?? 0);
            $toWarehouseId = (int) ($data['to_warehouse_id'] ?? 0);

            if ($fromStoreId === $toStoreId && $fromWarehouseId === $toWarehouseId) {
                throw new \InvalidArgumentException('Source and destination locations cannot be identical.');
            }

            $transferNumber = 'TRF-'.date('Ymd').'-'.str_pad((string) (StockTransfer::count() + 1), 5, '0', STR_PAD_LEFT);

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_store_id' => $fromStoreId ?: null,
                'from_warehouse_id' => $fromWarehouseId ?: null,
                'to_store_id' => $toStoreId ?: null,
                'to_warehouse_id' => $toWarehouseId ?: null,
                'status' => 'completed',
                'transfer_date' => now(),
                'received_date' => now(),
                'transferred_by' => $user->id,
                'received_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $pvsId = (int) $item['product_variant_size_id'];
                $qty = (int) $item['quantity'];

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_variant_size_id' => $pvsId,
                    'quantity_sent' => $qty,
                    'quantity_received' => $qty,
                ]);

                // 1. Deduct from Source
                $this->inventoryService->deductStock(
                    $pvsId,
                    $qty,
                    StockMovementType::TRANSFER_OUT,
                    StockTransfer::class,
                    $transfer->id,
                    $fromStoreId,
                    $fromWarehouseId,
                    0,
                    $user,
                    false,
                    "Stock transfer out ({$transferNumber})"
                );

                // 2. Add to Destination
                $this->inventoryService->addStock(
                    $pvsId,
                    $qty,
                    StockMovementType::TRANSFER_IN,
                    StockTransfer::class,
                    $transfer->id,
                    $toStoreId,
                    $toWarehouseId,
                    0,
                    $user,
                    "Stock transfer in ({$transferNumber})"
                );
            }

            return $transfer->load('items');
        });
    }
}
