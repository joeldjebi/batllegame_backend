# API mobile (`/api`)

Authentification **Sanctum** par token : `Authorization: Bearer {token}`. Réponses JSON,
erreurs de validation en 422 (messages en français), règles métier en 422 `{"message": …}`.
Les compétitions sont adressées par **slug** ; les ressources imbriquées (`matches`, `stages`)
sont toujours résolues à travers leur compétition.

## Authentification

| Méthode | URL | Corps | Notes |
|---|---|---|---|
| GET | `/countries` | | Pays actifs (indicatifs pour tous les champs téléphone) |
| POST | `/auth/register` | `name, country_id, phone, password, password_confirmation, email?` | Envoie un code SMS |
| POST | `/auth/login` | `country_id, phone, password, device_name?` | → `{token, user}` ; super-admin refusé |
| POST | `/auth/logout` | | Révoque le token |
| GET | `/auth/me` | | Inclut `phone_verified`, `must_change_password`, `is_judge` |
| POST | `/auth/phone/send-code` · `/auth/phone/verify` | `code` | Vérification du téléphone (obligatoire pour voter) |
| POST | `/auth/password` | `current_password, password, password_confirmation` | Obligatoire pour les jurés créés par un organisateur |

## Public

| Méthode | URL | Notes |
|---|---|---|
| GET | `/competitions` | Filtres `status`, `discipline` ; les brouillons ne sont jamais exposés |
| GET | `/competitions/{slug}` | Phases, critères, `description` (HTML nettoyé), `prizes` (`[{rank, reward}]`) |
| GET | `/competitions/{slug}/matches/{id}` | Slots, scores (après clôture ou si résultats en direct), `media` publiés, `vote_code_required`, étape, `voting_open`, `deliberation_ends_at`, `jury_scoring_open` |
| POST | `/competitions/{slug}/matches/{id}/votes` | `participant_id`, `vote_code` (présentiel avec code) ; en-tête `X-Device-Id` ; 409 si déjà voté |

## Artiste

| Méthode | URL | Notes |
|---|---|---|
| POST | `/competitions/{slug}/registrations` | `stage_name` |
| GET | `/me/participations` | Compétitions, étape en cours, état de ma soumission |
| GET | `/competitions/{slug}/stages/{id}/submission` | Ma soumission pour l'étape |
| POST | `/competitions/{slug}/stages/{id}/submission` | multipart `media` (vidéo ou audio selon les règles de la phase) |

## Paiement et présélection

| Méthode | URL | Notes |
|---|---|---|
| POST | `/competitions/{slug}/registrations` | Réponse : `status` (`paiement_en_attente` si frais), `payment_required`, `amount`, `currency` |
| POST | `/competitions/{slug}/payment` | `method` (`orange_money`, `mtn_momo`, `moov_money`, `wave`, `carte`), `simulate_failure?` → 201 payé / 402 refusé |
| GET | `/competitions/{slug}/preselection` | État (`programmee`, `ouverte`, `vote`, `deliberation`, `cloturee`, `publiee`), `ends_at`, `vote_ends_at`, `deliberation_ends_at`, `next_deadline`, `likes_open`, `public_voting_enabled`, pondérations effectives, nombre retenu, prestations publiées (likes visibles si résultats en direct ou après publication), `my_like` |
| POST | `/competitions/{slug}/preselection/submission` | multipart `media` (artiste inscrit, période ouverte) |
| POST / DELETE | `/competitions/{slug}/preselection/entries/{id}/like` · `/competitions/{slug}/preselection/like` | Un like par compétition (déplaçable), numéro vérifié, jusqu'à la fin du vote, si le vote du public est activé |
| POST | `/competitions/{slug}/preselection/entries/{id}/scores` | Juré : `scores[{criterion_id, score, comment?}]`, jusqu'à la fin de la délibération |

## Jury (`password.changed` requis)

| Méthode | URL | Notes |
|---|---|---|
| GET | `/judge/competitions` | Uniquement les compétitions où l'utilisateur est juré |
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
  `jury.score`, `match.{statut}`, `match.voting`, `stage.{statut}`, `competition.status|changed`, `judge.changed`.
- Un `update` signale un changement : l'app recharge la ressource concernée par l'API (les données restent la
  source de vérité). `message` (texte français) est prévu pour une notification.
- Les votes et likes n'arrivent sur les canaux publics que si la compétition affiche les résultats en direct.

