<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'judge_id', 'criterion_id', 'score', 'comment'])]
class PreselectionScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    /**
     * @return BelongsTo<PreselectionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PreselectionSubmission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<Judge, $this>
     */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(Judge::class);
    }

    /**
     * @return BelongsTo<Criterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
