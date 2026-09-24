<?php

namespace App\Models;

use App\Data\PhaseRules;
use App\Enums\CompetitionMode;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Exceptions\PhaseRulesFrozenException;
use Database\Factories\PhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'position', 'mode', 'qualifiers_per_group', 'rules'])]
class Phase extends Model
{
    /** @use HasFactory<PhaseFactory> */
    use HasFactory;

    /**
     * Attributes that can no longer change once the phase has started.
     */
    public const array FROZEN_ATTRIBUTES = ['type', 'rules', 'qualifiers_per_group'];

    /**
     * Mirrors the column default so a freshly created phase is not seen as started.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PhaseStatus::Pending->value,
    ];

    protected static function booted(): void
    {
        static::saving(function (Phase $phase): void {
            if (! $phase->isDirty(['type', 'rules'])) {
                return;
            }

            $phase->rules->assertCompatibleWith($phase->type);
        });

        static::updating(function (Phase $phase): void {
            $originalStatus = $phase->getOriginal('status');

            if ($originalStatus !== PhaseStatus::Pending && $phase->isDirty(self::FROZEN_ATTRIBUTES)) {
                throw PhaseRulesFrozenException::for($phase);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => PhaseType::class,
            'mode' => CompetitionMode::class,
            'status' => PhaseStatus::class,
            'position' => 'integer',
            'qualifiers_per_group' => 'integer',
            'rules' => PhaseRules::class,
            'calendar' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'results_published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('name');
    }

    /**
     * @return HasMany<Stage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('number');
    }

    /**
     * @return HasMany<BattleMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BattleMatch::class);
    }

    /**
     * Mode of the phase, falling back to the competition mode when not overridden.
     */
    public function effectiveMode(): CompetitionMode
    {
        return $this->mode ?? $this->competition->mode;
    }

    public function isFrozen(): bool
    {
        return $this->status !== PhaseStatus::Pending;
    }

    /**
     * Start the phase and freeze its rules.
     */
    public function markAsStarted(): void
    {
        $this->forceFill([
            'status' => PhaseStatus::InProgress,
            'started_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Next phase of the same competition, if any.
     */
    public function nextPhase(): ?Phase
    {
        return self::query()
            ->where('competition_id', $this->competition_id)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }
}
