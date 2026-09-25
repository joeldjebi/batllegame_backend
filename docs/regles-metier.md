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

## Lieux (pays, villes, communes)

- Le **super-admin** gère le référentiel (console → Référentiel → « Pays, villes, communes ») : il crée les pays
  (indicatif, longueur des numéros, drapeau, devise), puis les villes et les communes **à la main**, une par une ou
  plusieurs à la fois (une par ligne ; doublons ignorés). Une liste de départ facultative existe :
  `php artisan db:seed --class=LocationSeeder` (31 villes de Côte d'Ivoire, communes d'Abidjan).
- Personne d'autre ne saisit de lieu : organisateurs (ville **obligatoire** dès qu'une ville existe, commune
  obligatoire si la ville en a), compétitions (lieu facultatif) et utilisateurs (ville facultative à l'inscription)
  choisissent dans ces listes.
- Un lieu **inactif** n'est plus proposé mais reste affiché sur les fiches qui l'utilisent. Un lieu **utilisé** ne
  peut pas être supprimé (le désactiver) ; un pays avec des villes ou des comptes non plus. Au moins un pays reste actif.

### Parcours de l'artiste (« Mon parcours »)

`/artiste/competitions/{slug}` : la prochaine action en tête (envoyer sa prestation avec compte à rebours, partager
pendant le vote, attendre la délibération, rendez-vous sur scène), puis la présélection (sélectionné / rang) et chaque
phase jusqu'à la finale, étape par étape : dates, sa poule (membres) ou son adversaire, sa prestation (lecture,
statut, motif de refus, remplacement jusqu'à la limite), résultat (rang, qualifié, gagné, éliminé, forfait). Les tours
à venir apparaissent avec le calendrier prévu (« Si tu te qualifies »). Réservé aux artistes de la compétition.

### Notation du jury (présélection)

- **Notes définitives** : un juré note une prestation une seule fois (confirmation « définitives ») ; ensuite elle
  s'affiche en lecture seule. En cas d'erreur, l'organisateur **rouvre** la note du juré (fenêtre de la prestation →
  « Notes des jurés » → Rouvrir) : elle est effacée et le juré la refait. Possible jusqu'à la fin de la délibération.
- **Espace juré** : progression (« 5 / 17 notées »), onglets **À noter / Notées**, recherche, liste paginée (20) sans
  vidéo ; une page par prestation (lecteur + curseurs) avec « Enregistrer et suivante ».
- **Répartition entre les jurés** (Configurer → « Répartir les prestations entre les jurés », désactivé par défaut) :
  chaque prestation validée est attribuée à N jurés (équilibré, stable), la note jury est la moyenne des jurés qui
  l'ont notée ; un juré ne voit et ne note que sa liste. Par défaut, chaque juré note toutes les prestations.
