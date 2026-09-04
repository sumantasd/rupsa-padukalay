<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate_percentage' => (float) $this->rate_percentage,
            'cgst_percentage' => (float) $this->cgst_percentage,
            'sgst_percentage' => (float) $this->sgst_percentage,
            'igst_percentage' => (float) $this->igst_percentage,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
