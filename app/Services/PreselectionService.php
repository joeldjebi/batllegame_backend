<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PreselectionState;
use App\Exceptions\CompetitionFlowException;
use App\Jobs\ProcessSubmission;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Participant;
use App\Models\Preselection;
use App\Models\PreselectionLike;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * The pre-selection round:
 *  1. registered (paid) artists submit one performance during the period;
 *  2. the public likes one entry per competition until the end of the vote (when the
 *     organizer enabled public voting), judges score until the end of the deliberation;
 *  3. then the organizer publishes: the N best final scores
 *     (jury × jury_weight + likes × like_weight) become the competition's artists.
 */
class PreselectionService
{
    public function __construct(private SubmissionService $media) {}

    /**
     * Create or update the pre-selection of a competition.
     *
     * @param  array{starts_at: string, ends_at: string, vote_ends_at?: ?string, deliberation_hours?: int, rules?: array<string, mixed>}  $data
     */
    public function configure(Competition $competition, array $data): Preselection
    {
        $preselection = $competition->preselection ?? new Preselection;

        if ($preselection->exists && $preselection->state() === PreselectionState::Published) {
            throw CompetitionFlowException::preselectionPublished();
        }

        // Rules are frozen once artists have started to compete; dates can still move.
        if ($preselection->exists && $preselection->rulesFrozen()) {
            unset($data['rules']);
        }

        $preselection->fill($data);
        $competition->preselection()->save($preselection);
        $competition->setRelation('preselection', $preselection);

        return $preselection;
    }

    /**
     * One entry per artist: a new upload replaces the previous one until the end.
     */
    public function submit(Participant $participant, UploadedFile $file, ?Carbon $clientModifiedAt = null): PreselectionSubmission
    {
        $preselection = $participant->competition->preselection;

        if ($preselection === null || ! $preselection->isOpen()) {
            throw CompetitionFlowException::preselectionClosed();
        }

        // Paid competitions: never accept a performance before the fee is paid.
        if ($participant->status !== ParticipantStatus::Registered || ! $participant->hasPaid()) {
            throw CompetitionFlowException::preselectionNotEligible();
        }

        return DB::transaction(function () use ($participant, $preselection, $file, $clientModifiedAt): PreselectionSubmission {
            $entry = PreselectionSubmission::query()
                ->where('preselection_id', $preselection->id)
                ->where('participant_id', $participant->id)
                ->first() ?? new PreselectionSubmission;

            $entry->deleteMedia();
            $entry->forceFill([
                'preselection_id' => $preselection->id,
                'participant_id' => $participant->id,
                ...$this->media->storeMedia($file, "preselections/{$participant->competition_id}"),
                'competition_id' => $participant->competition_id,
                'status' => PerformanceStatus::Processing,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                ...SubmissionService::freshProvenance($clientModifiedAt),
            ])->save();

            ProcessSubmission::dispatch($entry)->afterCommit();

            return $entry;
        });
    }

    /**
     * A user likes one entry per competition; liking another entry moves the like.
     */
    public function like(User $user, PreselectionSubmission $entry, ?string $deviceId = null, ?string $ip = null): PreselectionLike
    {
        return DB::transaction(function () use ($user, $entry, $deviceId, $ip): PreselectionLike {
            $like = PreselectionLike::query()->lockForUpdate()
                ->where('preselection_id', $entry->preselection_id)
                ->where('user_id', $user->id)
                ->first() ?? new PreselectionLike;
            $previous = $like->exists ? $like->submission_id : null;

            $like->forceFill([
                'preselection_id' => $entry->preselection_id,
                'user_id' => $user->id,
                'submission_id' => $entry->id,
                'competition_id' => $entry->competition_id,
                'device_id' => $deviceId,
                'ip' => $ip,
            ]);

            try {
                DB::transaction(fn () => $like->save());
            } catch (UniqueConstraintViolationException) {
                abort(409, 'Vous avez déjà liké une prestation de cette compétition.');
            }

            $this->refreshLikes(array_filter([$previous, $entry->id]));

            return $like;
        });
    }

