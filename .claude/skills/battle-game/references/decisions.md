# Owner decisions, local environment, backlog

## Product decisions (confirmed by the owner, 2026-09)
- Web accounts (super-admin, organizers) log in with **email + password**; judges, artists and the public
  with **phone + password** (dial codes from active `countries`, Côte d'Ivoire by default).
- The super-admin has **separate login URL and guard**; refused everywhere else; read-only global view
  (organizers, competitions, users) plus verify / suspend, and **he alone creates organizers** (with their owner).
- Organizers **create their managers** (new accounts with a temporary password) and their judges; an organizer
  can never create another organizer.
- Public landing page at `/` to follow competitions and let fans / artists sign up or log in; the organizer
  back-office dashboard lives at `/tableau-de-bord`.
- Data must display fast: every listing / search is indexed.
- Organizer roles: staff = registrations + matches + submissions; admin = + configuration; owner = + members + delete.
- Suspended organizer: readable, no writes, no votes. Pending organizer: drafts only.
- Judges are **created by the organizer** per competition (SMS with a temporary password to change);
  a judge only sees assigned competitions; the jury uses the app/portal, not the back-office.
- One submission per participant **per stage** (stage = group phase, or one bracket round); organizer defines
  media types, max duration and size; optional review before publication (default on).
- Missing submission at the deadline = **forfeit**. Byes go to the best seeds. Qualified players cross groups (1A–2B).
- Public vote **per match**; no votes = 50/50; missing judge scores = mean of judges who scored; perfect tie =
  organizer decides. Grand final reset optional.
- On-site voting: opened live per match; **option C**: organizer chooses per competition whether a room code
  restricts voting to people present.
- Mixed competitions: each phase is online or on site.
- UI: premium, **no gradients**, French.
- Until the Flutter app exists: web portals `/jury`, `/artiste`, `/vote` with distinct login URLs.

## Local environment (owner's machine)
- Repo `backend/` → GitHub `joeldjebi/batllegame_backend` (branch `main`); commit/push only when asked.
- PostgreSQL local: db `battlegame_db`, tests `battlegame_test`, user `postgres` / `root`.
- Admin console path: `ADMIN_PATH` in `.env` (random, never commit it).
- Test accounts come from `LocalAccountsSeeder`, credentials in the `SEED_*` variables of `.env`
  (never committed): super-admin, organizer (emails), judge / artist / fan (phones). `DemoCompetitionSeeder`
  accounts use the password `password`. Ask the owner or read `.env`, never hard-code credentials.
- Node 18 is the default; use Homebrew Node 22 (`/opt/homebrew/bin`). No ffmpeg locally. PHP upload limit 2 MB.
- SMS are written to `storage/logs/laravel.log` (`LogSmsSender`). The SMS verification code is **123456** outside
  production (`services.phone_verification.fixed_code`, env `PHONE_VERIFICATION_CODE`), random in production.

## Backlog / not done yet
- Real SMS provider; payment of entry fees (`entry_fee` is informative only).
- Flutter app (API ready: `docs/api.md`); push notifications; real-time (Reverb) for live votes.
- S3 storage + ffmpeg in production; video transcoding/streaming.
- Email delivery of temporary passwords (today SMS + flash message).
- Public results pages / rankings export; audit log of organizer actions.
