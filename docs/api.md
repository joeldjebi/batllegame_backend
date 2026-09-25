# API mobile (`/api`)

Authentification **Sanctum** par token : `Authorization: Bearer {token}`. Réponses JSON,
erreurs de validation en 422 (messages en français), règles métier en 422 `{"message": …}`.

## Cache, hors ligne et médias (app mobile)

- **Revalidation** : chaque lecture (`GET`) réussie porte un `ETag` faible et `Cache-Control: private, no-cache`.
  L'app renvoie la valeur dans `If-None-Match` : réponse **304 vide** si rien n'a changé (garder la copie locale).
  Les liens signés des médias restent identiques pendant la moitié de leur validité, l'ETag aussi.
- **Idempotence** : toute écriture peut porter `Idempotency-Key: <8 à 100 caractères [A-Za-z0-9_-]>` (un UUID par
  action de la file hors ligne). Rejouée avec la même clé (24 h, par utilisateur), la réponse enregistrée est renvoyée
  avec `Idempotent-Replayed: true` au lieu d'agir deux fois (vote, like, note, envoi) ; même clé pour une autre requête
  → 422 ; requête identique encore en cours → 409 ; les erreurs 5xx ne sont pas mémorisées (on peut réessayer).
- **Médias** (`media` dans les réponses) : `type`, `url` (lien signé), `poster_url` (miniature JPEG, `null` tant que
  la vidéo n'est pas optimisée), `width` / `height` (taille d'affichage, rotation appliquée), `duration_seconds`.
  Les vidéos sont optimisées pour le streaming (lecture immédiate) sans perte quand c'est possible.
- Mettre en cache les vidéos par **identifiant** de prestation, jamais par URL (les liens signés changent).
Les compétitions sont adressées par **slug** ; les ressources imbriquées (`matches`, `stages`)
sont toujours résolues à travers leur compétition.

## Authentification

| Méthode | URL | Corps | Notes |
|---|---|---|---|
| GET | `/countries` | | Pays actifs (indicatifs pour tous les champs téléphone) |
| GET | `/locations` | | Lieux à proposer : `[{id, name, flag, cities: [{id, name, communes: [{id, name}]}]}]` (actifs) |
| POST | `/auth/register` | `name, country_id, phone, password, password_confirmation, email?, city_id?, commune_id?` | Envoie un code SMS ; `user.location` = `{city, commune, label}` ou `null` |
| POST | `/auth/login` | `country_id, phone, password, device_name?` | → `{token, user}` ; super-admin refusé |
| POST | `/auth/logout` | | Révoque le token |
| POST | `/auth/profile` | multipart `name, email?, city_id?, commune_id?, photo?` (image ≤ 5 Mo) ou `remove_photo=1` | Profil et photo (carré 512 px) → utilisateur avec `avatar_url` |
| GET | `/auth/me` | | Inclut `avatar_url`, `phone_verified`, `must_change_password`, `is_judge` |
| POST | `/auth/phone/send-code` · `/auth/phone/verify` | `code` | Vérification du téléphone (obligatoire pour voter) |
| POST | `/auth/password` | `current_password, password, password_confirmation` | Obligatoire pour les jurés créés par un organisateur |

## Public

