# Competition engine (`app/Services/Competition`)

## Starting a phase — `PhaseLauncher::start(Phase)`

Inside one transaction (phase row locked):
1. Refuses if the phase is not `Pending`, the competition is not `inscriptions`/`en_cours`, the effective
   mode is `mixte` (each phase must be online or on site), or the previous phase is not finished.
2. Entrants: first phase = `Validated` participants ordered by seed (nulls last) then id; later phases =
   `QualificationService::qualifiers(previous group phase)` (all group winners A, B, C…, then runners-up…).
   Groups need `group_count × max(2, qualifiers_per_group)` entrants; elimination needs 2.
3. `markAsStarted()` freezes the rules; competition moves to `en_cours`.
4. `GroupDrawService::draw()` (snake distribution, seeded or shuffled, `RoundRobinScheduler` circle method)
   or `BracketGenerator::generate()`.
5. `StageBuilder::build()` then `PhaseProgress::finishIfComplete()` (a bracket of byes may be decided already).

## Bracket — `BracketGenerator` + `BracketAdvancer`

- Size = next power of two; `SeedOrder::positions()` (1v8, 4v5, 2v7, 3v6…); missing seeds are byes.
- Matches are created from the end backwards so every `next_match_id` exists; every match gets 2 slots.
- Double elimination of size 2^k: winners rounds 1..k, losers rounds 1..2(k-1) (odd rounds pair survivors,
  even rounds receive winners-bracket dropouts, reversed every other round), grand final (winners champion
  slot 1 vs losers champion slot 2), optional reset match. k = 1: the loser goes straight to the grand final.
- `BracketAdvancer::advance(match)`: winner → next slot, loser → loser_next slot or `Eliminated`.
  `resolveIfReady(target)`: once all feeders are decided, 1 participant = walkover (Closed, winner),
  0 = void (Cancelled), and it recurses. Reset final: if the winners champion wins GF1, GF2 is cancelled.

## Groups — `GroupStandingsCalculator` + `QualificationService`

- Standings recomputed from closed group matches only; points from the phase rules; a draw needs
  `allow_draws`; a forfeit with no winner is a loss for both.
- Ranking: points, then tie breakers in order (head-to-head = mini-league among the tied, score diff,
  jury, public, seed), then participant id.
- `PhaseProgress::finishIfComplete()` (group phase): recalculates all groups synchronously, eliminates
  non-qualifiers, finishes the phase. A group phase never finishes the competition.

## Scores and closing — `MatchScoreCalculator`, `MatchCloser`

- Jury: per judge `Σ weight × score/max ÷ Σ weight × 100`, averaged over judges who scored; null if none.
- Public: vote share ×100, 50/50 with no votes. Final = jury × jury_weight% + public × public_weight%.
- `close(match, ?forcedWinnerId)`: locks, idempotent on Closed, needs 2 participants, stores slot scores,
  refuses if the jury is used and missing, decides the winner (draw only in groups with `allow_draws`,
  then tie breakers, then the forced winner or `CompetitionFlowException::unresolvedTie`), dispatches `MatchClosed`.
- `forfeit(match, ?winnerId)`: `is_forfeit = true`; null winner = Cancelled + both `Withdrawn` in elimination,
  Closed with no winner (double loss) in groups; dispatches `MatchClosed`.
- `storeScores()` is reused by `scores:recompute`.

## Stages — `StageBuilder`, `StageService`

- Groups: one stage « Poules ». Elimination: one stage per (bracket, round), named with `RoundLabel`
  (« Quarts de finale », « Tour perdants 2 », « Grande finale »…, prefixed « Principal · » in double elim).
- `schedule(stage, dates)` · `openSubmissions(stage)` (online, Pending, future deadline, all participants
  known; matches → `Submissions`) · `applyForfeits(stage)` (idempotent via `forfeits_applied_at`, missing or
  rejected submission) · `openVoting(stage)` (online: deadline passed, forfeits applied, nothing left to
  review; on site: participants known) · `openMatchVoting(match, ?closesAt)` (on site, generates the room
  code when `onsite_vote_code`) · `closeIfComplete(stage)` · `refreshPhase(phase)` (called by PhaseProgress,
  walkovers do not fire events) · `processDue()` (scheduler).

## Submissions — `SubmissionService`, `ProcessSubmission`

- `submit(participant, stage, file)`: one per participant and stage (replaces the file and resets review),
  stored on `config('media.disk')` under `submissions/{competition}/stage-{id}/`, status `Processing`,
  job dispatched after commit.
- `ProcessSubmission`: duration via `MediaInspector` (ffprobe; copies remote files locally), rejects above
  `media_max_duration` + 2 s, else `Pending` (review on) or `Approved`.
- `captation(match, participant, file, uploader)`: on-site recording, `Approved`.
- `approve()` / `reject(reason)`.

## Events, jobs, scheduler

- `MatchClosed` (ShouldDispatchAfterCommit) → `AdvanceBracket` (non-group) and
  `QueueGroupStandingsRecalculation` (group → `RecalculateGroupStandings`, unique per group).
- `routes/console.php`: `stages:process` and `matches:close-expired` every minute, `scores:recompute {slug}`.
- Production needs `queue:work` and `schedule:run`; tests use the sync queue.

## Payments and pre-selection

- `RegistrationService`: paid competition → `PaymentPending`, else `Competition::participantStatusAfterRegistration()`
  (Registered when a pre-selection exists or approval is required, else Validated).
- `PaymentService::simulate(participant, method, succeeds)` records a `Payment` (provider `simulation`) and, when paid,
  moves the participant out of `PaymentPending`. Replace this method when a real provider is plugged in.
- `PreselectionService`: `configure()` (freezes rules once started), `submit()` (Registered artists only, open period,
  one entry replaced on re-upload, `ProcessSubmission` job), `like()` / `unlike()` (one like per user and pre-selection,
  moved on a new like, `likes_count` refreshed), `score()` (judge, all criteria), `rank()` (jury normalized on 100 +
  likes relative to the top entry, weights, ties: jury, likes, participant id), `publish()` (closed, everything reviewed,
  jury complete if weighted → top N `Validated` + `selected`, others `NotSelected`).
- `PhaseLauncher` refuses to start a phase while a pre-selection is not published.
- `ProcessSubmission` handles any `Contracts\ReviewableMedia` (performances and pre-selection entries).
- Policies: `PreselectionSubmissionPolicy::like` (verified phone, open, published entry, not own, not judge) and `::score`.

## Shared services (API + portals)

`BackOfficeAccountService::findOrCreate(email, ?name, ?country, ?phone, context)` (existing by email, or a phone-only
mobile account gets the email, else a new account with a temporary password sent by SMS — returns `[user, ?password]`),
`VotingService::cast(voter, match, participantId, ?voteCode, ?deviceId, ?ip)` (participant of the match,
room code, one vote with savepoint, device limit — aborts 409/429), `JuryScoringService::store(judge, match,
input)` (all criteria, max points), `RegistrationService::register(user, competition, stageName)`,
`JudgeAccountService::assign(competition, country, phone, name)` (creates the account + SMS or reuses it),
`PhoneVerificationService` (6-digit code, 10 min, 5 attempts).
