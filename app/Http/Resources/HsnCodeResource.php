<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HsnCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'default_gst_rate' => (float) $this->default_gst_rate,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
