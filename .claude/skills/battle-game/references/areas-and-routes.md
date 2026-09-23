# Areas, guards, routes and authorization

## Areas

| Area | Routes file | Prefix / names | Guard | Login | Middleware |
|---|---|---|---|---|---|
| Organizer back-office | `routes/web.php` | `/`, `organizers.*`, `dashboard`, `login` | `web` | email | `auth:web`, `organizer.area` (DenyPlatformAdmins) |
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
  `organizers.competitions.store|show|update|status|destroy`,
  `organizers.competitions.phases.store|update|destroy|start`,
  `organizers.competitions.stages.update|open-submissions|open-voting`,
  `organizers.competitions.matches.update|open-voting|close|captations.store`,
  `organizers.competitions.performances.review`, `organizers.competitions.criteria.*`,
  `organizers.competitions.judges.store|destroy`, `organizers.competitions.participants.update`.
- Admin: `admin.dashboard`, `admin.organizers.index|show|status`, `admin.competitions.index`,
  `admin.organizers.competitions.show`, `admin.users.index|show`, `admin.login|logout`.
- Jury: `jury.dashboard`, `jury.competitions.show`, `jury.competitions.matches.show`,
  `jury.competitions.matches.scores.store`, `jury.password.edit|update`.
- Artist: `artist.dashboard`, `artist.competitions.register`, `artist.competitions.stages.submit`.
- Public: `fan.dashboard`, `fan.competitions.show`, `fan.competitions.matches.votes.store`,
  `fan.verification.show|send|verify`.
- API: see `docs/api.md`.

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
