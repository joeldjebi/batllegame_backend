<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\PhaseLauncher;
use App\Services\Media\MediaInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * A running competition of the given mode with $count seeded participants,
 * and its first phase started.
 *
 * @param  array<string, mixed>  $rules
 * @param  array<string, mixed>  $settings
 * @return array{competition: Competition, phase: Phase, participants: Collection<int, Participant>, owner: User, organizer: Organizer}
 */
function startedCompetition(CompetitionMode $mode, int $count = 4, PhaseType $type = PhaseType::SingleElimination, array $rules = [], array $settings = [], ?int $qualifiers = null): array
{
    $owner = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create([
        'mode' => $mode,
        'status' => CompetitionStatus::Registration,
        'settings' => $settings,
    ]);
    $participants = collect(range(1, $count))->map(fn (int $seed) => Participant::factory()->for($competition)->create(['seed' => $seed, 'stage_name' => "Seed {$seed}"]));

    $phase = Phase::factory()->for($competition)->create([
        'type' => $type,
        'qualifiers_per_group' => $qualifiers,
        'rules' => ['vote_mode' => 'public', ...$rules],
    ]);
    $phase = app(PhaseLauncher::class)->start($phase);

    return compact('competition', 'phase', 'participants', 'owner', 'organizer');
}

/**
 * Pretend every media lasts $seconds (no ffprobe in tests).
 */
function fakeMediaDuration(?float $seconds): void
{
    app()->instance(MediaInspector::class, new class($seconds) implements MediaInspector
    {
        public function __construct(private ?float $seconds) {}

        public function duration(string $absolutePath): ?float
        {
            return $this->seconds;
        }
    });
}

function fakeVideo(string $name = 'battle.mp4', int $kilobytes = 500): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'video/mp4');
}
