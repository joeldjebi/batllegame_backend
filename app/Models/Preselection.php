<?php

namespace App\Models;

use App\Data\PreselectionRules;
use App\Enums\PreselectionState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pre-selection round of a competition: registered (paid) artists submit one
 * performance, the public likes (one like per user) and the jury scores, then
 * the organizer publishes the N selected artists.
 */
#[Fillable(['starts_at', 'ends_at', 'rules'])]
class Preselection extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'rules' => PreselectionRules::class,
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
     * @return HasMany<PreselectionSubmission, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PreselectionSubmission::class);
    }

    /**
     * @return HasMany<PreselectionLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PreselectionLike::class);
    }

    public function state(): PreselectionState
    {
        return match (true) {
            $this->published_at !== null => PreselectionState::Published,
            $this->starts_at->isFuture() => PreselectionState::Scheduled,
            $this->ends_at->isFuture() => PreselectionState::Open,
            default => PreselectionState::Closed,
        };
    }

    public function isOpen(): bool
    {
        return $this->state() === PreselectionState::Open;
    }

    /**
     * Rules can change until the pre-selection starts.
     */
    public function rulesFrozen(): bool
    {
        return $this->state() !== PreselectionState::Scheduled;
    }
}
