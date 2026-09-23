---
name: battle-game
description: Working on the Battle Game Laravel backend (music battle competitions - organizers back-office, super-admin console, jury/artist/public web portals, mobile REST API, brackets, groups, stages, submissions, scoring). Use for any feature, bug fix, test or review in this repository.
---

# Battle Game backend

Laravel 13 / PHP 8.3 / PostgreSQL / Pest 4 / Blade + Tailwind 4 + Alpine (Vite 8, Node 22).
Full documentation in `docs/` (French): start with `docs/README.md`, then `docs/architecture.md`
and `docs/regles-metier.md` before touching the competition engine.

## Conventions

- Code, identifiers and comments in **English**; UI strings, validation messages, docs and
  replies to the user in **French**.
- Enums (`app/Enums`) for every status/type: English case names, French values, `label()`,
  `tone()` (badge color) for statuses. Never compare raw strings when an enum exists.
- JSON columns are typed DTOs (`app/Data`, `JsonData` + `JsonDataCast`): add fields to
  `fromArray()` validation, `toArray()` and the constructor together.
- Match the surrounding style; run `./vendor/bin/pint` before finishing.
- Solid colors only in the UI (the owner refused gradients). Reuse `components/ui/*` and
  `components/bo/*`; layouts: `x-layouts.app` (back-office + admin), `x-layouts.portal`
  (jury/artist/public), `x-layouts.auth`.

## Non-negotiable rules

1. **Tenant isolation.** `organizer_id` lives only in `competitions` (and `organizer_members`).
   Child tables carry a denormalized `competition_id` managed by `InheritsCompetitionId`.
   Nested routes use `scopeBindings()`; never load a child by its id alone. Validate ids
   received in request bodies against the parent (match slots, competition criteria…).
   Back-office authorization goes through `CompetitionPolicy` (non-members get **404**).
2. **Explicit guards.** Areas: `web` (organizers, email), `admin` (super-admin, email,
   `routes/admin.php` under `config('admin.path')`), `jury` and `member` (portals, phone),
   `sanctum` (API). Always write `auth:<guard>` / `guest:<guard>`, never bare `auth`.
   The spatie role `platform-admin` lives on the `web` guard: use `PlatformRole::GUARD`.
   Platform admins are refused everywhere except their console.
3. **Business logic lives in services**, shared by the API and the web portals
   (`VotingService`, `JuryScoringService`, `RegistrationService`, `SubmissionService`,
   `Services/Competition/*`). Controllers authorize, validate input shape and delegate.
4. **Phase rules are frozen** once a phase starts (`Phase::FROZEN_ATTRIBUTES`).
5. **Stored scores are derived** (`match_participants`, `group_participants`) and must stay
   recomputable from `jury_scores` / `public_votes` (`scores:recompute`).
6. Voting requires a verified phone; one vote per user and match; a participant never votes
   in their own match; a judge is never a participant of the same competition.
7. Business-rule violations throw `CompetitionFlowException` (rendered as 422 JSON or a
   `flow` form error) with a French message; add a named constructor for new cases.

## Engine map

- Start a phase: `PhaseLauncher` → `GroupDrawService` | `BracketGenerator` → `StageBuilder`.
- Close a match: `MatchCloser::close()` / `forfeit()` → `MatchClosed` (after commit) →
  `AdvanceBracket` (→ `BracketAdvancer`, `PhaseProgress`) or `RecalculateGroupStandings` job.
- Stages: `StageService` (`schedule`, `openSubmissions`, `applyForfeits`, `openVoting`,
  `openMatchVoting`, `processDue` used by `stages:process`).
- Submissions: `SubmissionService` + `ProcessSubmission` job (`MediaInspector`/ffprobe).
- Scores: `MatchScoreCalculator` (jury normalized on 100, public vote share, phase weights).

## Workflow

1. Read the relevant docs section and the existing service/tests for the area.
2. Write or update Pest tests next to similar ones (`tests/Feature/...`, helpers in
   `tests/Feature/Services/helpers.php` and `tests/Feature/Stages/helpers.php`).
3. Run `php artisan test`, then **also on PostgreSQL**:
   `DB_CONNECTION=pgsql DB_DATABASE=battlegame_test DB_USERNAME=postgres DB_PASSWORD=root ./vendor/bin/pest`.
4. For UI changes: `npm run build` (Node 22: `PATH=/opt/homebrew/bin:$PATH` on the owner's Mac)
   and check the page renders (feature tests render Blade views; screenshots when layout matters).
5. Update `docs/` when behavior, routes, env variables or commands change.
6. Commit only when asked; end commit messages with the attribution line the harness provides.

## Gotchas learned the hard way

- PostgreSQL aborts the whole transaction on a unique violation: wrap inserts that may race
  in `DB::transaction()` (savepoint) and pre-check, as `VotingService` does.
- `MatchClosed` implements `ShouldDispatchAfterCommit`: do not wrap multi-step flows (seeders)
  in one transaction or listeners only fire at the very end.
- In tests `actingAs($user)` without a guard reuses the last default guard: pass the guard.
- `performances` are per stage (`stage_id`, one per participant) for submissions, or per match
  for on-site captations; `BattleMatch::publishedPerformances()` merges both.
- Group-phase completion recalculates standings synchronously in `PhaseProgress` (queued jobs
  may lag); a group phase never finishes the competition by itself.
- Local PHP upload limit is 2 MB: real media tests need `upload_max_filesize`/`post_max_size` raised.
