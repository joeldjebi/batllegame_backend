use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\SeedKind;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Competition\PhaseCreator;
use App\Services\Competition\PhaseLauncher;
use App\Services\Competition\StageService;
use App\Services\JudgeAccountService;
use App\Services\RegistrationService;
use App\Services\SubmissionService;
use Illuminate\Http\UploadedFile;

// Two test organizers, one running competition each (groups in progress, real videos).
// MODE=seed creates and submits; MODE=approve validates the processed videos. Idempotent.
// See scripts/test-data/README.md.
$country = Country::where('iso2', 'CI')->firstOrFail();
$password = '12345678';
$videoDir = rtrim((string) getenv('VIDEO_DIR'), '/');

$setups = [
    [
        'organizer' => 'Abidjan Urban Music', 'email' => 'orga.rap@test.ci', 'owner' => 'Koffi Yao', 'phone' => '0796100001',
        'competition' => 'Rap & Chant Battle Abidjan', 'discipline' => Discipline::Rap, 'artistPrefix' => '07981', 'judgePrefix' => '07971',
        'videos' => [13011, 13012, 13019, 13013, 36684, 47497, 36762, 39490, 443, 42841, 42838, 42842, 42839, 4743, 474, 478, 14756, 490, 3599, 50326],
        'artists' => ['Didi Flow', 'Maestro K', 'Lil Ivoire', 'Aya Soul', 'Black Mamba', 'Kiff Nah', 'Tchèkè Boy', 'Reine Aïcha', 'Sergio Mic', 'Nana Voix',
            'Bamba Kid', 'Fanta Soul', 'MC Lagune', 'Awa Diva', 'Ali Punchline', 'Grâce Mélodie', 'Yao Beat', 'Siaka Flow', 'Lady Kalou', 'Josué Rime'],
        'judges' => ['Juge Adjoua Bleu', 'Juge Serge Kassi'],
    ],
    [
        'organizer' => 'Yopougon Street Culture', 'email' => 'orga.danse@test.ci', 'owner' => 'Mariam Traoré', 'phone' => '0796200001',
        'competition' => 'Danse Urbaine Battle Yopougon', 'discipline' => Discipline::Other, 'artistPrefix' => '07982', 'judgePrefix' => '07972',
        'videos' => [452, 3622, 40369, 40360, 40362, 51304, 51295, 51300, 51297, 51317, 51313, 51314, 51301, 33907, 33897, 40621, 40624, 40625, 40619, 441],
        'artists' => ['Kalou Dance', 'Zoé Pop', 'Coupé Kid', 'Mimi Krump', 'Bboy Sidi', 'Lady Twerk', 'Dani Locking', 'Ivoire Crew', 'Ramses Flex', 'Nina Waack',
            'Tito Break', 'Soraya Groove', 'Kader Pop', 'Bella Swing', 'Junior Shuffle', 'Emma Vogue', 'Kouamé Step', 'Inès Hype', 'Moussa Spin', 'Clara Flow'],
        'judges' => ['Juge Fatou Diallo', 'Juge Hervé Konan'],
    ],
];

$account = function (string $national, string $name, ?string $email = null) use ($country, $password): User {
    $user = User::firstOrNew(['phone' => $country->toE164($national)]);
    if (! $user->exists) {
        $user->fill(['name' => $name, 'email' => $email, 'country_id' => $country->id, 'password' => $password]);
    }
    $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'must_change_password' => false, 'seed_kind' => $user->seed_kind ?? SeedKind::TestAccount])->save();

    return $user;
};

