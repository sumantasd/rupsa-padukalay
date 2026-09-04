<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'pincode' => $this->pincode,
            'manager_user_id' => $this->manager_user_id,
            'manager_name' => $this->manager?->name,
            'is_active' => (bool) $this->is_active,
            'stores' => StoreResource::collection($this->whenLoaded('stores')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
