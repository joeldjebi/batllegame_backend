# Tests

```bash
php artisan test                         # SQLite en mémoire (rapide)
DB_CONNECTION=pgsql DB_DATABASE=battlegame_test DB_USERNAME=postgres DB_PASSWORD=root ./vendor/bin/pest
./vendor/bin/pint --test                 # style
```

**Toujours** relancer la suite sur PostgreSQL avant de livrer : SQLite masque certains
comportements (une violation d'unicité annule toute la transaction PostgreSQL).

## Organisation

| Dossier | Couvre |
|---|---|
| `tests/Feature/Models` | DTO, casts, règles figées, `competition_id` dénormalisé |
| `tests/Feature/BackOffice` | Cloisonnement entre organisateurs, rôles, pages Blade, comptes managers, page d'accueil |
| `tests/Feature/Admin` | Console super-admin, connexion séparée |
| `tests/Feature/Api` | Authentification, vote, notation, inscription |
| `tests/Feature/Services` | Bracket (byes, double élimination, reset), scores, poules, qualification |
| `tests/Feature/Stages` | Étapes, soumissions, forfaits, déroulé en ligne et présentiel |
| `tests/Feature/Jury`, `tests/Feature/Portals` | Comptes jurés, portails web |

Helpers : `tests/Feature/Services/helpers.php` (`competitionWithParticipants`, `startPhase`, `playMatch`,
`playPhaseByFavorites`) et `tests/Feature/Stages/helpers.php` (`startedCompetition`, `fakeMediaDuration`, `fakeVideo`).

## Pièges connus

- `actingAs($user)` sans guard réutilise le **dernier guard par défaut** : toujours préciser
  `'web'`, `'admin'`, `'jury'`, `'member'` ou `'sanctum'` quand un test mélange les espaces.
- `MatchClosed` est émis **après commit** : un traitement enveloppé dans une transaction ne déclenche
  les listeners qu'à la fin (ne pas envelopper le seeder de démo dans une transaction).
- Les vues sont rendues sans assets (`withoutVite()` dans `TestCase`).
- Les médias : `Storage::fake('public')`, `config(['media.disk' => 'public'])`, `fakeMediaDuration()`.
