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
| Back-office organisateur | `/login` | email + mot de passe | `web` | owners, admins, staff des organisateurs |
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

Comptes de démonstration (mot de passe `password`) : les artistes (`07 1000000x`),
les jurés « Juge Didi B » (`05 20000000`) et « Juge Suspect 95 » (`05 20000001`),
les artistes en ligne (`01 3000000x`).
