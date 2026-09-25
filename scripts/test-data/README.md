# Données de test grandeur nature

Scripts `tinker` (sans balise `<?php`) pour remplir un environnement de test. Jamais pour de vraies données.

## `two-organizers.php`

Deux organisateurs vérifiés, une compétition en ligne chacun (20 artistes validés, 2 jurés, poules en cours),
une vraie vidéo par artiste (clips [Mixkit](https://mixkit.co/license/#videoFree), licence libre, sans son).
Mot de passe de tous les comptes : `12345678`. Relançable sans doublon.

```bash
# 1. Vidéos (ids dans le script) téléchargées dans $VIDEO_DIR/{id}.mp4
# 2. Création + envoi des prestations (limite des envois dans 12 min, vote 3 jours)
MODE=seed VIDEO_DIR=/tmp/bg-videos php artisan tinker --execute "$(cat scripts/test-data/two-organizers.php)"
# 3. Quelques minutes plus tard (vidéos traitées) : validation ; le vote s'ouvre seul à la limite
MODE=approve php artisan tinker --execute "$(cat scripts/test-data/two-organizers.php)"
```

Comptes : organisateurs `orga.rap@test.ci` / `orga.danse@test.ci` (back-office), artistes `0798100001`–`0798100020`
et `0798200001`–`0798200020`, jurés `0797100001`–`2` et `0797200001`–`2`.

## `replace-videos.php`

Remplace les vidéos des artistes d'une compétition (prestations d'étape et de présélection) par des vidéos
locales, une par artiste (la même pour les deux), en gardant leur statut de validation ; l'optimisation
(miniature, lecture rapide) repasse en file d'attente.

```bash
COMPETITION="Compétition Démo 2026 (copie)" VIDEO_DIR=/tmp/bg-videos VIDEO_IDS="50487,45441,482" \
  php artisan tinker --execute "$(cat scripts/test-data/replace-videos.php)"
```
