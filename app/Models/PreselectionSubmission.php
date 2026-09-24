<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\PerformanceStatus;
use App\Models\Concerns\HasMediaFile;
use App\Models\Contracts\ReviewableMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered artist's single performance for the pre-selection.
 * Scores, likes_count and rank are derived (PreselectionService::rank()).
 */
class PreselectionSubmission extends Model implements ReviewableMedia
{
    use HasMediaFile;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PerformanceStatus::Processing->value,
        'likes_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'status' => PerformanceStatus::class,
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'reviewed_at' => 'datetime',
            'likes_count' => 'integer',
            'jury_score' => 'float',
            'like_score' => 'float',
            'final_score' => 'float',
            'rank' => 'integer',
            'selected' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Preselection, $this>
     */
    public function preselection(): BelongsTo
    {
        return $this->belongsTo(Preselection::class);
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return HasMany<PreselectionLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PreselectionLike::class, 'submission_id');
    }

    /**
     * @return HasMany<PreselectionScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(PreselectionScore::class, 'submission_id');
    }

    public function isPublished(): bool
    {
        return $this->status === PerformanceStatus::Approved;
    }

    public function maxMediaDuration(): int
    {
        return $this->preselection->rules->mediaMaxDuration;
    }

    public function submissionWindow(): array
    {
        // Open from its creation (no opening date) to the submission deadline.
        return [$this->preselection?->created_at, $this->preselection?->ends_at];
    }

    public function requiresReview(): bool
    {
        return $this->competition->settings->submissionsRequireApproval;
    }
}
