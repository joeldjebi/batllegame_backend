<?php

namespace App\Models;

use App\Enums\JudgeStatus;
use Database\Factories\JudgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status'])]
class Judge extends Model
{
    /** @use HasFactory<JudgeFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults so enum-based helpers work before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => JudgeStatus::Invited->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => JudgeStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<JuryScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(JuryScore::class);
    }

    public function isActive(): bool
    {
        return $this->status === JudgeStatus::Accepted;
    }
}
