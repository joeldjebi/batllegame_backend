<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\PerformanceSource;
use App\Enums\PerformanceStatus;
use App\Jobs\ProcessSubmission;
use App\Models\BattleMatch;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores participants' submissions and organizers' captations, and handles
 * their review.
 */
class SubmissionService
{
    /**
     * One submission per participant and stage: a new upload replaces the previous one.
     */
    public function submit(Participant $participant, Stage $stage, UploadedFile $file): Performance
    {
        return DB::transaction(function () use ($participant, $stage, $file): Performance {
            $performance = Performance::query()->firstOrNew([
                'stage_id' => $stage->id,
                'participant_id' => $participant->id,
                'turn' => 1,
            ]);

            $performance->deleteMedia();
            $performance->fill([
                ...$this->storeMedia($file, "submissions/{$participant->competition_id}/stage-{$stage->id}"),
                'source' => PerformanceSource::Submission,
                'status' => PerformanceStatus::Processing,
            ]);
            $performance->forceFill(['reviewed_by' => null, 'reviewed_at' => null, 'rejection_reason' => null])->save();

            ProcessSubmission::dispatch($performance)->afterCommit();

            return $performance;
        });
    }

    /**
     * On-site: the organizer uploads the recording of a live battle (published directly).
     */
    public function captation(BattleMatch $match, Participant $participant, UploadedFile $file, User $uploader): Performance
    {
        $performance = Performance::query()->firstOrNew(['match_id' => $match->id, 'participant_id' => $participant->id, 'turn' => 1]);
        $performance->deleteMedia();
        $performance->fill([
            ...$this->storeMedia($file, "captations/{$match->competition_id}/match-{$match->id}"),
            'stage_id' => $match->stage_id,
            'source' => PerformanceSource::Capture,
            'status' => PerformanceStatus::Approved,
        ]);
        $performance->forceFill(['reviewed_by' => $uploader->id, 'reviewed_at' => now()])->save();

        return $performance;
    }

    /**
     * @template T of Model
     *
     * @param  T  $performance  A Performance or a PreselectionSubmission.
     * @return T
     */
    public function approve(Model $performance, User $reviewer): Model
    {
        $performance->forceFill([
            'status' => PerformanceStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $performance;
    }

    /**
     * @template T of Model
     *
     * @param  T  $performance  A Performance or a PreselectionSubmission.
     * @return T
     */
    public function reject(Model $performance, User $reviewer, string $reason): Model
    {
        $performance->forceFill([
            'status' => PerformanceStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $performance;
    }

    /**
     * Store an uploaded media and describe it (columns shared by performances
     * and pre-selection entries).
     *
     * @return array<string, mixed>
     */
    public function storeMedia(UploadedFile $file, string $directory): array
    {
        $disk = config('media.disk');
        $mime = $file->getMimeType() ?? $file->getClientMimeType();

        return [
            'media_disk' => $disk,
            'media_path' => $file->storeAs($directory, Str::uuid().'.'.($file->guessExtension() ?? $file->getClientOriginalExtension()), $disk),
            'media_type' => MediaType::fromMime($mime),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'duration_seconds' => null,
        ];
    }
}
