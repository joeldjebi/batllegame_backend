use App\Jobs\OptimizeMedia;
use App\Models\Competition;
use App\Models\Performance;
use App\Models\PreselectionSubmission;
use App\Services\SubmissionService;
use Illuminate\Http\UploadedFile;

// Replaces the media of a competition's artists (stage performances and pre-selection
// entries) by the videos $VIDEO_DIR/{id}.mp4, one per artist (same video for both),
// keeping their review status. See scripts/test-data/README.md.
// Only media uploaded by these scripts are touched: a real upload (from the app or
// the web, which send the file date, or with its own file name) is always kept.
$competition = Competition::where('name', getenv('COMPETITION'))->firstOrFail();
$videoDir = rtrim((string) getenv('VIDEO_DIR'), '/');
$ids = array_values(array_filter(array_map('trim', explode(',', (string) getenv('VIDEO_IDS')))));

$participantIds = $competition->participants()->orderBy('seed')->orderBy('id')->pluck('id')->all();
$videoFor = fn (int $participantId): string => $videoDir.'/'.$ids[array_search($participantId, $participantIds, true) % count($ids)].'.mp4';

$media = [
    ...Performance::whereIn('participant_id', $participantIds)->whereNotNull('media_path')->get(),
    ...PreselectionSubmission::where('competition_id', $competition->id)->whereNotNull('media_path')->get(),
];

$isScripted = fn ($item): bool => $item->client_modified_at === null
    && preg_match('/^(prestation(-\d+)?|take|\d+)\.(mp4|m4v)$/', (string) $item->original_name) === 1;

$replaced = 0;
$kept = [];
foreach ($media as $item) {
    if (! $isScripted($item)) {
        $kept[] = "{$item->participant->stage_name} ({$item->original_name})";

        continue;
    }
    $source = $videoFor($item->participant_id);
    $tmp = sys_get_temp_dir()."/bg-replace-{$item->getTable()}-{$item->id}.mp4";
    copy($source, $tmp);
    $directory = $item instanceof Performance
        ? "submissions/{$competition->id}/stage-{$item->stage_id}"
        : "preselections/{$competition->id}";

    $stored = app(SubmissionService::class)->storeMedia(new UploadedFile($tmp, basename($source), 'video/mp4', null, true), $directory);
    $item->deleteMedia();
    $item->forceFill([...$stored, 'recorded_at' => null, 'media_origin' => null, 'media_metadata' => ['uploaded_at' => now()->toIso8601String()]])->save();
    OptimizeMedia::dispatch($item)->afterCommit();
    $replaced++;
}

echo $competition->name, " : {$replaced} vidéo(s) remplacée(s) (", count($participantIds), " artistes), optimisation en file d'attente\n";
if ($kept !== []) {
    echo 'Vraies prestations conservées : ', implode(', ', $kept), "\n";
}
