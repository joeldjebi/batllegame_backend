# Règles métier

## Acteurs

| Acteur | Compte | Ce qu'il fait |
|---|---|---|
| Super-admin | email, rôle spatie `platform-admin` | Vérifie / suspend les organisateurs, vue globale en lecture seule |
| Organisateur (owner / admin / staff) | email, membre d'un `organizer` | Owner : tout + membres + suppression. Admin : configure compétitions, phases, jury, critères. Staff : inscriptions, matchs, soumissions |
| Juré | téléphone, créé par l'organisateur | Note uniquement les compétitions où il est affecté |
| Artiste (participant) | téléphone | S'inscrit, envoie une prestation par étape |
| Public | téléphone **vérifié par SMS** | Vote une fois par match |

Un même compte téléphone peut être artiste dans une compétition et votant dans d'autres,
mais **jamais juré et participant d'une même compétition**. Un participant ne vote pas
dans son propre match, un juré ne vote pas avec le public.

## Organisateurs

- **Seul le super-admin crée les organisateurs**, avec leur compte propriétaire (console → Organisateurs →
  « Nouvel organisateur »). Un organisateur ne peut pas en créer un autre.
- Statut à la création : `verifie` ou `en_attente` ; seuls les organisateurs **vérifiés** peuvent ouvrir des inscriptions.
- Les **managers** (admin, staff) sont ajoutés par le propriétaire : compte existant retrouvé par email
  (ou par téléphone s'il s'agit d'un compte mobile sans email), sinon compte créé.
- **Mot de passe provisoire** : tout compte créé par un tiers (propriétaire, manager, juré) reçoit un mot de
  passe provisoire par SMS, affiché une fois à la personne qui l'a créé, et doit le changer à la première
  connexion (`/mot-de-passe` au back-office, `/jury/mot-de-passe` au jury).
- `suspendu` : tout reste lisible, aucune écriture, aucun vote, aucune notation.
- Un organisateur garde toujours au moins un owner.

## Cycle de vie d'une compétition

`brouillon → inscriptions → en_cours → terminee` (ou `annulee` à tout moment avant la fin).
Démarrer la première phase fait passer la compétition `en_cours`. La fin de la dernière
phase **d'élimination** la termine (une phase de poules seule ne la termine pas : l'organisateur
peut encore ajouter la phase suivante). Suppression (soft delete) : owner, brouillon ou annulée.

## Phases

- Types : `poules`, `elimination`, `double_elimination`, enchaînés par `position`.
- Mode : `presentiel`, `en_ligne` ou `mixte` sur la compétition ; une phase peut le surcharger.
  Dans une compétition **mixte**, chaque phase doit être `en_ligne` ou `presentiel`.
- Première phase : participants **validés**, triés par seed. Phases suivantes : qualifiés de la
  phase de poules précédente.
- **Poules** : tirage au sort ou par seed (distribution en serpentin), round-robin, points
  victoire / nul / défaite configurables, top N qualifiés, **croisement** 1A–2B, 1B–2A…
- **Élimination** : bracket complet généré au démarrage, **byes aux meilleures têtes de série**,
  résolus en « exempt ». **Double élimination** : tableau des perdants, grande finale, reset optionnel
  (joué seulement si le finaliste du tableau des perdants gagne la première finale).

## Étapes (stages)

Une **étape** regroupe les matchs joués en même temps : toute la phase pour des poules, un tour
de bracket en élimination. Chaque étape a son calendrier : date limite de soumission, ouverture
et clôture du vote.

### En ligne

1. L'organisateur programme l'étape et **ouvre les soumissions** (tous les participants de l'étape doivent être connus).
2. Chaque participant envoie **une prestation par étape** (vidéo ou audio), remplaçable jusqu'à la date limite,
   réutilisée pour tous ses matchs de l'étape. Règles fixées par l'organisateur dans la phase :
   types, durée max (vérifiée par ffprobe), taille max.
3. Option « Valider les soumissions » (par défaut) : l'organisateur valide / rejette chaque média ;
   seuls les médias validés sont visibles du public et du jury.
4. **À la date limite, sans soumission (ou soumission rejetée) : forfait.**
   Élimination : l'adversaire gagne ; si les deux manquent, match annulé, les deux passent `forfait`.
   Poules : défaite (les deux perdent si aucun n'a soumis).
5. Le vote s'ouvre automatiquement (ou à l'heure prévue) une fois les soumissions traitées ;
   il se ferme à `voting_closes_at` (commande planifiée).

### Présentiel

- L'organisateur ouvre le vote **match par match**, en direct, avec une durée optionnelle.
- Option « Code de salle » : un code à 4 chiffres affiché dans la salle est exigé pour voter
  (seules les personnes présentes votent). Sans l'option, tout le monde vote dans l'application.
- Le staff peut ajouter la **captation** vidéo d'une prestation (publiée directement).

## Scores

- **Jury** (0–100) : pour chaque juré, moyenne des critères pondérée par leur poids, chaque note
  normalisée par sa note max ; puis moyenne des jurés ayant noté. Clôture refusée si le jury
  (utilisé par la phase) n'a pas noté.
- **Public** (0–100) : part des voix du match ; 50 / 50 sans aucun vote.
- **Final** : `jury × poids_jury + public × poids_public` (poids de la phase, total 100 en mode mixte).
- **Départage** (ordre défini dans la phase) : score jury, score public, seed ; confrontation
  directe et différence de score pour les classements de poule. Égalité parfaite : l'organisateur
  désigne le vainqueur.

## Vote du public

- Un vote par utilisateur et par match (contrainte unique + savepoint anti-course).
- Numéro de téléphone vérifié obligatoire ; limite optionnelle de votes par appareil.
