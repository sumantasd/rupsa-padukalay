<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'is_protected' => (bool) $this->is_protected,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'stores' => StoreResource::collection($this->whenLoaded('stores')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
