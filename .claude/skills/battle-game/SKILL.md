---
name: battle-game
description: Everything needed to work on the Battle Game Laravel backend (music battle competitions) - organizer back-office, super-admin console, jury/artist/public web portals, mobile REST API, competition engine (groups, single/double elimination brackets, stages, online submissions, on-site live voting, forfeits, jury and public scoring). Use for any feature, bug fix, refactor, test, review or question about this repository.
---

# Battle Game backend

Music battle competitions (rap, chant, freestyle, slam, beatbox). Organizers run competitions from a
Blade back-office; artists register and submit performances; the public votes; judges score.
The Flutter mobile app is not built yet: web portals (`/jury`, `/artiste`, `/vote`) stand in for it and
share the same services as the REST API.

Stack: Laravel 13, PHP 8.3, PostgreSQL (SQLite in-memory for fast tests), Sanctum, spatie/permission
(super-admin role only), Pest 4, Blade + Tailwind CSS 4 + Alpine.js (Vite 8, **Node 22**), blade-heroicons,
laravel-lang (French).

## Load the right reference

| Task | Read |
|---|---|
| Tables, relations, enums (all values), DTO keys | [references/domain-model.md](references/domain-model.md) |
| Guards, areas, route names, policies and who can do what | [references/areas-and-routes.md](references/areas-and-routes.md) |
| Brackets, groups, stages, submissions, scoring, forfeits, events | [references/engine.md](references/engine.md) |
| Step-by-step changes (DTO field, enum case, back-office action, API endpoint, portal page, migration) | [references/recipes.md](references/recipes.md) |
| Views, design rules, component catalog and props | [references/ui.md](references/ui.md) |
| Test helpers, factories, patterns | [references/testing.md](references/testing.md) |
| Product decisions of the owner, local environment, accounts, backlog | [references/decisions.md](references/decisions.md) |

Human documentation (French) lives in `docs/` (`README`, `architecture`, `regles-metier`, `api`,
`exploitation`, `tests`). Keep both the docs and this skill up to date when behavior changes.

## Conventions

- Code, identifiers, comments in **English**. UI strings, validation/business messages, docs and replies
  to the owner in **French**.
- Every status/type is a PHP enum (`app/Enums`): English cases, French values, `label()`; status enums
  also `tone()` (implement `Contracts\HasBadge`). Compare enums, never raw strings.
- JSON columns are immutable, validated DTOs (`app/Data`): `PhaseRules`, `CompetitionSettings`.
- Controllers stay thin: authorize → validate shape → call a service → redirect/resource. Shared business
  logic lives in services used by both the API and the portals.
- Business refusals throw `CompetitionFlowException` named constructors (French message; rendered as a 422
  JSON or a `flow` toast). Validation errors use `ValidationException` with French messages.
- Blade: reuse `components/ui/*` and `components/bo/*`; **solid colors only — the owner refused gradients**;
  every screen works in dark mode; French dates via `translatedFormat()`.
- New migrations only (never edit a pushed one); explicit FK delete rules and indexes.
- Run `./vendor/bin/pint` before finishing.

## Non-negotiable rules

1. **Tenant isolation.** `organizer_id` only exists in `competitions` and `organizer_members`. Children carry
   a denormalized `competition_id` kept consistent by `InheritsCompetitionId` (mismatch = exception).
   Nested routes use `scopeBindings()`; a child is never loaded by its id alone; ids in request bodies are
   validated against the parent (`match slots`, `competition criteria`…). Back-office access goes through
   `CompetitionPolicy` — non-members get a **404**, never a 403.
2. **Explicit guards on every route**: `web` (organizers, email), `admin` (super-admin, email, secret
   `ADMIN_PATH`), `jury` (judges, phone), `member` (artists + public, phone), `sanctum` (API). Never write a
   bare `auth` / `guest`. The spatie role `platform-admin` is stored on the `web` guard: always pass
   `PlatformRole::GUARD` to role checks. Platform admins are refused everywhere except their console.
3. **Frozen rules**: a started phase cannot change `type`, `rules`, `qualifiers_per_group`.
4. **Derived scores**: `match_participants.*_score` and `group_participants` must stay recomputable from
   `jury_scores` and `public_votes` (`php artisan scores:recompute {slug}`).
5. **Voting integrity**: verified phone, one vote per user and match (unique index + savepoint), never in
   one's own match, judges do not vote, optional device limit, room code for on-site when enabled.
6. **Conflicts of interest**: a judge is never a participant of the same competition.
7. Organizer status gates writes: `en_attente` = drafts only, `suspendu` = read-only (no votes, no scores).
8. **Only the super-admin creates organizers** (with their owner). Organizers add managers and judges; accounts
   created by someone else go through `BackOfficeAccountService` / `JudgeAccountService` (temporary password by
   SMS, shown once in the flash message, `must_change_password` enforced by `fresh.password` / `jury.access`).
9. Every new listing / filter / sort needs an index (see `docs/architecture.md` › Indexation).
10. **Realtime (Socket.IO)**: a change users should see live goes through `App\Realtime\BroadcastModelChanges`
    (observer, add the model in `AppServiceProvider::registerRealtime()`), never through a direct emit. Public channels
    (`competition.{id}`, `live`) never carry counts (updates are bare signals, pages re-render per viewer); vote
    signals go there only with `settings.showLiveResults`. A page declares its
    channels with `<x-realtime :channels="[...]" />` and marks re-renderable areas `data-live="unique-key"` (never a
    region holding a Trix editor; forms are safe: dirty regions are skipped). Private channels must be checked in
    `RealtimeToken::allows()`.

## Workflow

