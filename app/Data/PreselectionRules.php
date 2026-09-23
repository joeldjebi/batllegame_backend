<?php

namespace App\Data;

use App\Enums\MediaType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rules of a pre-selection, stored in preselections.rules. Frozen once it has started.
 */
final readonly class PreselectionRules extends JsonData
{
    /**
     * @param  list<MediaType>  $mediaTypes
     */
    public function __construct(
        // Share of the final score given by public likes and by the jury (sum = 100).
        public int $likeWeight,
        public int $juryWeight,
        // Number of artists retained for the competition.
        public int $selectionSize,
        public array $mediaTypes,
        public int $mediaMaxDuration,
        public int $mediaMaxSizeMb,
    ) {}

    public static function fromArray(array $data): static
    {
        $validated = Validator::make($data, [
            'like_weight' => ['sometimes', 'integer', 'between:0,100'],
            'jury_weight' => ['sometimes', 'integer', 'between:0,100'],
            'selection_size' => ['sometimes', 'integer', 'between:2,1024'],
            'media_types' => ['sometimes', 'array', 'min:1'],
            'media_types.*' => ['distinct', Rule::enum(MediaType::class)],
            'media_max_duration' => ['sometimes', 'integer', 'between:10,1800'],
            'media_max_size_mb' => ['sometimes', 'integer', 'between:1,2048'],
        ])->validate();

        $likeWeight = (int) ($validated['like_weight'] ?? 50);
        $juryWeight = (int) ($validated['jury_weight'] ?? 50);

        if ($likeWeight + $juryWeight !== 100) {
            throw ValidationException::withMessages(['like_weight' => 'Les pourcentages des likes et du jury doivent totaliser 100 %.']);
        }

        return new self(
            likeWeight: $likeWeight,
            juryWeight: $juryWeight,
            selectionSize: (int) ($validated['selection_size'] ?? 16),
            mediaTypes: array_map(MediaType::from(...), $validated['media_types'] ?? MediaType::values()),
            mediaMaxDuration: (int) ($validated['media_max_duration'] ?? 180),
            mediaMaxSizeMb: (int) ($validated['media_max_size_mb'] ?? 200),
        );
    }

    public function toArray(): array
    {
        return [
            'like_weight' => $this->likeWeight,
            'jury_weight' => $this->juryWeight,
            'selection_size' => $this->selectionSize,
            'media_types' => array_map(fn (MediaType $t) => $t->value, $this->mediaTypes),
            'media_max_duration' => $this->mediaMaxDuration,
            'media_max_size_mb' => $this->mediaMaxSizeMb,
        ];
    }

    /**
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        return array_merge(...array_map(fn (MediaType $t) => $t->mimeTypes(), $this->mediaTypes));
    }

    public function usesJury(): bool
    {
        return $this->juryWeight > 0;
    }
}