foreach ($setups as $setup) {
    $competition = Competition::where('name', $setup['competition'])->first();

    if (getenv('MODE') === 'approve') {
        $stage = $competition->phases()->where('position', 1)->firstOrFail()->stages()->firstOrFail();
        $approved = 0;
        foreach ($stage->performances()->where('status', PerformanceStatus::Pending)->get() as $performance) {
            app(SubmissionService::class)->approve($performance, $competition->creator);
            $approved++;
        }
        echo $competition->name, " : {$approved} validée(s) · ", $stage->performances()->get()->countBy(fn ($p) => $p->status->value)->toJson(),
            " · vote à partir de ", $stage->submission_deadline->format('H:i'), " UTC\n";

        continue;
    }

    // 1. Organizer (verified) and its owner (back-office login by email).
    $owner = $account($setup['phone'], $setup['owner'], $setup['email']);
    $organizer = Organizer::firstOrNew(['name' => $setup['organizer']]);
    if (! $organizer->exists) {
        $organizer->slug = Organizer::uniqueSlug($organizer->name);
        $organizer->forceFill(['status' => OrganizerStatus::Verified, 'verified_at' => now(), 'seed_kind' => SeedKind::TestAccount])->save();
        $organizer->users()->attach($owner, ['role' => OrganizerRole::Owner]);
    }

    // 2. Competition: online, free, groups then final bracket.
    if (! $competition) {
        $competition = new Competition([
            'name' => $setup['competition'], 'slug' => Competition::uniqueSlug($setup['competition']),
            'discipline' => $setup['discipline'], 'mode' => CompetitionMode::Online, 'status' => CompetitionStatus::Registration,
            'max_participants' => 20, 'entry_fee' => 0, 'currency' => 'XOF', 'registration_ends_at' => now()->addDays(10),
            'description' => "<div><strong>{$setup['competition']}</strong> : 20 artistes, 5 poules, vote du public et jury professionnel.</div>",
            'prizes' => [['rank' => '1er prix', 'reward' => '500 000 XOF'], ['rank' => 'Prix du public', 'reward' => 'Tournage d\'un clip']],
        ]);
        $competition->organizer()->associate($organizer);
        $competition->creator()->associate($owner);
        $competition->save();
        foreach ([['Technique', 10, 2], ['Originalité', 10, 1], ['Présence scénique', 10, 1]] as $i => [$name, $max, $weight]) {
            $competition->criteria()->create(['name' => $name, 'max_points' => $max, 'weight' => $weight, 'position' => $i]);
        }
    }

    // 3. Twenty artists, validated.
    foreach ($setup['artists'] as $i => $name) {
        $user = $account($setup['artistPrefix'].str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), $name);
        $participant = $competition->participants()->where('user_id', $user->id)->first()
            ?? app(RegistrationService::class)->register($user, $competition, $name);
        $participant->update(['status' => ParticipantStatus::Validated, 'seed' => $i + 1]);
    }

    // 4. Two judges.
    foreach ($setup['judges'] as $i => $name) {
        $national = $setup['judgePrefix'].str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
        $user = User::where('phone', $country->toE164($national))->first();
        if (! $user?->isJudgeOf($competition, acceptedOnly: false)) {
            app(JudgeAccountService::class)->assign($competition, $country, $national, $name);
        }
        $account($national, $name);
    }

    // 5. Groups phase (5 groups of 4, 2 qualified) + final bracket, started.
    $phase = $competition->phases()->where('position', 1)->first()
        ?? app(PhaseCreator::class)->create($competition, [
            'type' => 'poules', 'mode' => 'en_ligne', 'qualifiers_per_group' => 2,
            'rules' => ['group_count' => 5, 'expected_entrants' => 20, 'vote_mode' => 'mixte', 'jury_weight' => 50, 'public_weight' => 50,
                'draw_method' => 'seed', 'media_types' => ['video'], 'media_max_duration' => 180, 'media_max_size_mb' => 100],
        ])['phase'];
    if ($phase->fresh()->status->value === 'en_attente') {
        app(PhaseLauncher::class)->start($phase);
    }

    // 6. Submissions open until in 12 minutes, vote for 3 days.
    $stage = $phase->stages()->firstOrFail();
    $stages = app(StageService::class);
    if ($stage->status->value === 'en_attente') {
        $stages->schedule($stage, ['submission_deadline' => now()->addMinutes(12), 'voting_closes_at' => now()->addDays(3)]);
        $stages->openSubmissions($stage);
    }

    // 7. One real video per artist.
    $done = $stage->performances()->pluck('participant_id')->all();
    $participants = $competition->participants()->orderBy('seed')->get();
    $sent = 0;
    foreach ($participants as $i => $participant) {
        if (in_array($participant->id, $done, true)) {
            continue;
        }
        $source = "{$videoDir}/{$setup['videos'][$i]}.mp4";
        $tmp = sys_get_temp_dir()."/bg-up-{$participant->id}.mp4";
        copy($source, $tmp);
        app(SubmissionService::class)->submit($participant, $stage->fresh(), new UploadedFile($tmp, "prestation-{$setup['videos'][$i]}.mp4", 'video/mp4', null, true));
        $sent++;
    }

    echo $competition->name, " : ", $competition->participants()->count(), " artistes · ", $competition->judges()->count(), " jurés · ",
        $phase->groups()->count(), " poules · {$sent} vidéo(s) envoyée(s), ", $stage->performances()->count(), " au total · limite ",
        $stage->fresh()->submission_deadline->format('H:i'), " UTC\n";
}
