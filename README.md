# Battle Game — backend

Plateforme de compétitions de music battle (rap, chant, freestyle…) : back-office des
organisateurs, console super-admin, portails web jury / artiste / public et API REST pour
l'application mobile Flutter.

- Stack : Laravel 13, PHP 8.3, PostgreSQL, Sanctum, Pest, Blade + Tailwind 4 + Alpine (Vite, Node 22).
- Documentation : [`docs/`](docs/README.md) — architecture, règles métier, API, exploitation, tests.
- Agents IA : [`CLAUDE.md`](CLAUDE.md) / [`AGENTS.md`](AGENTS.md) et le skill
  [`.claude/skills/battle-game/SKILL.md`](.claude/skills/battle-game/SKILL.md).

```bash
composer install && npm install && npm run build
php artisan migrate --seed
php artisan test
```