    public function unlike(User $user, Preselection $preselection): void
    {
        $like = $preselection->likes()->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();
            $this->refreshLikes([$like->submission_id]);
        }
    }

    /**
     * @param  array<string, mixed>  $input  scores[{criterion_id, score, comment?}]
     */
    public function score(Judge $judge, PreselectionSubmission $entry, array $input): void
    {
        $criteria = $entry->competition->criteria()->get()->keyBy('id');

        $validated = Validator::make($input, [
            'scores' => ['required', 'array', 'size:'.$criteria->count()],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct', Rule::in($criteria->keys())],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.comment' => ['nullable', 'string', 'max:1000'],
        ])->after(function (ValidationValidator $validator) use ($criteria, $input): void {
            foreach ((array) ($input['scores'] ?? []) as $i => $row) {
                $criterion = $criteria->get($row['criterion_id'] ?? null);

                if ($criterion !== null && is_numeric($row['score'] ?? null) && $row['score'] > $criterion->max_points) {
                    $validator->errors()->add("scores.{$i}.score", "La note maximale pour « {$criterion->name} » est {$criterion->max_points}.");
                }
            }
        })->validate();

        DB::transaction(function () use ($validated, $entry, $judge): void {
            foreach ($validated['scores'] as $row) {
                PreselectionScore::query()->updateOrCreate(
                    ['submission_id' => $entry->id, 'judge_id' => $judge->id, 'criterion_id' => $row['criterion_id']],
                    ['score' => $row['score'], 'comment' => $row['comment'] ?? null],
                );
            }
        });
    }

    /**
     * Recompute every derived score and the ranking of the approved entries.
     * Jury: weighted criteria normalized on 100, mean of the judges who scored.
     * Likes: likes relative to the most liked entry, on 100.
     */
    public function rank(Preselection $preselection): void
    {
        $weights = $preselection->effectiveWeights();
        $criteria = $preselection->competition->criteria()->get()->keyBy('id');
        // Unpaid artists never compete, even with an approved entry.
        $entries = $preselection->entries()->with(['scores', 'participant.competition', 'participant.payments'])->where('status', PerformanceStatus::Approved)->get()
            ->filter(fn (PreselectionSubmission $entry) => $entry->participant->hasPaid())->values();
        $maxLikes = max(1, (int) $entries->max('likes_count'));

        $computed = $entries->map(function (PreselectionSubmission $entry) use ($criteria, $maxLikes, $weights) {
            $perJudge = $entry->scores->groupBy('judge_id')->map(function ($rows) use ($criteria) {
                $weighted = $weights = 0.0;
                foreach ($rows as $row) {
                    $criterion = $criteria->get($row->criterion_id);
                    if ($criterion && $criterion->max_points > 0) {
                        $weighted += $criterion->weight * ($row->score / $criterion->max_points);
                        $weights += $criterion->weight;
                    }
                }

                return $weights > 0 ? $weighted / $weights * 100 : null;
            })->filter(fn ($score) => $score !== null);

            $jury = $perJudge->isEmpty() ? null : round($perJudge->avg(), 2);
            $likes = round($entry->likes_count / $maxLikes * 100, 2);
            $final = round(($jury ?? 0) * $weights['jury'] / 100 + $likes * $weights['likes'] / 100, 2);

            return compact('entry', 'jury', 'likes', 'final');
        })->sort(fn ($a, $b) => [$b['final'], $b['jury'] ?? 0, $b['entry']->likes_count, $a['entry']->participant_id]
            <=> [$a['final'], $a['jury'] ?? 0, $a['entry']->likes_count, $b['entry']->participant_id])
            ->values();

        DB::transaction(function () use ($computed, $preselection): void {
            $preselection->entries()->whereNotIn('id', $computed->pluck('entry.id'))->update(['rank' => null, 'final_score' => null]);

            foreach ($computed as $index => ['entry' => $entry, 'jury' => $jury, 'likes' => $likes, 'final' => $final]) {
                $entry->forceFill(['jury_score' => $jury, 'like_score' => $likes, 'final_score' => $final, 'rank' => $index + 1])->save();
            }
        });
    }

    /**
     * Apply the selection: the N best approved entries' artists become the
     * competition's participants, every other registered artist is not retained.
     */
    public function publish(Preselection $preselection): int
    {
        if ($preselection->state() !== PreselectionState::Closed) {
            throw $preselection->state() === PreselectionState::Published
                ? CompetitionFlowException::preselectionPublished()
                : CompetitionFlowException::preselectionStillOpen();
        }

        $approved = $preselection->entries()->where('status', PerformanceStatus::Approved);

        if ((clone $approved)->count() === 0) {
            throw CompetitionFlowException::preselectionEmpty();
        }

        if ($preselection->entries()->whereIn('status', [PerformanceStatus::Processing, PerformanceStatus::Pending])->exists()) {
            throw CompetitionFlowException::submissionsToReview($preselection->entries()->whereIn('status', [PerformanceStatus::Processing, PerformanceStatus::Pending])->count());
        }

        // Deliberation is over: entries a judge did not score are ranked with the scores received.
        $this->rank($preselection);

        return DB::transaction(function () use ($preselection): int {
            $size = $preselection->rules->selectionSize;
            $selectedIds = $preselection->entries()->whereNotNull('rank')->where('rank', '<=', $size)->pluck('participant_id');

            $preselection->entries()->update(['selected' => false]);
            $preselection->entries()->whereIn('participant_id', $selectedIds)->update(['selected' => true]);

            $competition = $preselection->competition;
            $competition->participants()->whereIn('id', $selectedIds)->update(['status' => ParticipantStatus::Validated]);
            $competition->participants()
                ->whereNotIn('id', $selectedIds)
                ->whereIn('status', [ParticipantStatus::Registered, ParticipantStatus::PaymentPending])
                ->update(['status' => ParticipantStatus::NotSelected]);

            $preselection->forceFill(['published_at' => now()])->save();

            return $selectedIds->count();
        });
    }

    /**
     * Approved entries without any jury score (warning shown before publishing).
     */
    public function unscoredCount(Preselection $preselection): int
    {
        if ($preselection->effectiveWeights()['jury'] === 0) {
            return 0;
        }

        return $preselection->entries()->where('status', PerformanceStatus::Approved)->whereDoesntHave('scores')->count();
    }

    /**
     * @param  list<int>  $entryIds
     */
    private function refreshLikes(array $entryIds): void
    {
        foreach (array_unique($entryIds) as $id) {
            PreselectionSubmission::query()->whereKey($id)->update([
                'likes_count' => PreselectionLike::query()->where('submission_id', $id)->count(),
            ]);
        }
    }
}
