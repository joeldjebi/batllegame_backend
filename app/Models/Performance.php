<?php

namespace App\Models;

use App\Enums\PerformanceSource;
use App\Enums\PerformanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['participant_id', 'turn', 'media_path', 'source', 'status'])]
class Performance extends Model
{
    /**
     * Mirrors the column defaults so enum-based helpers work before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PerformanceStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'turn' => 'integer',
            'source' => PerformanceSource::class,
            'status' => PerformanceStatus::class,
        ];
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
}
