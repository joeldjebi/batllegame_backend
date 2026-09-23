# Domain model

## Tables and relations

```
countries ─< users ─< organizer_members >─ organizers ─< competitions
                 │                                          │
                 ├─< participants >─────────────────────────┤ (competition_id)
                 ├─< judges >───────────────────────────────┤
                 ├─< public_votes                           ├─< criteria
                 └─ model_has_roles (spatie, guard web)     ├─< phases ─< groups ─< group_participants >─ participants
                                                            │      └─< stages
                                                            └─< matches (competition_id, phase_id, group_id?, stage_id?)
                                                                   ├─< match_participants (slot 1|2, participant_id?, jury/public/final_score)
                                                                   ├─< jury_scores (judge, participant, criterion)
                                                                   ├─< public_votes (user, participant, device_id, ip)
                                                                   └─ next_match_id/slot, loser_next_match_id/slot (self)
performances (competition_id, stage_id, match_id?, participant_id, turn, media_*, source, status, reviewed_*)
```

| Table | Key columns / constraints |
|---|---|
| `users` | `phone` E.164 unique, `email` nullable unique (lowercased by a mutator), `phone_verified_at`, `must_change_password`, `country_id` |
| `countries` | `iso2`/`iso3` unique, `dial_code`, `phone_min_length`/`phone_max_length`, `is_active` (only CI active by default) |
| `organizers` | `slug` unique (route key), `status`, `verified_at`, `plan` |
| `organizer_members` | unique(`organizer_id`,`user_id`), `role` |
| `competitions` | `organizer_id` (restrict), `created_by` (null on delete), `slug` **globally** unique, `mode`, `status`, `settings` jsonb, `entry_fee` int + `currency`, soft deletes |
| `phases` | unique(`competition_id`,`position`), `type`, `mode` nullable (inherit), `qualifiers_per_group`, `rules` jsonb, `started_at` (freezes rules) |
| `stages` | unique(`phase_id`,`number`), `name`, `status`, `submission_deadline`, `voting_opens_at`, `voting_closes_at`, `forfeits_applied_at` |
| `participants` | unique(`competition_id`,`user_id`), `stage_name`, `seed` |
| `group_participants` | unique(`group_id`,`participant_id`), `points`,`wins`,`draws`,`losses`,`score_diff`,`rank` — **derived** |
| `matches` | `bracket` (null for groups), `round`, `bracket_position`, next/loser links, `status`, `winner_id` (null = draw or void), `is_forfeit`, `vote_code`, voting window |
| `match_participants` | unique(`match_id`,`slot`), scores **derived** |
| `jury_scores` | unique(`match_id`,`judge_id`,`participant_id`,`criterion_id`) |
| `public_votes` | unique(`match_id`,`user_id`), index (`match_id`,`device_id`) |
| `performances` | unique(`stage_id`,`participant_id`,`turn`); submission = `stage_id` only, captation = `stage_id` + `match_id` |

Delete rules: cascade from `competitions` down; restrict on organizers with competitions, on users with
participations/votes/judge roles, on judges/criteria already used in `jury_scores`.

## Models worth knowing

- `BattleMatch` (table `matches`): `slots()`, `participants()` (pivot `slot`), `stage()`, `nextMatch()`,
  `feederMatches()`, `publishedPerformances()`, `isVotingOpen()`, `isGroupMatch()`.
- `Phase`: `rules` (PhaseRules), `effectiveMode()`, `isFrozen()`, `markAsStarted()`, `nextPhase()`, `stages()`.
- `Stage`: `playableMatches()`, `participantIds()`, `participantFor(User)`, `isOnline()`, `acceptsSubmissions()`, `isDeadlinePassed()`.
- `Participant::currentStage()`, `User::roleIn()`, `hasOrganizerPermission()`, `isJudgeOf()`, `isParticipantOf()`, `isPlatformAdmin()`.
- `Competition`: `settings` (CompetitionSettings), `stages()` (hasManyThrough phases), `performances()`, `isRegistrationOpen()`, `uniqueSlug()`.
- Pivots with ids: `OrganizerMember` (`membership`), `GroupParticipant` (`standing`), `MatchParticipant` (`slot`).

