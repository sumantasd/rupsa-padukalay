<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size_number' => $this->size_number,
            'size_system' => $this->size_system,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) ($this->is_active ?? true),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
