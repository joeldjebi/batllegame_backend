<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\PerformanceSource;
use App\Enums\PerformanceStatus;
use App\Models\Concerns\InheritsCompetitionId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A participant's media for a stage: an online submission (one per stage,
 * reused in all its matches) or an on-site captation uploaded by the organizer.
 */
#[Fillable(['stage_id', 'match_id', 'participant_id', 'turn', 'media_path', 'media_disk', 'media_type', 'mime_type', 'size_bytes', 'duration_seconds', 'original_name', 'source', 'status'])]
class Performance extends Model
{
    use InheritsCompetitionId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PerformanceStatus::Processing->value,
        'turn' => 1,
    ];

    protected function casts(): array
    {
        return [
            'turn' => 'integer',
            'source' => PerformanceSource::class,
            'status' => PerformanceStatus::class,
            'media_type' => MediaType::class,
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function competitionIdSourceKey(): string
    {
        return 'stage_id';
    }

    protected function resolveCompetitionId(): ?int
    {
        $phaseId = Stage::query()->whereKey($this->stage_id)->value('phase_id');

        return $phaseId ? Phase::query()->whereKey($phaseId)->value('competition_id') : null;
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * @return BelongsTo<BattleMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(BattleMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Visible to voters and judges.
     */
    public function isPublished(): bool
    {
        return $this->status === PerformanceStatus::Approved;
    }

    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk($this->media_disk ?? config('media.disk'))->url($this->media_path) : null;
    }

    public function deleteMedia(): void
    {
        if ($this->media_path) {
            Storage::disk($this->media_disk ?? config('media.disk'))->delete($this->media_path);
        }
    }
}