## Enums (case = value)

| Enum | Values |
|---|---|
| OrganizerStatus | Pending=`en_attente`, Verified=`verifie`, Suspended=`suspendu` |
| OrganizerRole | Owner=`owner`, Admin=`admin`, Staff=`staff` (`permissions()`, `grants()`) |
| OrganizerPermission | `competitions.view`, `competitions.manage`, `competitions.delete`, `registrations.manage`, `matches.run`, `organizer.edit`, `members.manage` |
| OrganizerPlan | `free`, `pro`, `premium` |
| CompetitionStatus | Draft=`brouillon`, Registration=`inscriptions`, InProgress=`en_cours`, Finished=`terminee`, Cancelled=`annulee` (`nextStatuses()`, `canTransitionTo()`, `isPublic()`) |
| CompetitionMode | OnSite=`presentiel`, Online=`en_ligne`, Hybrid=`mixte` |
| Discipline | `rap`, `chant`, `freestyle`, `slam`, `beatbox`, `autre` (`icon()`) |
| PhaseType | Groups=`poules`, SingleElimination=`elimination`, DoubleElimination=`double_elimination` |
| PhaseStatus | Pending=`en_attente`, InProgress=`en_cours`, Finished=`terminee` |
| StageStatus | Pending=`en_attente`, Submissions=`soumissions`, Voting=`vote`, Closed=`cloture` |
| MatchStatus | Scheduled=`planifie`, Submissions=`soumissions`, Voting=`vote`, Closed=`cloture`, Cancelled=`annule` |
| BracketSide | Winners=`gagnants`, Losers=`perdants`, GrandFinal=`grande_finale` |
| ParticipantStatus | Registered=`inscrit`, Validated=`valide`, Eliminated=`elimine`, Withdrawn=`forfait`, Disqualified=`disqualifie` |
| JudgeStatus | Invited=`invite`, Accepted=`accepte`, Declined=`refuse` (judges are created Accepted) |
| PerformanceSource / Status | `soumission`, `captation` / Processing=`traitement`, Pending=`en_attente`, Approved=`validee`, Rejected=`rejetee` |
| MediaType | `video`, `audio` (`mimeTypes()`, `fromMime()`) |
| VoteMode | `jury`, `public`, `mixte` |
| TieBreaker | `jury`, `public`, `confrontation_directe`, `difference_score`, `seed` |
| GroupDrawMethod | Random=`tirage`, Seeded=`seed` |
| PlatformRole | Admin=`platform-admin`, const `GUARD = 'web'` |

Status enums implement `Contracts\HasBadge` (`label()` French, `tone()` among gray/blue/green/amber/red/violet/fuchsia).

## JSON DTOs (`app/Data`)

`PhaseRules` keys: `rounds`, `turn_duration`, `vote_mode`, `jury_weight`, `public_weight`, `tie_breakers[]`,
`points_win|draw|loss`, `allow_draws` (groups only), `group_count` (required for groups), `draw_method`,
`grand_final_reset` (double elim only), `media_types[]`, `media_max_duration` (s), `media_max_size_mb`.
Weights normalized to 100/0 for single-source modes, must sum to 100 in `mixte`.
`assertCompatibleWith(PhaseType)` checks type-dependent keys; `acceptedMimeTypes()`, `usesJury()`, `usesPublic()`.

`CompetitionSettings` keys: `registration_requires_approval`, `public_voting_enabled`, `show_live_results`,
`max_votes_per_device`, `timezone`, `submissions_require_approval`, `onsite_vote_code`.

Both extend `JsonData`: `fromArray()` validates (throws ValidationException), `toArray()`, `defaults()`,
`with([...])` returns a new validated instance. Never mutate; assign a new instance.
