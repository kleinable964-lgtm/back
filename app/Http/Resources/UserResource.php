<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'role' => $this->role,
            'permission_group' => $this->permission_group,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'desk_count' => $this->whenLoaded('desks', fn (): int => $this->desks->count()),

            'desks' => DeskResource::collection($this->whenLoaded('desks')),

        ];
    }
}
