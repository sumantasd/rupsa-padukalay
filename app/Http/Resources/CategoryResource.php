<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_url' => $this->image_url,
            'is_visible_on_web' => (bool) $this->is_visible_on_web,
            'is_active' => (bool) $this->is_active,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