| Méthode | URL | Notes |
|---|---|---|
| GET | `/feed` | Fil « Pour toi » : prestations publiques les plus récentes (présélection validée + prestations d'étapes / captations validées), pagination par curseur `cursor` (`meta.next_cursor`, `null` à la fin), `limit` (10, max 30), filtres `competition` (slug), `discipline`. Élément : `key` (`preselection-12` / `performance-34`), `kind`, `id`, `published_at`, `media`, `artist` (`participant_id`, `stage_name`, `avatar_url`), `competition` (`slug`, `name`, `discipline`, `status`), `context` (`label`, `entry_id`, `match_id`, `stage_id`), `likes` (présélection : `enabled`, `open`, `liked`, `count` selon les règles de visibilité) ou `vote` (`match_id`, `open`, `closes_at`), `share_url`. Jeton facultatif (état du like) |

| Méthode | URL | Notes |
|---|---|---|
| GET | `/competitions` | Filtres `status`, `discipline` ; les brouillons ne sont jamais exposés |
| GET | `/competitions/{slug}` | Phases, critères, `description` (HTML nettoyé), `prizes` (`[{rank, reward}]`), `location` (lieu de la compétition), `organizer.location`, `schedule` (`[{title, date, details, auto}]` : étapes automatiques + étapes ajoutées par l'organisateur (`auto: false`), date locale `Y-m-d\TH:i` dans le fuseau de la compétition), `regulations` (HTML nettoyé) |
| GET | `/competitions/{slug}/matches` | Matchs d'une phase (`phase` = id, défaut : la première) pour les vues poules / tableau, sans médias : `phase` (`id`, `position`, `type`, `status`), `data[]` (`id`, `is_group`, `title`, `group`, `bracket`, `round`, `bracket_position`, `status`, `stage`, `voting_open`, `voting_closes_at`, `winner_id`, `slots[]` : `participant_id`, `stage_name`, `avatar_url`, `final_score`, `rank`, `is_forfeit` — scores et vainqueur seulement une fois publics) |
| GET | `/competitions/{slug}/matches/{id}` | `share_url`, photo des artistes (`slots[].participant.avatar_url`), `is_group` (poule : tous les artistes de la poule dans `slots`, `title` « Poule A »), slots avec `rank` et `is_forfeit`, scores (après clôture — pour une poule après la publication des résultats de la phase — ou si résultats en direct), `media` publiés, `vote_code_required`, étape, `voting_open`, `deliberation_ends_at`, `jury_scoring_open` |
| POST | `/competitions/{slug}/matches/{id}/votes` | `participant_id`, `vote_code` (présentiel avec code) ; en-tête `X-Device-Id` ; 409 si déjà voté (poules : **un seul vote par phase**, 403 pour les artistes de la phase) |

## Artiste

| Méthode | URL | Notes |
|---|---|---|
| POST | `/competitions/{slug}/registrations` | `stage_name` |
| GET | `/me/participations` | Compétitions, étape en cours, état de ma soumission |
| GET | `/me/participations/{slug}` | « Mon parcours » (comme la page web) : `participant` (`status`, `paid`, `payment_required`, `awaits_approval`), `out`, `champion`, `next` (`type` : `submit`/`sent`/`vote`/`wait`/`stage`, `text`, `deadline`, `stage_id`, `match_id`), `preselection` (`state`, `ends_at`, `can_submit`, `media_rules`, `entry`, `result`), `phases[]` (`title`, `online`, `state`, `stages[]` : `name`, `state`, `label`, `dates`, `action`, `media_rules`, `match` (`is_group`, `title`, `others[]` avec photo, `my_score`, `my_rank`, `voting_open`), `submission`) ; 404 hors participants |
| GET | `/competitions/{slug}/stages/{id}/submission` | Ma soumission pour l'étape |
| POST | `/competitions/{slug}/stages/{id}/submission` | multipart `media` (vidéo ou audio selon les règles de la phase) |

## Paiement et présélection

| Méthode | URL | Notes |
|---|---|---|
| POST | `/competitions/{slug}/registrations` | Réponse : `status` (`paiement_en_attente` si frais), `payment_required`, `amount`, `currency` |
| POST | `/competitions/{slug}/payment` | `method` (`orange_money`, `mtn_momo`, `moov_money`, `wave`, `carte`), `simulate_failure?` → 201 payé / 402 refusé |
| GET | `/competitions/{slug}/preselection` | État (`programmee`, `ouverte`, `vote`, `deliberation`, `cloturee`, `publiee`), `ends_at` (date limite d'envoi ; plus de `starts_at`), `vote_ends_at`, `deliberation_ends_at`, `next_deadline`, `likes_open`, `public_voting_enabled`, pondérations effectives, nombre retenu, `media_rules`, `entries_count`, `my_like` |
| GET | `/competitions/{slug}/preselection/entries` | Prestations publiées, **pagination par curseur** (`cursor`, `limit` ≤ 50, `meta.next_cursor`), recherche `q` : les plus récentes d'abord, par rang après la publication. Élément : `stage_name`, `avatar_url`, `media`, `likes` (visibles après son propre like, si résultats en direct ou après publication ; l'artiste voit toujours ceux de sa prestation), `liked`, `rank`, `selected`, `share_url` |
| POST | `/competitions/{slug}/preselection/submission` | multipart `media` (artiste inscrit, période ouverte) |
| POST / DELETE | `/competitions/{slug}/preselection/entries/{id}/like` · `/competitions/{slug}/preselection/like` | Un like par compétition (déplaçable), numéro vérifié, jusqu'à la fin du vote, si le vote du public est activé. Réponse : `message`, `my_like` (id liké ou `null`), `counts` (`{id: likes}` des prestations dont le compteur est visible : toutes après son like, si résultats en direct ou sélection publiée ; sinon seulement la sienne pour un artiste, ou `null`), `can_like` |
| POST | `/competitions/{slug}/preselection/entries/{id}/scores` | Juré : `scores[{criterion_id, score, comment?}]`, jusqu'à la fin de la délibération ; **une seule fois** (422 si déjà noté, l'organisateur peut rouvrir) ; avec la répartition, seulement les prestations attribuées (422 sinon). Réponse : `next_entry_id` (prochaine à noter, `null` quand tout est noté) |

## Jury (`password.changed` requis)

| Méthode | URL | Notes |
|---|---|---|
| GET | `/judge/competitions` | Uniquement les compétitions où l'utilisateur est juré, avec `preselection` (`state`, `accepts_scores`, `total`, `scored`) |
| GET | `/judge/competitions/{slug}/preselection` | Mes prestations `tab=a_noter` (défaut) ou `notees`, recherche `q`, curseur (`limit` ≤ 50). `data[]` : `stage_name`, `avatar_url`, `media`, `my_total` ; `meta` : `next_cursor`, `state`, `accepts_scores`, `deliberation_ends_at`, `splits_judging`, `jury_weight`, `total`, `scored`, `next_entry_id`, `criteria[]`. 404 si non juré accepté |
| GET | `/judge/competitions/{slug}/preselection/entries/{id}` | Une prestation : `media`, `my_scores[]`, `can_score` (notes définitives une fois enregistrées), `next_entry_id` ; 404 hors de mes prestations |
| GET | `/judge/competitions/{slug}` | Critères, étapes, matchs à noter (`to_score`, `scored_participants`) ; 404 si non affecté |
| GET | `/judge/competitions/{slug}/matches/{id}` | Match, médias, critères, mes notes |
| POST | `/competitions/{slug}/matches/{id}/jury-scores` | `participant_id, scores[{criterion_id, score, comment?}]` — tous les critères, réécriture possible jusqu'à la fin de la délibération (`deliberation_ends_at`, sinon fin du vote) |

## Temps réel (Socket.IO)

| Méthode | URL | Description |
|---|---|---|
| GET | `/realtime` | `channels[]` optionnels → `enabled`, `url`, `channels` (publics), `token` (canaux privés autorisés, dont toujours `user.{id}`), `expires_in` |

Client (`socket_io_client` en Flutter) : se connecter à `url`, puis émettre
`subscribe` `{ channels: [...publics], token }` (accusé : `{ joined: [...] }`). Chaque mise à jour arrive sur
l'événement `update` : `{ channel, type, data: { competition_id, match_id? }, message, at }`.

- Canaux publics : `competition.{id}` (page publique), `live` (accueil). Privés (jeton) : `user.{id}`,
  `jury.competition.{id}` (juré accepté), `bo.competition.{id}` / `bo.organizer.{id}` (back-office web).
- Types : `participant.registered|status`, `payment.paid`, `preselection.{statut}` / `performance.{statut}`
  (`traitement`, `en_attente`, `validee`, `rejetee`), `preselection.like|changed|published`, `vote.cast`,
  `jury.score`, `match.{statut}`, `match.voting`, `phase.published` (résultats des poules), `stage.{statut}`, `competition.status|changed`, `judge.changed`.
- Un `update` signale un changement : l'app recharge la ressource concernée par l'API (les données restent la
  source de vérité). `message` (texte français) est prévu pour une notification.
- Les votes n'arrivent sur les canaux publics que si la compétition affiche les résultats en direct ; les likes y
  arrivent toujours comme simple signal (aucun compteur dans le message).
- Lien à partager pour une prestation : `https://<domaine>/vote/competitions/{slug}/prestations/{id}` (page web avec aperçu).

