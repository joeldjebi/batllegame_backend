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
- **Gestion des compétitions** (back-office → organisateur → Compétitions) : liste avec recherche, filtres
  (statut, discipline, mode), tri (récentes, nom, fin des inscriptions, participants) et pagination.
  - **Créer** (owner, admin) : toujours en brouillon ;
  - **Modifier** : onglet Paramètres, tant que la compétition n'est ni terminée ni annulée ;
  - **Dupliquer** (owner, admin) : nouveau brouillon avec présentation, récompenses, options, critères et
    phases ; jamais les participants, jurés, matchs, votes, paiements ni la présélection (datée) ;
  - **Supprimer** (owner) : seulement en brouillon ou annulée (suppression douce) ;
  - le staff consulte sans agir.

## Inscription et frais

- Compétition **gratuite** : l'artiste est inscrit directement (validé, ou « inscrit » si une présélection ou
  une validation manuelle est prévue).
- Compétition **payante** (`entry_fee` > 0) : l'inscription reste en **« paiement en attente »** tant que les frais
  ne sont pas payés ; l'artiste n'est pas encore artiste de la compétition.
- **Aucun envoi sans paiement** : tant que les frais ne sont pas payés, l'espace artiste n'affiche aucun bouton
  d'envoi mais un encart incitatif (bénéfices, prix, compte à rebours, bouton « Je confirme ») ; le serveur refuse
  aussi toute soumission (`Participant::hasPaid()`), à la présélection comme aux étapes.
- **Paiement simulé** (aucun prestataire branché) : l'artiste choisit Orange Money, MTN MoMo, Moov Money, Wave ou
  carte et le résultat à simuler (accepté / refusé). Chaque tentative est enregistrée dans `payments`
  (montant, moyen, référence `BG-…`, statut). Un paiement accepté fait passer l'artiste « inscrit » (ou « validé »).

## Présélection

Étape optionnelle, une par compétition, configurée par l'organisateur après les inscriptions :

- **Calendrier défini par l'organisateur** :
  1. **Envois** (début → fin des envois) : les artistes inscrits (frais payés) envoient **une** prestation (vidéo ou
     audio, remplaçable jusqu'à la fin des envois, règles de médias propres, validation si l'option est active) ;
  2. **Vote du public** jusqu'à la **fin du vote** (≥ fin des envois ; vide = fin des envois) ;
  3. **Délibération du jury** : une durée en heures après la fin du vote, pendant laquelle seuls les jurés notent ;
  4. **Résultats** : la sélection se publie après la fin de la délibération.
- **Likes du public** (seulement si l'option « Vote du public » de la compétition est active) : un utilisateur au
  numéro vérifié like **une seule prestation par compétition**, dès qu'une prestation est publiée et jusqu'à la fin
  du vote (il peut déplacer ou retirer son like) ; jamais la sienne ; les jurés ne likent pas. Option désactivée :
  aucun like, classement **100 % jury**.
- **Jury** : les jurés notent chaque prestation selon les critères (sur 100, moyenne des jurés), du début des envois
  à la fin de la délibération ; ensuite les notes sont figées.
- **Score final** = score jury × % jury + score likes × % likes (définis par l'organisateur, total 100 %).
  Score likes = likes de la prestation ÷ likes de la plus likée × 100.
- **Nombre d'artistes retenus** (participants de l'événement) défini par l'organisateur.
- Après la délibération, l'organisateur **publie la sélection** (toutes les prestations traitées) avec les notes
  reçues : une prestation qu'aucun juré n'a notée compte 0 pour la part jury (l'organisateur est averti avant de
  publier). Les N premiers passent **« validé »**, les autres **« non retenu »**. Départage : jury, likes, ancienneté.
- La première phase de la compétition ne peut démarrer qu'après la publication ; seuls les artistes retenus y participent.
- Les règles de la présélection sont figées dès son début (les dates restent modifiables jusqu'à la publication).

## Provenance des prestations (métadonnées cachées)

À chaque envoi (présélection et étapes), le serveur lit les **métadonnées cachées** du fichier
(`App\Services\Media\MediaProvenance` : lecteur MP4/MOV en PHP, complété par ffprobe s'il est installé) :
- **date d'enregistrement** (date Apple `creationdate`, balise date, ou date du conteneur) comparée à la période
  attendue (présélection : début → fin des envois ; étape : démarrage de la phase → date limite) :
  « pendant la période », « avant la période », « date incohérente » (postérieure à l'envoi), « inconnue » ;
- **origine probable** : appareil (marque / modèle), application de montage (CapCut, InShot…), plateforme
  (TikTok, YouTube, Instagram…), fichier ré-encodé (convertisseur, messagerie), métadonnées effacées ;
- la **date du fichier sur l'appareil** transmise par le navigateur (indicative).

L'organisateur voit ces badges et le détail dans la liste des prestations à valider. C'est un **indice, jamais une
preuve** (les métadonnées peuvent être effacées ou modifiées) : rien n'est refusé automatiquement, l'organisateur
décide. La position GPS n'est jamais conservée (seulement sa présence). `php artisan media:provenance` analyse les
médias envoyés avant cette fonctionnalité.

## Cycle de vie d'une compétition

`brouillon → inscriptions → en_cours → terminee` (ou `annulee` à tout moment avant la fin).
Pour ouvrir les inscriptions, l'organisateur doit avoir rédigé une **description** (texte enrichi : gras, italique,
titres, listes, citations, liens — nettoyée côté serveur) et listé **au moins une récompense** (rang + lot, dans l'ordre
du classement ; un rang vide devient « 1er prix », « 2e prix »…). Les deux sont affichées au public et aux artistes
et exposées par l'API (`description`, `prizes`).
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
et **fin du vote du public**, puis **délibération du jury** (durée en minutes après la fin du vote,
0 = aucune). Pendant la délibération, le public ne vote plus et seuls les jurés notent ; à la fin,
le match se clôture automatiquement avec les notes reçues.

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
   le public vote jusqu'à `voting_closes_at`, le jury note jusqu'à `deliberation_ends_at`, puis la commande
   planifiée clôture le match.

### Présentiel

- L'organisateur ouvre le vote **match par match**, en direct, avec une durée optionnelle et le temps de
  délibération du jury (par défaut celui de l'étape).
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
