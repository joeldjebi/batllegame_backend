<?php

namespace App\Http\Resources;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stage
 */
class StageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rules = $this->phase->rules;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'status' => $this->status,
            'mode' => $this->phase->effectiveMode(),
            'submission_deadline' => $this->submission_deadline,
            'voting_opens_at' => $this->voting_opens_at,
            'voting_closes_at' => $this->voting_closes_at,
            'accepts_submissions' => $this->acceptsSubmissions(),
            'media_rules' => [
                'types' => $rules->mediaTypes,
                'max_duration_seconds' => $rules->mediaMaxDuration,
                'max_size_mb' => $rules->mediaMaxSizeMb,
            ],
        ];
    }
}
