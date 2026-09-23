<?php

namespace App\Http\Resources;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Competition
 */
class CompetitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'discipline' => $this->discipline,
            'mode' => $this->mode,
            'status' => $this->status,
            'registration_open' => $this->isRegistrationOpen(),
            'registration_ends_at' => $this->registration_ends_at,
            'max_participants' => $this->max_participants,
            'entry_fee' => $this->entry_fee,
            'currency' => $this->currency,
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'name' => $this->organizer->name,
                'slug' => $this->organizer->slug,
                'logo_path' => $this->organizer->logo_path,
                'city' => $this->organizer->city,
            ]),
            'phases' => PhaseResource::collection($this->whenLoaded('phases')),
            'criteria' => $this->whenLoaded('criteria', fn () => $this->criteria->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'max_points' => $c->max_points,
            ])),
        ];
    }
}
