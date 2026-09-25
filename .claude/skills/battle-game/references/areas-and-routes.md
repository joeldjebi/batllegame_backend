# Areas, guards, routes and authorization

## Areas

| Area | Routes file | Prefix / names | Guard | Login | Middleware |
|---|---|---|---|---|---|
| Public landing | `routes/web.php` | `/` (`home`, `LandingController`) | — | — | none (reads `auth('member')` for the CTA) |
| Organizer back-office | `routes/web.php` | `/tableau-de-bord` (`dashboard`), `organizers.*`, `login`, `organizers.signup` (`/creer-mon-espace`, guest or signed in, `config('organizers.self_signup')`), `password.edit|update` | `web` | email | `auth:web`, `organizer.area` (DenyPlatformAdmins), `fresh.password:password.edit` |
| Super-admin console | `routes/admin.php` | `config('admin.path')`, `admin.*` | `admin` | email | `auth:admin`, `platform.admin`, `admin.idle` |
| Jury portal | `routes/portals.php` | `/jury`, `jury.*` | `jury` | phone | `auth:jury`, `deny.admins`, `jury.access` |
| Artist portal | `routes/portals.php` | `/artiste`, `artist.*` | `member` | phone | `auth:member`, `deny.admins` |
| Public portal | `routes/portals.php` | `/vote`, `fan.*` (browse is public) | `member` | phone | `auth:member`, `deny.admins` for voting |
| Mobile API | `routes/api.php` | `/api`, `api.*` | `sanctum` | phone | `auth:sanctum`, `phone.verified` (votes), `password.changed` (judge) |

`bootstrap/app.php` registers the admin and portal route files (`then:`), the middleware aliases, and
`redirectGuestsTo` / `redirectUsersTo` closures mapping `admin.*`, `jury.*`, `artist.*`, `fan.*` to the
right login / home page. Add new areas there too.

`App\Support\Portal` describes the three portals (`key`, `guard`, `title`, `icon`, `home`, `canRegister`);
`Portal::current()` derives it from the route name prefix. `PortalAuthController` serves login /
registration / logout for all three.

## Route names (most used)

- Back-office: `organizers.show|update`, `organizers.members.store|update|destroy`,
  `organizers.competitions.index|store|show|update|status|destroy|duplicate` (index: `q`, `status`, `discipline`, `mode`, `sort` = recent|name|registration|participants, 12 per page),
  `organizers.competitions.phases.store|update|destroy|start`,
  `organizers.competitions.stages.update|open-submissions|open-voting`,
  `organizers.competitions.matches.update|open-voting|close|captations.store`,
  `organizers.competitions.performances.review`, `organizers.competitions.criteria.*`,
  `organizers.competitions.judges.store|destroy`, `organizers.competitions.participants.update`.
- There is **no** `organizers.store` in the back-office: organizers are created by `admin.organizers.store`.
- Admin: `admin.dashboard`, `admin.organizers.index|store|show|status` (`?creer=1` opens the create panel), `admin.competitions.index`,
  `admin.organizers.competitions.show`, `admin.users.index|show`, `admin.locations.*` (countries / cities / communes), `admin.login|logout`.
- Pre-selection (back-office): `organizers.competitions.preselection.update|rank|publish`,
  `organizers.competitions.preselection.entries.review` (`{entry}` via `$competition->entries()`).
- Payments / pre-selection (portals): `artist.competitions.payment` (GET/POST simulated checkout),
  `artist.competitions.preselection.submit`, `fan.competitions.preselection.like|unlike`,
  `jury.competitions.preselection`, `jury.competitions.preselection.scores.store`.
- Jury: `jury.dashboard`, `jury.competitions.show`, `jury.competitions.matches.show`,
  `jury.competitions.matches.scores.store`, `jury.password.edit|update`.
- Artist: `artist.dashboard`, `artist.competitions.register`, `artist.competitions.stages.submit`.
- Public: `fan.dashboard`, `fan.competitions.show`, `fan.competitions.matches.votes.store`,
  `fan.verification.show|send|verify`.
- API: see `docs/api.md` (mobile: `/feed`, `/me/participations/{slug}` journey, `/competitions/{slug}/preselection/entries`,
  `/judge/competitions/{slug}/preselection[/entries/{entry}]`).

Scoped bindings everywhere: `{organizer}` by slug (RouteKey attribute), `{competition}` by id in the
back-office/admin and by `:slug` in the API/portals; children (`{phase}`, `{stage}`, `{match}`,
`{criterion}`, `{judge}`, `{participant}`, `{performance}`, `{member}`) resolve through the parent relation.

## Authorization

| Ability | Where | Rule |
|---|---|---|
| `CompetitionPolicy::viewAny/view` | back-office reads | member of the organizer (else 404) |
| `create`, `update` (structure: phases, jury, criteria, settings) | owner, admin | not suspended; `update` refused once finished/cancelled |
| `changeStatus($status)` | owner, admin | valid transition; opening registrations needs a verified organizer |
| `delete` | owner | draft or cancelled only |
| `manageRegistrations` | owner, admin, staff | participants status / seed |
| `runMatches` | owner, admin, staff | stages, votes, match closing, submissions review, captations |
| `OrganizerPolicy::update` / `manageMembers` / `moderate` | owner+admin / owner / platform admin | |
| `ParticipantPolicy::register` | artist | registration open, not judge, not already in, capacity |
| `PublicVotePolicy::create` | voter | verified phone, public voting enabled and used by the phase, vote open, not a participant of the match, not a judge |
| `JuryScorePolicy::create` | judge | accepted judge of the competition (else 404), jury used by the phase, vote open |

Platform admins bypass organizer checks through `ChecksOrganizerAccess::before()` (back-office policies
only). Voting / scoring policies never bypass.
