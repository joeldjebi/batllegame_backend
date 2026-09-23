<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Keeps a denormalized competition_id in sync with the parent it is derived from
 * (e.g. matches.competition_id from phases.competition_id).
 *
 * The value is filled automatically when missing, and a mismatching value is
 * rejected so that a row can never be attached to another organizer's competition.
 */
trait InheritsCompetitionId
{
    /**
     * Foreign key whose parent owns the competition_id (e.g. "phase_id").
     */
    abstract protected function competitionIdSourceKey(): string;

    /**
     * Resolve the competition_id from the parent record.
     */
    abstract protected function resolveCompetitionId(): ?int;

    protected static function bootInheritsCompetitionId(): void
    {
        static::saving(function (self $model): void {
            if ($model->exists && ! $model->isDirty([$model->competitionIdSourceKey(), 'competition_id'])) {
                return;
            }

            $expected = $model->resolveCompetitionId();

            if ($expected === null) {
                return;
            }

            if ($model->competition_id === null) {
                $model->competition_id = $expected;
            } elseif ((int) $model->competition_id !== $expected) {
                throw new LogicException(sprintf(
                    '%s: competition_id %d does not match its parent competition %d.',
                    static::class,
                    $model->competition_id,
                    $expected,
                ));
            }
        });
    }
}
