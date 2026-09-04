<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreTransferMatrixResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from_store_id' => (int) $this['from_store_id'],
            'from_store_name' => $this['from_store_name'],
            'to_store_id' => (int) $this['to_store_id'],
            'to_store_name' => $this['to_store_name'],
            'transfer_count' => (int) $this['transfer_count'],
            'total_sent_quantity' => (int) $this['total_sent_quantity'],
            'total_received_quantity' => (int) $this['total_received_quantity'],
            'total_valuation' => (float) $this['total_valuation'],
        ];
    }
}
