<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_store_id' => $this->from_store_id,
            'from_store_name' => $this->fromStore?->name,
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse_name' => $this->fromWarehouse?->name,
            'to_store_id' => $this->to_store_id,
            'to_store_name' => $this->toStore?->name,
            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse_name' => $this->toWarehouse?->name,
            'status' => $this->status,
            'transfer_date' => $this->transfer_date?->toIso8601String(),
            'received_date' => $this->received_date?->toIso8601String(),
            'transferred_by' => $this->transferred_by,
            'transferred_by_name' => $this->transferredBy?->name,
            'received_by' => $this->received_by,
            'received_by_name' => $this->receivedBy?->name,
            'notes' => $this->notes,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
