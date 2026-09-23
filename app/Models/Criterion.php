<?php

namespace App\Models;

use Database\Factories\CriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('criteria')]
#[Fillable(['name', 'max_points', 'weight', 'position'])]
class Criterion extends Model
{
    /** @use HasFactory<CriterionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'max_points' => 'integer',
            'weight' => 'float',
            'position' => 'integer',
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
     * @return HasMany<JuryScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(JuryScore::class);
    }
}
