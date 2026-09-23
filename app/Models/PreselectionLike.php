<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's single like of the pre-selection (unique per user and pre-selection).
 */
class PreselectionLike extends Model
{
    /**
     * @return BelongsTo<PreselectionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PreselectionSubmission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
