<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl(),
            'phone' => $this->phone,
            'phone_verified' => $this->hasVerifiedPhone(),
            'must_change_password' => (bool) $this->must_change_password,
            'is_judge' => $this->judgeAssignments()->exists(),
            'email' => $this->email,
            'country' => new CountryResource($this->whenLoaded('country')),
            'location' => $this->locationData(),
        ];
    }
}
