<?php

namespace App\Data;

use App\Enums\GroupDrawMethod;
use App\Enums\MediaType;
use App\Enums\PhaseType;
use App\Enums\TieBreaker;
use App\Enums\VoteMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rules of a phase, stored in phases.rules. Frozen once the phase has started.
 */
final readonly class PhaseRules extends JsonData
{
    /**
     * @param  list<TieBreaker>  $tieBreakers  Ordered, first one is applied first.
     */
    public function __construct(
        public int $rounds,
        public int $turnDuration,
        public VoteMode $voteMode,
        public int $juryWeight,
        public int $publicWeight,
        public array $tieBreakers,
        public int $pointsWin,
        public int $pointsDraw,
        public int $pointsLoss,
        public bool $allowDraws,
        public ?int $groupCount,
        // Participants the organizer plans for (sizes the groups before registrations close).
        public ?int $expectedEntrants,
        public GroupDrawMethod $drawMethod,
        public bool $grandFinalReset,
        /** @var list<MediaType> */
        public array $mediaTypes,
        // Online phases: maximum duration (seconds) and size (MB) of a submission.
        public int $mediaMaxDuration,
        public int $mediaMaxSizeMb,
    ) {}

    public static function fromArray(array $data): static
    {
        $validated = Validator::make($data, [
            'rounds' => ['sometimes', 'integer', 'between:1,10'],
            'turn_duration' => ['sometimes', 'integer', 'between:15,3600'],
            'vote_mode' => ['sometimes', Rule::enum(VoteMode::class)],
            'jury_weight' => ['sometimes', 'integer', 'between:0,100'],
            'public_weight' => ['sometimes', 'integer', 'between:0,100'],
            'tie_breakers' => ['sometimes', 'array', 'min:1'],
            'tie_breakers.*' => ['distinct', Rule::enum(TieBreaker::class)],
            'points_win' => ['sometimes', 'integer', 'between:0,10'],
            'points_draw' => ['sometimes', 'integer', 'between:0,10'],
            'points_loss' => ['sometimes', 'integer', 'between:0,10'],
            'allow_draws' => ['sometimes', 'boolean'],
            'group_count' => ['sometimes', 'nullable', 'integer', 'between:1,64'],
            'expected_entrants' => ['sometimes', 'nullable', 'integer', 'between:2,1024'],
            'draw_method' => ['sometimes', Rule::enum(GroupDrawMethod::class)],
            'grand_final_reset' => ['sometimes', 'boolean'],
            'media_types' => ['sometimes', 'array', 'min:1'],
            'media_types.*' => ['distinct', Rule::enum(MediaType::class)],
            'media_max_duration' => ['sometimes', 'integer', 'between:10,1800'],
            'media_max_size_mb' => ['sometimes', 'integer', 'between:1,2048'],
        ])->validate();

        $voteMode = VoteMode::from($validated['vote_mode'] ?? VoteMode::Mixed->value);

        // Weights only matter in mixed mode; single-source modes are normalized.
        [$juryWeight, $publicWeight] = match ($voteMode) {
            VoteMode::Jury => [100, 0],
            VoteMode::Public => [0, 100],
            VoteMode::Mixed => [
                (int) ($validated['jury_weight'] ?? 50),
                (int) ($validated['public_weight'] ?? 50),
            ],
        };

        if ($voteMode === VoteMode::Mixed && ($juryWeight + $publicWeight !== 100 || $juryWeight === 0 || $publicWeight === 0)) {
            throw ValidationException::withMessages([
                'jury_weight' => 'En mode mixte, les poids du jury et du public doivent être positifs et totaliser 100 %.',
            ]);
        }

        return new self(
            rounds: (int) ($validated['rounds'] ?? 1),
            turnDuration: (int) ($validated['turn_duration'] ?? 60),
            voteMode: $voteMode,
            juryWeight: $juryWeight,
            publicWeight: $publicWeight,
            tieBreakers: array_map(
                TieBreaker::from(...),
                $validated['tie_breakers'] ?? [TieBreaker::JuryScore->value, TieBreaker::PublicScore->value, TieBreaker::Seed->value],
            ),
            pointsWin: (int) ($validated['points_win'] ?? 3),
            pointsDraw: (int) ($validated['points_draw'] ?? 1),
            pointsLoss: (int) ($validated['points_loss'] ?? 0),
            allowDraws: (bool) ($validated['allow_draws'] ?? false),
            groupCount: isset($validated['group_count']) ? (int) $validated['group_count'] : null,
            expectedEntrants: isset($validated['expected_entrants']) ? (int) $validated['expected_entrants'] : null,
            drawMethod: GroupDrawMethod::from($validated['draw_method'] ?? GroupDrawMethod::Random->value),
            grandFinalReset: (bool) ($validated['grand_final_reset'] ?? false),
            mediaTypes: array_map(MediaType::from(...), $validated['media_types'] ?? MediaType::values()),
            mediaMaxDuration: (int) ($validated['media_max_duration'] ?? 180),
            mediaMaxSizeMb: (int) ($validated['media_max_size_mb'] ?? 200),
        );
    }

    public function toArray(): array
    {
        return [
            'rounds' => $this->rounds,
            'turn_duration' => $this->turnDuration,
            'vote_mode' => $this->voteMode->value,
            'jury_weight' => $this->juryWeight,
            'public_weight' => $this->publicWeight,
            'tie_breakers' => array_map(fn (TieBreaker $t) => $t->value, $this->tieBreakers),
            'points_win' => $this->pointsWin,
            'points_draw' => $this->pointsDraw,
            'points_loss' => $this->pointsLoss,
            'allow_draws' => $this->allowDraws,
            'group_count' => $this->groupCount,
            'expected_entrants' => $this->expectedEntrants,
            'draw_method' => $this->drawMethod->value,
            'grand_final_reset' => $this->grandFinalReset,
            'media_types' => array_map(fn (MediaType $t) => $t->value, $this->mediaTypes),
            'media_max_duration' => $this->mediaMaxDuration,
            'media_max_size_mb' => $this->mediaMaxSizeMb,
        ];
    }

    /**
     * Check the rules that depend on the phase type.
     *
     * @throws ValidationException
     */
    public function assertCompatibleWith(PhaseType $type): void
    {
        $errors = [];

        if ($this->allowDraws && $type !== PhaseType::Groups) {
            $errors['allow_draws'] = 'Les matchs nuls ne sont possibles qu\'en phase de poules.';
        }

        if ($this->grandFinalReset && $type !== PhaseType::DoubleElimination) {
            $errors['grand_final_reset'] = 'La finale « reset » n\'existe qu\'en double élimination.';
        }

        if ($type === PhaseType::Groups && $this->groupCount === null) {
            $errors['group_count'] = 'Le nombre de poules est obligatoire pour une phase de poules.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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

    public function usesPublic(): bool
    {
        return $this->publicWeight > 0;
    }
}
