<?php

namespace Database\Seeders;

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\JudgeStatus;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PhaseType;
use App\Enums\SeedKind;
use App\Models\BattleMatch;
use App\Models\City;
use App\Models\Competition;
use App\Models\Country;
use App\Models\JuryScore;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\PhaseLauncher;
use App\Services\Competition\StageService;
use App\Services\PreselectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Local demo data for the test organizer: a running competition (groups played,
 * bracket in progress), one open for registrations and a draft.
 * Run after LocalAccountsSeeder: php artisan db:seed --class=DemoCompetitionSeeder
 */
class DemoCompetitionSeeder extends Seeder
{
    private const array ARTISTS = [
        'Kaaris Junior', 'Lil Zoro', 'MC Gbaka', 'Nahomie K', 'Big Frère', 'Soum Bill Jr', 'Tchoko', 'Reine Flow',
        'Yodé Kid', 'Black K', 'Zouglou Boy', 'Miss Kpaflo',
    ];

    public function run(PhaseLauncher $launcher, MatchCloser $closer): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoCompetitionSeeder must not run in production.');
        }

        // No worker in a seeder: run the standings job inline so phases can finish.
        // No wrapping transaction either: MatchClosed listeners run after each commit.
        config(['queue.default' => 'sync']);

        $this->seed($launcher, $closer);
    }

    private function seed(PhaseLauncher $launcher, MatchCloser $closer): void
    {
        $organizer = Organizer::query()->where('slug', 'organisateur-test')->firstOrFail();
        $country = Country::query()->where('iso2', 'CI')->firstOrFail();

        $this->seedOnlineCompetition($organizer, $country, $launcher);
        $this->seedPreselection($organizer);

        if ($organizer->competitions()->where('slug', 'abidjan-rap-battle-2026')->exists()) {
            $this->command?->warn('Demo data already present.');

            return;
        }

        $users = collect(self::ARTISTS)->map(fn (string $name, int $i) => $this->demoUser(
            $country->toE164('07'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT)), $name, $country->id,
        ));

        // 1. Running competition: groups (ranking rounds, results published) then single elimination (in progress).
        $battle = $this->competition($organizer, 'Abidjan Rap Battle 2026', Discipline::Rap, CompetitionMode::Hybrid, CompetitionStatus::Registration, 16);

        $users->take(8)->each(fn (User $user, int $i) => $battle->participants()->create([
            'user_id' => $user->id, 'stage_name' => $user->name, 'seed' => $i + 1, 'status' => ParticipantStatus::Validated,
        ]));
        $users->slice(8, 3)->values()->each(function (User $user, int $i) use ($battle): void {
            $battle->participants()->create(['user_id' => $user->id, 'stage_name' => $user->name, 'status' => ParticipantStatus::Registered]);
        });

        $criteria = collect([['Flow', 10, 2], ['Lyrics', 10, 3], ['Présence scénique', 10, 1]])
            ->map(fn ($c, $i) => $battle->criteria()->create(['name' => $c[0], 'max_points' => $c[1], 'weight' => $c[2], 'position' => $i]));

        $judges = collect(['Juge Didi B', 'Juge Suspect 95'])->map(function (string $name, int $i) use ($battle, $country) {
            $user = $this->demoUser($country->toE164('05'.str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT)), $name, $country->id);

            return $battle->judges()->create(['user_id' => $user->id, 'status' => JudgeStatus::Accepted]);
        });

        $groups = new Phase([
            'type' => PhaseType::Groups, 'position' => 1, 'mode' => CompetitionMode::Online, 'qualifiers_per_group' => 2,
            'rules' => ['group_count' => 2, 'draw_method' => 'seed', 'vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40, 'rounds' => 2, 'turn_duration' => 90],
        ]);
        $battle->phases()->save($groups);

        $final = new Phase([
            'type' => PhaseType::SingleElimination, 'position' => 2, 'mode' => CompetitionMode::OnSite,
            'rules' => ['vote_mode' => 'mixte', 'jury_weight' => 70, 'public_weight' => 30, 'rounds' => 3, 'turn_duration' => 60],
        ]);
        $battle->phases()->save($final);

        $launcher->start($groups);
        $this->playAll($groups->fresh(), $judges, $criteria, $closer);
        // Groups are ranking rounds: the organizer publishes their results before the final phase.
        app(GroupResultsService::class)->publish($groups->fresh());

        $launcher->start($final);
        $this->playAll($final->fresh(), $judges, $criteria, $closer, limit: 1);

        // Leave one semi-final open for voting.
        $final->matches()->where('status', MatchStatus::Scheduled)
            ->whereDoesntHave('slots', fn ($q) => $q->whereNull('participant_id'))
            ->first()?->forceFill(['status' => MatchStatus::Voting, 'voting_opens_at' => now(), 'voting_closes_at' => now()->addHours(2)])->save();

        // 2. Open for registrations.
        $open = $this->competition($organizer, 'Voix d\'Or de Cocody', Discipline::Singing, CompetitionMode::OnSite, CompetitionStatus::Registration, 24, 5000);
        $users->slice(4, 6)->each(fn (User $user) => $open->participants()->create(['user_id' => $user->id, 'stage_name' => $user->name, 'status' => ParticipantStatus::Registered]));

        // 3. Draft.
        $this->competition($organizer, 'Freestyle Session Yopougon', Discipline::Freestyle, CompetitionMode::Online, CompetitionStatus::Draft, 32);
    }

    /**
     * Online competition with the first stage open for submissions.
     */
    private function seedOnlineCompetition(Organizer $organizer, Country $country, PhaseLauncher $launcher): void
    {
        if ($organizer->competitions()->where('name', 'Abidjan Talents en ligne')->exists()) {
            return;
        }

        $competition = $this->competition($organizer, 'Abidjan Talents en ligne', Discipline::Singing, CompetitionMode::Online, CompetitionStatus::Registration, 8);

        collect(['Awa Voice', 'Dj Kiff', 'Maman Soul', 'Petit Yodé'])->each(function (string $name, int $i) use ($competition, $country): void {
            $user = $this->demoUser($country->toE164('01'.str_pad((string) (30000000 + $i), 8, '0', STR_PAD_LEFT)), $name, $country->id);
            $competition->participants()->create(['user_id' => $user->id, 'stage_name' => $name, 'seed' => $i + 1, 'status' => ParticipantStatus::Validated]);
        });

        $phase = new Phase([
            'type' => PhaseType::SingleElimination, 'position' => 1,
            'rules' => ['vote_mode' => 'mixte', 'jury_weight' => 50, 'public_weight' => 50, 'media_types' => ['video', 'audio'], 'media_max_duration' => 120, 'media_max_size_mb' => 100],
        ]);
        $competition->phases()->save($phase);
        $launcher->start($phase);

        $stages = app(StageService::class);
        $first = $phase->stages()->first();
        $stages->schedule($first, ['submission_deadline' => now()->addDays(2), 'voting_closes_at' => now()->addDays(4), 'deliberation_minutes' => 1440]);
        $stages->openSubmissions($first);
    }

    /**
     * Open pre-selection on the paid singing competition (idempotent).
     */
    private function seedPreselection(Organizer $organizer): void
    {
        $competition = $organizer->competitions()->where('name', "Voix d'Or de Cocody")->first();

        if ($competition === null || $competition->preselection()->exists()) {
            return;
        }

        app(PreselectionService::class)->configure($competition, [
            'ends_at' => now()->addDays(5),
            'vote_ends_at' => now()->addDays(6),
            'deliberation_hours' => 24,
            'rules' => ['like_weight' => 40, 'jury_weight' => 60, 'selection_size' => 4, 'media_types' => ['video', 'audio'], 'media_max_duration' => 180, 'media_max_size_mb' => 100],
        ]);

        // Demo registrations count as paid (simulated payment record); they can now submit.
        // Real accounts registered on the same competition are left untouched: they must pay.
        $competition->participants()->whereIn('user_id', $this->demoArtistIds())
            ->where('status', ParticipantStatus::Validated)->update(['status' => ParticipantStatus::Registered]);
        $this->recordDemoPayments($competition);
    }

    /**
     * Simulated paid payment for every registered demo artist of a paid competition.
     */
    public function recordDemoPayments(Competition $competition): void
    {
        if (! $competition->requiresPayment()) {
            return;
        }

        $competition->participants()
            ->whereIn('user_id', $this->demoArtistIds())
            ->where('status', ParticipantStatus::Registered)
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Paid))
            ->get()
            ->each(function (Participant $participant) use ($competition): void {
                $payment = new Payment;
                $payment->forceFill([
                    'competition_id' => $competition->id, 'participant_id' => $participant->id, 'user_id' => $participant->user_id,
                    'amount' => $competition->entry_fee, 'currency' => $competition->currency,
                    'method' => PaymentMethod::OrangeMoney, 'provider' => 'simulation',
                    'reference' => 'BG-DEMO'.str_pad((string) $participant->id, 5, '0', STR_PAD_LEFT),
                    'status' => PaymentStatus::Paid, 'paid_at' => now(),
                ])->save();
            });
    }

    /**
     * Ids of the artists created by this seeder (never a real account).
     *
     * @return list<int>
     */
    private function demoArtistIds(): array
    {
        $country = Country::query()->where('iso2', 'CI')->firstOrFail();
        $phones = collect(array_keys(self::ARTISTS))
            ->map(fn (int $i) => $country->toE164('07'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT)));

        return User::query()->whereIn('phone', $phones)->pluck('id')->all();
    }

    /**
     * A generated account, tagged so the super-admin can remove the demo data.
     */
    private function demoUser(string $phone, string $name, int $countryId): User
    {
        $user = User::query()->firstOrNew(['phone' => $phone]);

        if (! $user->exists) {
            $user->fill(['name' => $name, 'country_id' => $countryId, 'password' => $this->password()]);
            $user->forceFill(['phone_verified_at' => now(), 'seed_kind' => SeedKind::Demo])->save();
        }

        return $user;
    }

    private function password(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('password');
    }

    /**
     * Demo description and rewards (also used to backfill existing demo competitions).
     *
     * @return array{description: string, prizes: list<array{rank: string, reward: string}>}
     */
    public static function presentation(string $name, Discipline $discipline): array
    {
        return [
            'description' => "<h1>{$name}</h1><div><strong>La scène {$discipline->label()} qui révèle les talents d'Abidjan.</strong> Des passages chronométrés, un jury de professionnels et <em>le vote du public</em> à chaque étape.</div>"
                .'<ul><li>Inscription en ligne, prestation vidéo ou audio</li><li>Présélection : likes du public et note du jury</li><li>Battles en direct jusqu\'à la grande finale</li></ul>'
                .'<blockquote>Fair-play obligatoire : tout contenu offensant entraîne la disqualification.</blockquote>',
            'prizes' => [
                ['rank' => '1er prix', 'reward' => '1 000 000 XOF + enregistrement d\'un single en studio'],
                ['rank' => '2e prix', 'reward' => '500 000 XOF'],
                ['rank' => '3e prix', 'reward' => '250 000 XOF'],
                ['rank' => 'Prix du public', 'reward' => 'Tournage d\'un clip vidéo'],
            ],
        ];
    }

    private function competition(Organizer $organizer, string $name, Discipline $discipline, CompetitionMode $mode, CompetitionStatus $status, int $max, int $fee = 0): Competition
    {
        $competition = new Competition([
            'name' => $name, 'slug' => Competition::uniqueSlug($name), 'discipline' => $discipline, 'mode' => $mode,
            'status' => $status, 'max_participants' => $max, 'entry_fee' => $fee, 'registration_ends_at' => now()->addWeeks(2),
            ...self::presentation($name, $discipline),
        ]);
        $competition->forceFill(['seed_kind' => SeedKind::Demo]);

        // Venue when the reference places exist (php artisan db:seed --class=LocationSeeder).
        $abidjan = City::query()->where('name', 'Abidjan')->first();
        $competition->city_id = $abidjan?->id;
        $competition->commune_id = $abidjan?->communes()->inRandomOrder()->value('id');
        $competition->organizer()->associate($organizer);
        $competition->creator()->associate($organizer->users()->first());
        $competition->save();

        return $competition;
    }

    private function playAll(Phase $phase, $judges, $criteria, MatchCloser $closer, ?int $limit = null): void
    {
        $played = 0;

        while ($limit === null || $played < $limit) {
            $match = $phase->matches()->where('status', MatchStatus::Scheduled)
                ->whereDoesntHave('slots', fn ($q) => $q->whereNull('participant_id'))
                ->orderBy('round')->orderBy('bracket_position')->first();

            if ($match === null) {
                return;
            }

            $this->simulate($match, $judges, $criteria);
            $closer->close($match);
            $played++;
        }
    }

    /**
     * Random but seed-biased jury scores and public votes.
     */
    private function simulate(BattleMatch $match, $judges, $criteria): void
    {
        $match->forceFill(['status' => MatchStatus::Voting])->save();

        foreach ($match->slots()->with('participant')->get() as $slot) {
            $strength = 10 - ($slot->participant->seed ?? 8);

            foreach ($judges as $judge) {
                foreach ($criteria as $criterion) {
                    JuryScore::create([
                        'match_id' => $match->id, 'judge_id' => $judge->id, 'participant_id' => $slot->participant_id,
                        'criterion_id' => $criterion->id, 'score' => max(1, min(10, $strength + random_int(-2, 3) * 0.5 + 1)),
                    ]);
                }
            }

            foreach (range(1, random_int(3, 6) + $strength) as $i) {
                $voter = $this->demoUser('+22501'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT), 'Fan '.$i, $slot->participant->user->country_id);
                $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $slot->participant_id]);
                $vote->forceFill(['user_id' => $voter->id, 'device_id' => 'demo-'.$voter->id]);
                $vote->save();
            }
        }
    }
}
