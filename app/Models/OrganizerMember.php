<?php

namespace App\Models;

use App\Enums\OrganizerRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table('organizer_members', incrementing: true)]
#[Fillable(['organizer_id', 'user_id', 'role'])]
class OrganizerMember extends Pivot
{
    protected function casts(): array
    {
        return [
            'role' => OrganizerRole::class,
        ];
    }

    /**
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
