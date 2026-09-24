# Battle Game — Documentation technique

Plateforme de compétitions de music battle (rap, chant, freestyle, slam…) : les
organisateurs pilotent leurs compétitions depuis un back-office web, les artistes
s'inscrivent et envoient leurs prestations, le public vote et le jury note.

| Document | Contenu |
|---|---|
| [architecture.md](architecture.md) | Stack, organisation du code, espaces et guards, cloisonnement, événements |
| [regles-metier.md](regles-metier.md) | Domaine : organisateurs, compétitions, phases, étapes, modes, scores, forfaits |
| [api.md](api.md) | API REST de l'application mobile (Sanctum) |
| [exploitation.md](exploitation.md) | Installation, variables d'environnement, commandes, mise en production |
| [tests.md](tests.md) | Lancer et écrire les tests, pièges connus |

Un agent IA qui reprend le projet doit aussi lire le skill
[`.claude/skills/battle-game/SKILL.md`](../.claude/skills/battle-game/SKILL.md).

## Les espaces et leurs URL de connexion

| Espace | URL | Identifiant | Guard | Pour qui |
|---|---|---|---|---|
| Page d'accueil publique | `/` | — | — | tout le monde : hero animé, votes en direct, compétitions, création de compte, liens de connexion |
| Back-office organisateur | `/login` → `/tableau-de-bord` | email + mot de passe | `web` | owners, admins, staff des organisateurs |
| Console super-admin | `/{ADMIN_PATH}/login` | email + mot de passe | `admin` | propriétaire de la plateforme |
| Portail jury | `/jury/login` | téléphone + mot de passe | `jury` | jurés (comptes créés par les organisateurs) |
| Portail artiste | `/artiste/login` | téléphone + mot de passe | `member` | participants |
| Portail public | `/vote/login` (consultation libre sur `/vote`) | téléphone + mot de passe | `member` | spectateurs qui votent |
| API mobile | `/api/*` | téléphone + mot de passe → token | `sanctum` | future application Flutter |

Les portails jury / artiste / public sont la version web de l'application mobile en
attendant qu'elle soit développée ; ils utilisent les mêmes services que l'API.

## Démarrage rapide (local)

```bash
nvm use                         # Node 22 (Vite 8 ne supporte pas Node 18)
composer install && npm install
cp .env.example .env && php artisan key:generate
# Renseigner DB_* (PostgreSQL), ADMIN_PATH et SEED_* dans .env
php artisan migrate --seed
php artisan db:seed --class=LocalAccountsSeeder     # super-admin + organisateur test
php artisan db:seed --class=DemoCompetitionSeeder   # données de démonstration (optionnel)
php artisan storage:link
npm run build                   # ou npm run dev
php artisan serve               # + php artisan queue:work et php artisan schedule:work
```

## Comptes de test (local)

Créés par `LocalAccountsSeeder` à partir des variables `SEED_*` du `.env` (identifiants et mots de passe
uniquement dans le `.env`, jamais dans le dépôt) :

| Espace | URL | Identifiant |
|---|---|---|
| Super-admin | `/{ADMIN_PATH}/login` | `SEED_ADMIN_EMAIL` |
| Organisateur | `/login` | `SEED_ORGANIZER_EMAIL` |
| Jury | `/jury/login` | `SEED_JUDGE_PHONE` (juré de toutes les compétitions de l'organisateur test) |
| Artiste | `/artiste/login` | `SEED_ARTIST_PHONE` |
| Public | `/vote/login` | `SEED_FAN_PHONE` (numéro déjà vérifié) |

Code de vérification SMS en local : **`123456`** (affiché sur la page de vérification en mode test).

Relancer `LocalAccountsSeeder` après `DemoCompetitionSeeder` pour rattacher le juré et l'artiste aux
compétitions de démonstration. Comptes de `DemoCompetitionSeeder` (mot de passe `password`) : artistes
`07 1000000x` (Abidjan Rap Battle) et `01 3000000x` (Abidjan Talents en ligne, soumissions ouvertes),
jurés `05 20000000` et `05 20000001`.

**Vider les données de test** : console super-admin → Vue d'ensemble → carte « Données de test » →
« Vider les données de test » (saisir `VIDER`), ou `php artisan demo:purge [--accounts] [--force]`.
Les seeders marquent ce qu'ils créent (`seed_kind` : `demo` pour les compétitions et comptes générés,
`compte_test` pour l'organisateur test et les comptes `SEED_*`). Supprimé : les compétitions de démo avec
tout leur contenu (inscriptions, paiements simulés, matchs, votes, likes, notes, fichiers médias) et les comptes
générés ; avec l'option, aussi l'organisateur test (et toutes ses compétitions) et les comptes de test.
Jamais supprimés : les super-admins, les comptes réels, et un compte marqué encore utilisé dans une vraie
compétition ou chez un vrai organisateur.