- **BO** : avancement de chaque juré, classement paginé côté serveur (20 par page, filtres et recherche dans l'URL),
  classement recalculé en tâche de fond après chaque note.

## Organisateurs

- **Inscription libre** (`/creer-mon-espace`, lien sur la connexion et l'accueil) : un visiteur crée son
  organisateur et son compte propriétaire (nom, email, téléphone, mot de passe choisi, conditions acceptées) et
  arrive connecté sur son espace. Un utilisateur déjà connecté au back-office peut ouvrir un organisateur de plus.
  Un compte mobile existant (artiste, public) avec le même numéro est relié **seulement si** le mot de passe saisi
  est le sien ; un email ou un numéro déjà utilisés par un compte back-office sont refusés.
  Désactivable : `ORGANIZER_SELF_SIGNUP=false`.
- Un organisateur inscrit seul est **`en_attente`** : il prépare ses compétitions en brouillon, mais n'ouvre les
  inscriptions qu'une fois **vérifié par le super-admin** (notifié en temps réel, liste « À vérifier »). L'organisateur
  est prévenu en direct quand il est vérifié ou suspendu.
- Le **super-admin** peut aussi créer un organisateur avec son compte propriétaire (console → Organisateurs →
  « Nouvel organisateur »), directement `verifie` ou `en_attente`.
- Seuls les organisateurs **vérifiés** peuvent ouvrir des inscriptions.
- Les **managers** (admin, staff) sont ajoutés par le propriétaire : compte existant retrouvé par email
  (ou par téléphone s'il s'agit d'un compte mobile sans email), sinon compte créé.
- **Jurés, en attendant un service SMS** : `JUDGE_DEFAULT_PASSWORD` (.env, ignoré en production) donne à tout nouveau
  compte juré ce mot de passe provisoire (à changer à la première connexion) ; `php artisan judges:default-password --force`
  l'applique aux jurés existants (jamais aux comptes back-office ni aux super-admins).
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

- **Photo de profil** : chaque utilisateur l'ajoute depuis « Mon profil » (portail) ou l'app (`POST /api/auth/profile`),
  recadrée en carré 512 px. Elle accompagne l'artiste dans le BO (participants, présélection, poules, étapes) et sur
  les pages publiques. **Fiche participant** (BO, « Voir la fiche ») : photo, nom, téléphone (appel, WhatsApp),
  email, ville, pays, vérification du numéro, dates, paiements — visible seulement de l'organisateur de la compétition.

- Compétition **gratuite** : l'artiste est inscrit directement (validé, ou « inscrit » si une présélection ou
  une validation manuelle est prévue).
- **Envoi de la prestation de présélection** : frais payés **et**, si « Valider les inscriptions » est activé,
  inscription **validée par l'organisateur** (statut `valide`) ; sans validation manuelle, un artiste « inscrit »
  payé peut envoyer directement. En attendant, l'espace artiste affiche « Inscription en attente de validation ».
  À la publication, tout artiste non retenu (inscrit ou validé) passe `non_retenu`.
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
- Sur le portail public, « J'aime » / « Je n'aime plus » / « Déplacer mon like » fonctionnent **sans rechargement**
  (AJAX) : le bouton et les compteurs changent immédiatement puis sont confirmés par le serveur (annulés en cas de
  refus, avec le message). Les **compteurs** sont visibles **dès qu'on a liké** (comme un sondage), pour tout le
  monde si l'organisateur affiche les résultats en direct, et après la publication ; ils se mettent à jour tout
  seuls avec les likes des autres (temps réel). L'**artiste** voit toujours le compteur de **sa** prestation
  (il ne peut pas la liker), dans son espace et sur les pages publiques, avec un toast à chaque nouveau like.
- **Partage** : chaque prestation publiée a sa page publique (`/vote/competitions/{slug}/prestations/{id}`) avec
  aperçu WhatsApp / Facebook / X (titre, texte, image Battle Game, vidéo) et bouton « J'aime ». Le bouton
  « Partager » (cartes de la galerie, page de la prestation, espace artiste, matchs en vote) ouvre le partage natif
  du téléphone, sinon WhatsApp, Facebook, X, Telegram ou « Copier le lien ». Un match en vote se partage par un lien
  direct vers sa carte (`#match-{id}`).
- Les prestations sont affichées dans un ordre aléatoire **propre à chaque visiteur** et stable (équitable, sans
  saut de cartes), puis par rang après la publication.
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
**Création en 3 étapes**, sur une page dédiée (`/organizers/{slug}/competitions/nouvelle`, stepper et aperçu en direct de la structure jusqu'à la finale) : 1. la compétition (nom, description, discipline, mode,
lieu, fin des inscriptions, inscrits max., frais) ; 2. **présélection ou non** — elle précède la compétition et n'a
rien à voir avec les poules : **date limite d'envoi** (pas de date d'ouverture : chaque artiste envoie dès que son inscription est validée et payée), fin du vote du public, délibération du jury, artistes retenus,
pondération likes/jury, médias ; 3. **le format** (poules puis phase finale, élimination directe, double
élimination, ou plus tard), les participants attendus étant les artistes retenus (sinon les inscrits max.). Tout est
créé d'un coup, en brouillon : la présélection, la première phase et, pour des poules, la phase finale (calendrier
prévu jusqu'à la finale). Le calendrier de la première phase ne peut pas commencer avant la fin de la délibération
de la présélection.

**Déroulé** (automatique) : calculé en permanence depuis la configuration — clôture des inscriptions, présélection
(date limite, fin du vote, annonce), chaque tour des phases avec son calendrier prévu ou réel (« Date à planifier »
sinon), résultats. L'organisateur peut y ajouter des **étapes supplémentaires** (ex. conférence de presse), placées
selon leur date (sans date : à la fin). **Règlement** : texte mis en forme (nettoyé) écrit par l'organisateur ;
« Générer un brouillon du règlement » le pré-remplit depuis la configuration (frais, présélection, phases,
pondérations, votes, médias, départage, critères) **dans le formulaire seulement** : rien n'est enregistré ni public
avant que l'organisateur relise et enregistre. Affichés sur la page publique (ancre `#reglement`), rappelés
dans l'espace artiste (« En t'inscrivant, tu acceptes le règlement ») et exposés par l'API (`schedule`,
`regulations`). Copiés à la duplication.
Démarrer la première phase fait passer la compétition `en_cours`. La fin de la dernière
phase **d'élimination** la termine (une phase de poules se termine à la publication de ses résultats et ne termine
pas la compétition : l'organisateur peut encore ajouter la phase suivante). Suppression (soft delete) : owner, quel que soit le statut, **tant qu'aucun participant n'a payé** ; dès qu'un paiement est reçu, la compétition ne peut plus qu'être annulée (participants à rembourser). La confirmation indique le nombre d'inscrits retirés.

