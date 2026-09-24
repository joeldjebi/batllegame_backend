<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\ParticipantStatus;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PreselectionSubmission;
use App\Models\User;
use App\Services\PreselectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * A competition open for registrations with an open pre-selection and $count registered artists.
 *
 * @param  array<string, mixed>  $rules
 * @return array{competition: Competition, owner: User, organizer: Organizer, artists: Collection<int, Participant>}
 */
function competitionWithPreselection(int $count = 3, array $rules = [], array $settings = ['submissions_require_approval' => false], int $fee = 0): array
{
    $owner = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create(['status' => CompetitionStatus::Registration, 'entry_fee' => $fee, 'settings' => ['registration_requires_approval' => false, ...$settings]]);

    app(PreselectionService::class)->configure($competition, [
        'ends_at' => now()->addDay(),
        'rules' => ['like_weight' => 40, 'jury_weight' => 60, 'selection_size' => 2, ...$rules],
    ]);

    $artists = collect(range(1, $count))->map(fn (int $i) => Participant::factory()->for($competition)->create([
        'stage_name' => "Artiste {$i}", 'status' => ParticipantStatus::Registered,
    ]));

    return compact('competition', 'owner', 'organizer', 'artists');
}

function preselectionEntry(Participant $artist): PreselectionSubmission
{
    return app(PreselectionService::class)->submit($artist, UploadedFile::fake()->create('take.mp4', 300, 'video/mp4'))->fresh();
}

function likeAs(PreselectionSubmission $entry, ?User $user = null): User
{
    $user ??= User::factory()->create();
    app(PreselectionService::class)->like($user, $entry);

    return $user;
}
