<?php

namespace App\Data;

use Illuminate\Support\Facades\Validator;

/**
 * Competition-wide options, stored in competitions.settings.
 */
final readonly class CompetitionSettings extends JsonData
{
    public function __construct(
        // Registrations must be validated by the organizer before the artist takes part.
        public bool $registrationRequiresApproval,
        public bool $publicVotingEnabled,
        // Whether public vote counts are visible before voting closes.
        public bool $showLiveResults,
        // Anti-fraud: max votes from the same device on one match (null = unlimited).
        public ?int $maxVotesPerDevice,
        public string $timezone,
        // Submissions must be approved by the organizer before voters and judges see them.
        public bool $submissionsRequireApproval,
        // On-site phases: a code shown in the room is required to vote (only people present).
        public bool $onsiteVoteCode,
    ) {}

    public static function fromArray(array $data): static
    {
        $validated = Validator::make($data, [
            'registration_requires_approval' => ['sometimes', 'boolean'],
            'public_voting_enabled' => ['sometimes', 'boolean'],
            'show_live_results' => ['sometimes', 'boolean'],
            'max_votes_per_device' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'timezone' => ['sometimes', 'timezone:all'],
            'submissions_require_approval' => ['sometimes', 'boolean'],
            'onsite_vote_code' => ['sometimes', 'boolean'],
        ])->validate();

        return new self(
            registrationRequiresApproval: (bool) ($validated['registration_requires_approval'] ?? true),
            publicVotingEnabled: (bool) ($validated['public_voting_enabled'] ?? true),
            showLiveResults: (bool) ($validated['show_live_results'] ?? false),
            maxVotesPerDevice: isset($validated['max_votes_per_device']) ? (int) $validated['max_votes_per_device'] : null,
            timezone: $validated['timezone'] ?? 'Africa/Abidjan',
            submissionsRequireApproval: (bool) ($validated['submissions_require_approval'] ?? true),
            onsiteVoteCode: (bool) ($validated['onsite_vote_code'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'registration_requires_approval' => $this->registrationRequiresApproval,
            'public_voting_enabled' => $this->publicVotingEnabled,
            'show_live_results' => $this->showLiveResults,
            'max_votes_per_device' => $this->maxVotesPerDevice,
            'timezone' => $this->timezone,
            'submissions_require_approval' => $this->submissionsRequireApproval,
            'onsite_vote_code' => $this->onsiteVoteCode,
        ];
    }
}