## Phases

- Types : `poules`, `elimination`, `double_elimination`, enchaînés par `position`.
- **Enchaînements** : seules des poules qualifient des artistes pour la phase suivante ; une phase à
  **élimination désigne le vainqueur** et ne peut être suivie de rien (le BO ne propose plus « Ajouter une phase »,
  une phase suivie d'une autre ne peut devenir une élimination). Une phase démarre quand la précédente est terminée.
- **Phase finale automatique** : créer des poules crée aussi la phase à **élimination simple** qui suit (mêmes mode,
  pondération, médias, départage), dimensionnée par les qualifiés (ex. 4 poules × 2 = 8 → quarts, demies, finale).
  Modifiable ou supprimable (par exemple pour passer en double élimination).
- **Calendrier prévu** : avant le démarrage, chaque phase affiche ses étapes jusqu'à la finale (« Poules », puis
  « Quarts de finale », « Demi-finales », « Finale » selon les participants attendus) ; l'organisateur y planifie
  date limite d'envoi (en ligne), ouverture et fin du vote, délibération du jury. Au démarrage, ces dates sont
  copiées sur les étapes réelles, par nom (si le tableau est plus petit, les derniers tours gardent leurs dates).
  Double élimination : planification après le démarrage. « Générer un brouillon » du déroulé reprend ces tours.
- **Modification** : une phase se modifie (format, poules, mode, pondération, médias, départage) tant qu'elle n'a
  pas démarré ; au démarrage ses règles sont figées.
- **Départage** (section « Avancé ») : à note finale égale, d'abord le jury ou d'abord le public (au choix), puis
  l'autre, puis la tête de série ; en dernier recours l'ordre d'inscription (poules) ou le choix de l'organisateur
  à la clôture du battle (élimination).
- Mode : `presentiel`, `en_ligne` ou `mixte` sur la compétition ; une phase peut le surcharger.
  Dans une compétition **mixte**, chaque phase doit être `en_ligne` ou `presentiel`.
- Première phase : participants **validés**, triés par seed. Phases suivantes : qualifiés de la
  phase de poules précédente.
- **Poules (de classement)** : les artistes d'une poule **ne s'affrontent pas**. Répartition au sort ou par seed
  (serpentin). Chaque artiste présente **sa prestation** (vidéo en ligne ou passage sur scène) ; chaque poule est
  classée par la note finale (jury + public selon la pondération de la phase), départage : jury, public, seed, puis
  ordre d'inscription. **Vote du public : un seul vote pour toute la phase**, toutes poules confondues (non
  modifiable) ; les artistes de la phase ne votent pas. Après la délibération, la poule est close (classement privé),
  puis **l'organisateur publie les résultats de la phase** (toutes les poules closes) : les N premiers de chaque
  poule sont qualifiés, les autres éliminés, le classement devient public et chacun est prévenu.
  Qualifiés croisés dans le bracket suivant : 1A–2B, 1B–2A…
- **Assistant de format** (BO) : participants attendus pré-remplis (présélection, poules précédentes, maximum ou
  validés), formules conseillées, aperçu (tailles des poules, qualifiés, tour suivant, exemptés) ; un format
  injouable (trop de poules, autant de qualifiés que d'artistes) est refusé à l'enregistrement et au lancement.
- **Élimination** : bracket complet généré au démarrage, **byes aux meilleures têtes de série**,
  résolus en « exempt ». **Double élimination** : tableau des perdants, grande finale, reset optionnel
  (joué seulement si le finaliste du tableau des perdants gagne la première finale).

## Étapes (stages)

Une **étape** regroupe les matchs joués en même temps : toutes les poules d'une phase (une poule = un « match »
réunissant tous ses artistes), un tour de bracket en élimination. Chaque étape a son calendrier : date limite de soumission, ouverture
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
   Poules : l'artiste sort du classement (forfait) et ne peut pas se qualifier ; la poule continue avec les autres.
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