1. Read the relevant reference(s) and the existing service + tests of the area before editing.
2. Implement in the service layer first; wire controllers/routes/views after.
3. Write or extend Pest tests next to similar ones; cover the happy path, the forbidden role, the foreign
   organizer (404) and the business refusal.
4. Run `php artisan test`, then the PostgreSQL suite (see `references/testing.md`) — both must be green.
5. UI changes: `npm run build` with Node 22 and render the page (tests render Blade; take screenshots when
   layout matters).
6. Update `docs/` and this skill (routes, env vars, commands, decisions).
7. Commit / push only when the owner asks; end commit messages with the attribution line the harness provides.
   Never commit `.env`; local credentials go through `SEED_*` variables.

## Gotchas learned the hard way

- PostgreSQL aborts the whole transaction on a unique violation (SQLite does not): wrap racy inserts in
  `DB::transaction()` (savepoint) and pre-check, like `VotingService` / `RegistrationService`.
- `MatchClosed` is `ShouldDispatchAfterCommit`: wrapping a multi-step flow (e.g. a seeder) in one transaction
  delays every listener to the end — phases then never finish mid-flow.
- In tests, `actingAs($user)` without a guard reuses the last default guard (e.g. `sanctum`): always pass it.
- zsh does not word-split unquoted variables: in shell loops use `${=var}`.
- Group standings are recalculated in a queued job; `PhaseProgress` recalculates synchronously before
  finishing a group phase. A group phase never finishes the competition (a next phase may be added).
- Walkovers/voids created by `BracketAdvancer` do not fire `MatchClosed`; stage completion is refreshed by
  `StageService::refreshPhase()` inside `PhaseProgress`.
- `performances`: a submission belongs to a stage (reused in all its matches); a captation also has a
  `match_id`. Use `BattleMatch::publishedPerformances()` to get what voters/judges may see.
- `@php use …; @endphp` is fine at the top of a view but not inside components' nested blocks — prefer FQCN.
- Never mix the inline `@php($x = …)` form with a `@php … @endphp` block in the same view: the compiler pairs them
  wrongly (ParseError « unexpected endforeach/endif »). Use blocks only once a view has one.
  Rendering is the only place it fails: after editing views, lint the compiled views —
  `php artisan view:cache && for f in storage/framework/views/*.php; do php -l "$f" | grep -v '^No syntax'; done; php artisan view:clear`.
- **Never put a Blade directive (`@js`, `@if`…) inside the attributes of a component tag** (`<x-ui.button x-on:click="…@js($x)…">`):
  it is not compiled, reaches Alpine verbatim and breaks the page's JS. Use `'{{ $x }}'` there. A test asserts
  `assertDontSee('@js(', false)` on the competition page.
- A valueless attribute on a **component** tag (`<x-ui.icon x-transition.scale />`) is rendered as
  `x-transition.scale="x-transition.scale"` and crashes Alpine: put `x-transition` / `x-collapse` on a plain HTML
  wrapper (`x-cloak` is harmless).
- Every form inside `x-ui.modal` / `x-ui.slide-over` carries `<input type="hidden" name="_form" value="<modal name>">`:
  the modal reopens on its own validation errors (whatever the failing field).
- Use `BattleMatch::votingNow()` (status vote **and** window not expired) for anything shown as « en direct »;
  the scheduler may not have closed expired matches yet.
- Do not name a Blade loop variable `$slot`/`$slots` inside components (reserved).
- Uploads stay « en traitement » until `ProcessSubmission` runs: locally start a worker (`composer dev` or
  `php artisan queue:work`), otherwise the organizer never gets the Valider / Rejeter buttons. Entries of unpaid artists
  cannot be approved and are left out of `PreselectionService::rank()`.
- Uploaded media are served from `/storage`: run `php artisan storage:link` once per machine (otherwise every player
  shows « Lecture impossible »).
- Mobile API contract (see `docs/api.md` › Cache, hors ligne et médias): every API GET gets an ETag/304
  (`ConditionalJsonResponse`), writes accept `Idempotency-Key` (`IdempotentRequests`, per user, 24 h); lists for the
  app are cursor-paginated (`/feed` via `App\Services\Feed`, pre-selection entries, judge pre-selection). Keep new
  app endpoints cursor-paginated and media payloads with `poster_url`/`width`/`height`.
- `Eloquent\Collection::merge()` re-keys by model id: `->toBase()` before merging collections keyed by strings.
- Local machine: Node 18 by default (use `/opt/homebrew/bin` Node 22), ffmpeg in /opt/homebrew/bin (not on PHP's PATH: tests find it, the app skips optimization unless FFMPEG_PATH is set),
  PHP upload limit 2 MB, SMS written to `storage/logs/laravel.log`, login throttling is 6/min per IP.

## Useful commands

```bash
php artisan migrate --seed                               # countries (CI active) + platform role
php artisan db:seed --class=LocalAccountsSeeder          # SA, organizer, judge, artist, fan from SEED_* (local only)
php artisan db:seed --class=DemoCompetitionSeeder        # demo competitions (local only), then re-run LocalAccountsSeeder
php artisan db:seed --class=LocationSeeder               # optional starter places (SA creates them in the console)
php artisan demo:purge [--accounts]                      # remove seeded rows (seed_kind), also from the SA dashboard; seeders must tag what they create
php artisan stages:process | matches:close-expired       # scheduler jobs (every minute in production)
php artisan scores:recompute {slug}                      # rebuild derived scores
php artisan media:optimize [--sync]                      # streaming optimization + posters of media uploaded before it existed
composer dev                                             # server + queue + logs + Vite + Socket.IO (npm run realtime)
php artisan route:list --except-vendor                   # 90+ routes across the 6 areas
```
