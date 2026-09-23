<?php

namespace App\Http\Resources;

use App\Models\Performance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Performance
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participant_id' => $this->participant_id,
            'type' => $this->media_type,
            'source' => $this->source,
            'url' => $this->mediaUrl(),
            'duration_seconds' => $this->duration_seconds,
        ];
    }
}
