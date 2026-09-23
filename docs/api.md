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
| GET | `/competitions/{slug}` | Phases, critères |
| GET | `/competitions/{slug}/matches/{id}` | Slots, scores (après clôture ou si résultats en direct), `media` publiés, `vote_code_required`, étape |
| POST | `/competitions/{slug}/matches/{id}/votes` | `participant_id`, `vote_code` (présentiel avec code) ; en-tête `X-Device-Id` ; 409 si déjà voté |

## Artiste

| Méthode | URL | Notes |
|---|---|---|
| POST | `/competitions/{slug}/registrations` | `stage_name` |
| GET | `/me/participations` | Compétitions, étape en cours, état de ma soumission |
| GET | `/competitions/{slug}/stages/{id}/submission` | Ma soumission pour l'étape |
| POST | `/competitions/{slug}/stages/{id}/submission` | multipart `media` (vidéo ou audio selon les règles de la phase) |

## Jury (`password.changed` requis)

| Méthode | URL | Notes |
|---|---|---|
| GET | `/judge/competitions` | Uniquement les compétitions où l'utilisateur est juré |
| GET | `/judge/competitions/{slug}` | Critères, étapes, matchs à noter (`to_score`, `scored_participants`) ; 404 si non affecté |
| GET | `/judge/competitions/{slug}/matches/{id}` | Match, médias, critères, mes notes |
| POST | `/competitions/{slug}/matches/{id}/jury-scores` | `participant_id, scores[{criterion_id, score, comment?}]` — tous les critères, réécriture possible tant que le vote est ouvert |
