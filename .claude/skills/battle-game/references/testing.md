# Testing

Run: `php artisan test` (SQLite) **and** `DB_CONNECTION=pgsql DB_DATABASE=battlegame_test DB_USERNAME=postgres DB_PASSWORD=root ./vendor/bin/pest`.
Style: `./vendor/bin/pint` (`--test` to check). Current baseline: 145 tests green on both.

## Helpers
- `tests/Feature/Services/helpers.php`: `competitionWithParticipants(int $count)` → `[$competition, $participants]`
  (seeds 1..n, status inscriptions); `startPhase($competition, PhaseType, array $rules = [], ?int $qualifiersPerGroup)`
  (public vote by default); `playMatch($match, ?Participant $winner)` (one public vote, closes);
  `playPhaseByFavorites($phase, ?callable $pickWinner)`; `nextPlayableMatch($phase)`; `seedsOf($match)`.
- `tests/Feature/Stages/helpers.php`: `startedCompetition(CompetitionMode, $count = 4, PhaseType, $rules, $settings, $qualifiers)`
  → `['competition','phase','participants','owner','organizer']` (owner with email, phase started);
  `fakeMediaDuration(?float)`; `fakeVideo()`.
- Load with `require_once __DIR__.'/../Stages/helpers.php';` (functions are global).
- Factories: `User` (phone, verified, `unverified()`, `platformAdmin()`), `Organizer` (`pending()`, `suspended()`,
  `withMember(role, user)`), `Competition` (`draft()`, `inProgress()`), `Phase` (`groups()`, `doubleElimination()`,
  `started()`), `Participant`, `Judge` (Accepted), `Criterion`, `BattleMatch`, `Country::factory()->ivoryCoast()`.

## Patterns
- Isolation: request a child of organizer B through organizer A's URL → `assertNotFound()`.
- Roles: staff vs admin vs owner → `assertForbidden()`.
- Always pass the guard: `actingAs($u, 'web'|'admin'|'jury'|'member'|'sanctum')`.
- Time: `$this->travel(2)->days()` then run `$this->artisan('stages:process')` / `matches:close-expired`.
- Media: `Storage::fake('public')`, `config(['media.disk' => 'public'])`, `fakeMediaDuration(60)`.
- SMS: `$this->app->instance(SmsSender::class, Mockery::spy(SmsSender::class))`; capture codes with `andReturnUsing`.
- Queue is sync in tests (`phpunit.xml`); use `Queue::fake()` to assert dispatching.
- Blade pages: at least one test renders each page with realistic data (catches view errors).
