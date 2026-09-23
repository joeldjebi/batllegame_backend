# Recipes

## Add a field to a JSON DTO (PhaseRules / CompetitionSettings)
1. Constructor property (camelCase), `fromArray()` rule with `sometimes` + default, `toArray()` key (snake_case).
2. Type-dependent constraint? add it to `PhaseRules::assertCompatibleWith()`.
3. Expose it in the forms (`competitions/show.blade.php`: phase slide-over `rules[...]` or settings toggles
   `settings[...]`), the API resources if the app needs it, and the French attribute name in `lang/fr/validation.php`.
4. Test in `tests/Feature/Models/PhaseRulesTest.php` (defaults, validation, round trip).
No migration: JSON columns; existing rows get the default.

## Add a status / enum case
Add the case (French value), `label()`, `tone()`; handle it in every `match` over the enum (PHP errors
otherwise); update transitions (`nextStatuses()`), policies and views filtering by status; doc tables.

## Add a back-office action on a competition child
1. Route in `routes/web.php` inside the `competitions/{competition}` group (scoped), named `organizers.competitions.<thing>.<action>`.
2. Controller in `Http/Controllers/BackOffice`: `$this->authorize('<ability>', $competition)` first,
   validate, delegate to a service, `back()->with('status', '… en français.')`.
3. Business refusals: throw a `CompetitionFlowException` named constructor (shown as a toast).
4. UI: button inside `x-ui.confirm` for anything destructive or irreversible.
5. Test: happy path, forbidden role (staff vs admin), foreign organizer URL → 404.

## Add a mobile API endpoint
Route in `routes/api.php` (inside `scopeBindings()`, `auth:sanctum`), reuse the service used by the portal,
JSON resource in `Http/Resources`, policy via `$this->authorize`, test with `actingAs($user, 'sanctum')`,
document in `docs/api.md`.

## Add a portal page (jury / artist / public)
Route in `routes/portals.php` under the right prefix, controller in `Http/Controllers/Portal/<Area>`,
view in `resources/views/portal/<area>/` using `x-layouts.portal`; current user = `auth('<guard>')->user()`
or `$request->user()`; add nav links in `components/layouts/portal.blade.php` if needed.

## Add a new area with its own login
New guard in `config/auth.php`, route file registered in `bootstrap/app.php`, mapping in
`redirectGuestsTo`/`redirectUsersTo` and `DenyPlatformAdmins`, `auth:<guard>` on every route.

## Add a migration
New file (never edit a pushed migration), explicit FK delete rules, indexes for every filter/scheduler query,
enum columns as `string(20)` with a default from the enum, JSON as `jsonb`. Run it on PostgreSQL and SQLite
(tests). Update `references/domain-model.md` and `docs/`.

## Change the scoring or the bracket
Touch only `Services/Competition/*`; keep stored values recomputable; extend `tests/Feature/Services/*`
(bracket sizes 2/4/5/6/8, single/double/reset, byes, forfeits) before changing behavior.
