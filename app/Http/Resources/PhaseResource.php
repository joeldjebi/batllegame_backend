<?php

namespace App\Http\Resources;

use App\Models\Phase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Phase
 */
class PhaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'position' => $this->position,
            'mode' => $this->mode,
            'status' => $this->status,
            'rules' => [
                'rounds' => $this->rules->rounds,
                'turn_duration' => $this->rules->turnDuration,
                'vote_mode' => $this->rules->voteMode,
                'jury_weight' => $this->rules->juryWeight,
                'public_weight' => $this->rules->publicWeight,
            ],
        ];
    }
}
