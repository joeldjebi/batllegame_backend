<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\PerformanceSource;
use App\Enums\PerformanceStatus;
use App\Exceptions\CompetitionFlowException;
use App\Jobs\OptimizeMedia;
use App\Jobs\ProcessSubmission;
use App\Models\BattleMatch;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
    public function submit(Participant $participant, Stage $stage, UploadedFile $file, ?Carbon $clientModifiedAt = null): Performance
    {
        if (! $participant->hasPaid()) {
            throw CompetitionFlowException::paymentRequired();
        }

        return DB::transaction(function () use ($participant, $stage, $file, $clientModifiedAt): Performance {
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
            $performance->forceFill([
                'reviewed_by' => null, 'reviewed_at' => null, 'rejection_reason' => null,
                ...self::freshProvenance($clientModifiedAt),
            ])->save();

            ProcessSubmission::dispatch($performance)->afterCommit();

            return $performance;
        });
    }

    /**
     * File date reported by the browser / app (File.lastModified, in ms or ISO 8601).
     * Kept only when plausible; it is an indication, the device can change it.
     */
    public static function clientModifiedAt(Request $request): ?Carbon
    {
        $value = $request->input('client_modified_at');

        $date = match (true) {
            is_numeric($value) => Carbon::createFromTimestampMsUTC((int) $value),
            is_string($value) && $value !== '' => rescue(fn () => Carbon::parse($value)->utc(), null, false),
            default => null,
        };

        return $date && $date->year >= 2000 && $date->lessThanOrEqualTo(now()->addDay()) ? $date : null;
    }

    /**
     * Provenance columns reset on every new upload (ProcessSubmission reads them again).
     *
     * @return array<string, mixed>
     */
    public static function freshProvenance(?Carbon $clientModifiedAt): array
    {
        return [
            'recorded_at' => null,
            'media_origin' => null,
            // The row is reused when an artist replaces the media: keep the date of this upload.
            'media_metadata' => ['uploaded_at' => now()->toIso8601String()],
            'client_modified_at' => $clientModifiedAt,
        ];
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

        // Published at once: optimized in the background (the original plays meanwhile).
        OptimizeMedia::dispatch($performance)->afterCommit();

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
