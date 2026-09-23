# Battle Game — guide pour les agents

Backend Laravel 13 de Battle Game (compétitions de music battle) : back-office organisateur,
console super-admin, portails web jury / artiste / public, API mobile.

- **Lire d'abord** le skill [`.claude/skills/battle-game/SKILL.md`](.claude/skills/battle-game/SKILL.md)
  (règles non négociables, carte du moteur, pièges) puis [`docs/README.md`](docs/README.md).
- Code et commentaires en anglais ; interface, messages, documentation et réponses en français.
- Tests : `php artisan test` **et** la suite sur PostgreSQL (voir `docs/tests.md`) ; style : `./vendor/bin/pint`.
- Front : Node 22 (`.nvmrc`), `npm run build`.
- Ne jamais commiter `.env` ; les identifiants locaux passent par les variables `SEED_*`.
