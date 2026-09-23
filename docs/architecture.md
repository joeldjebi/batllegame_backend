# Architecture

## Stack

- **Laravel 13**, PHP 8.3+, **PostgreSQL** (SQLite en mémoire pour les tests rapides)
- **Sanctum** (tokens de l'API mobile), **spatie/laravel-permission** (rôle super-admin uniquement)
- Front : **Blade + Tailwind CSS 4 + Alpine.js** compilés par **Vite 8** (Node 22), icônes **blade-heroicons**
- Traductions françaises : `laravel-lang` (`lang/fr`), noms de champs métier dans `lang/fr/validation.php`
- Tests : **Pest 4**

## Organisation du code

```
app/
├── Data/                 DTO immuables des colonnes JSON (PhaseRules, CompetitionSettings) — castés et validés
├── Enums/                Tous les statuts et types (valeurs en français, label(), tone() pour les badges)
├── Events/MatchClosed    Émis après commit quand un match est décidé
├── Listeners/            AdvanceBracket (élimination), QueueGroupStandingsRecalculation (poules)
├── Jobs/                 RecalculateGroupStandings, ProcessSubmission (durée via ffprobe)
├── Exceptions/           CompetitionFlowException (règle métier → 422 / erreur de formulaire)
├── Http/
│   ├── Controllers/BackOffice   Back-office organisateur (Blade)
│   ├── Controllers/Admin        Console super-admin (Blade)
│   ├── Controllers/Portal       Portails jury / artiste / public (Blade)
│   ├── Controllers/Api          API mobile (JSON)
│   ├── Middleware/              Accès par espace (admin, jury, mot de passe, téléphone vérifié…)
│   └── Requests/, Resources/
├── Models/               Eloquent ; BattleMatch = table matches ("match" est un mot réservé PHP)
├── Policies/             CompetitionPolicy (cloisonnement), PublicVotePolicy, JuryScorePolicy, ParticipantPolicy
├── Services/
│   ├── Competition/      Moteur : PhaseLauncher, BracketGenerator, BracketAdvancer, GroupDrawService,
│   │                     GroupStandingsCalculator, QualificationService, MatchScoreCalculator,
│   │                     MatchCloser, StageBuilder, StageService, PhaseProgress
│   ├── VotingService, JuryScoringService, RegistrationService, SubmissionService (partagés API + web)
│   ├── JudgeAccountService, BackOfficeAccountService (comptes créés par un tiers), PhoneVerificationService
│   ├── PaymentService (frais d'inscription, paiement simulé), PreselectionService (soumissions, likes, notes, classement, publication)
│   ├── Media/            MediaInspector (ffprobe)
│   └── Sms/              SmsSender (LogSmsSender en local)
└── Support/Portal        Définition des 3 portails web
routes/
├── web.php               Page d'accueil (/) et back-office organisateur (/tableau-de-bord…)
├── admin.php             Console super-admin (préfixe config('admin.path'))
├── portals.php           /jury, /artiste, /vote
├── api.php               API mobile
└── console.php           Commandes et planification
resources/views/
├── components/ui/        Design system (button, badge, card, stat, table, input, modal, slide-over, tabs, confirm…)
├── components/bo/        Composants métier (bracket, match-card, standings, stage-panel, lifecycle…)
├── components/layouts/   app (back-office/admin), auth, portal
└── …                     Pages par espace
```

## Espaces, guards et sessions

Chaque espace a **son guard** et sa page de connexion (voir le tableau du README). Les
guards partagent la même session navigateur mais sont indépendants : se connecter au
portail artiste ne donne pas accès au back-office, et inversement.

- Les routes précisent **toujours** leur guard (`auth:web`, `auth:admin`, `auth:jury`,
  `auth:member`, `auth:sanctum`) : ne jamais utiliser `auth` seul.
- `DenyPlatformAdmins` (`deny.admins` / `organizer.area`) : le super-admin n'entre que dans sa console.
- `EnsurePlatformAdmin` + `AdminIdleTimeout` : console admin, déconnexion après inactivité.
- `EnsureJudgeAccess` (`jury.access`) : mot de passe provisoire à changer, juré affecté à au moins une compétition.
- `EnsurePasswordChanged` (`password.changed`) : même règle côté API.
- `EnsurePhoneIsVerified` (`phone.verified`) : vote API.
- `RequireFreshPassword` (`fresh.password:<route>`) : back-office, redirige vers le changement du mot de passe provisoire.
- Le rôle spatie `platform-admin` est stocké sur le guard **`web`** (`PlatformRole::GUARD`) :
  toujours passer ce guard aux vérifications de rôle.

## Cloisonnement entre organisateurs

1. `organizer_id` n'existe que dans `competitions` (et `organizer_members`). Les tables filles
   portent un `competition_id` dénormalisé, rempli et vérifié par le trait `InheritsCompetitionId`.
2. Routes imbriquées avec `scopeBindings()` : une compétition est cherchée via
   `$organizer->competitions()`, une phase via `$competition->phases()`, un match via
   `$competition->matches()`, une étape via `$competition->stages()`… **Jamais un enfant par son id seul.**
3. `CompetitionPolicy` : un non-membre reçoit un **404** (pas 403) ; rôles owner / admin / staff
   (matrice dans `OrganizerRole::permissions()`) ; organisateur suspendu = lecture seule.
4. Les identifiants reçus dans le corps des requêtes (participant, critère…) sont validés
   contre le match ou la compétition concernés.

## Moteur de compétition (vue d'ensemble)

```
PhaseLauncher::start(phase)
  ├─ fige les règles (Phase::markAsStarted)
  ├─ GroupDrawService (poules + round-robin)  ou  BracketGenerator (bracket complet + byes)
  └─ StageBuilder (1 étape par phase de poules, 1 étape par tour de bracket)

StageService (en ligne)      openSubmissions → applyForfeits (date limite) → openVoting → closeIfComplete
StageService (présentiel)    openMatchVoting (par match, durée, code de salle)

MatchCloser::close / forfeit ──► MatchClosed (après commit)
  ├─ AdvanceBracket → BracketAdvancer::advance → PhaseProgress::finishIfComplete
  └─ QueueGroupStandingsRecalculation → RecalculateGroupStandings (job) → PhaseProgress
```

Les scores stockés (`match_participants.*_score`, `group_participants`) sont dénormalisés et
**recalculables** depuis `jury_scores` et `public_votes` (`php artisan scores:recompute {slug}`).

## Indexation

Chaque liste, tableau de bord et recherche s'appuie sur un index (migration `add_listing_indexes`) :
statut + date pour les compétitions et organisateurs, `stage_id + status` pour les matchs et soumissions,
`competition_id + created_at` / `user_id + created_at` pour les votes, `user_id + status` pour les jurés…
Sur PostgreSQL, l'extension **pg_trgm** et des index GIN trigrammes accélèrent les recherches « contient »
(`whereLike`) sur les noms d'organisateurs et de compétitions, et sur le nom / email / téléphone des
utilisateurs (ignorés si l'extension ne peut pas être activée). Toute nouvelle requête de liste doit avoir
son index.

## Données JSON typées

- `competitions.settings` → `CompetitionSettings` (validation des inscriptions, vote public, résultats en direct,
  votes max par appareil, fuseau, validation des soumissions, code de salle).
- `phases.rules` → `PhaseRules` (passages, durée, mode de vote, pondérations, départages, points,
  nuls, nombre de poules, tirage, finale reset, types / durée / taille des médias).
- Les règles d'une phase sont **figées** dès qu'elle démarre (`PhaseRulesFrozenException`).
