# Reprise — communauté et entraide

Lot courant : **B15**, prêt pour revue sur `backend/communaute-entraide-b15`, depuis B14 `6a0db1e`. Worktree isolé `.worktrees/b15` : une modification préexistante de `backend/routes/api/capsules-lab.php` dans le répertoire principal est préservée et exclue de cette livraison. B14 reste inchangé dans #24.

Lire [le contrat B15](../../../api/HELP_REQUEST_READING.md), [les preuves](../../../quality/B15_READING.md) et [SYSTALINK_TASKS.md](SYSTALINK_TASKS.md). Le SHA final, le numéro de PR et la CI du commit exact sont communiqués après commit dans le bilan/PR, sans SHA autoréférent.

Ordre d'intégration : #23 de Madina → #22 complément B11 → #24 B14 → PR B15, temporairement ciblée sur `backend/communaute-entraide-b14`. Après chaque intégration autorisée, synchroniser/recibler la suivante et vérifier sa CI. Aucune revue humaine, fusion ou suppression de branche présumée ; PR #12 conservée.

B15 : visibilité SQL commune avant recherche/pagination/total, détail protégé par Policy, vue publique et vue privée scope=mine, filtres stricts, tri stable et chargement des relations en nombre constant. Pas de migration ni dépendance nouvelle. Les parents projets sont à raccorder dans BC04/BC05 quand ces données existeront ; AC15 capsules et AC25 global restent ouverts.

Prochain lot : **B16 — Modifier une demande** sous version/verrou/révision ; publication d'un brouillon existant par commande documentée. Attendre l'intégration des prérequis ou préparer séparément sur demande de continuer pendant la revue. B11/B14/B15 En cours dans Systalink ; 24 autres lots à faire. Aucun GO_FRONTEND ni déploiement.

## Reprise B14 antérieure conservée


Lot courant : **B14**, branche `backend/communaute-entraide-b14`, issue #2. API de création prête pour revue après validations ; consulter [le contrat](../../../api/HELP_REQUEST_CREATION.md), [les preuves](../../../quality/B14_CREATION.md) et [SYSTALINK_TASKS.md](SYSTALINK_TASKS.md). Le dernier commit/CI est indiqué dans la PR et le bilan.

Travail de Madina préservé : PR #23 (B11 original, `1c380c4`) → PR #22 (compléments B11, `5854e04`, base reciblée sur sa branche) → PR B14 (base temporaire `backend/communaute-entraide`). Aucune de ces PR n'est présumée fusionnée. Après chaque fusion, synchroniser et recibler la suivante vers main, puis vérifier le dernier SHA. Ne pas clôturer #23 comme doublon ni écraser sa branche.

Le 4 octobre, l'utilisateur confirme que Madina n'a pas commencé B14 ni HelpIntent/BV201. B14 définit `App\Enums\HelpRequests\HelpIntent` une seule fois ; BV201/BC07 le réutilisent, sans deuxième enum ni migration doublonnée. Les cinq migrations B11 sont inchangées ; l'extension B14 est additive. Les contenus de Madina sous son dossier participant sont inchangés.

Prochain lot : **B15**, lectures/recherche avec visibilité avant filtres/pagination et compteurs ; réutiliser HelpRequestPolicy et HelpRequestResource. Ne pas ajouter B15 à la PR B14 pendant sa revue. B16 ajoutera les éditions/publications de brouillons existants sous version ; POST B14 crée une nouvelle ressource et n'est pas une route d'édition.

B11/B14 restent En cours dans Systalink jusqu'à intégration vérifiée. Les 25 autres lots de la coordination #2 restent à faire ; les points `ask_question`/HelpIntent préparés ne valident pas l'intégralité BC07/BV201. Aucun BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION. Tests PostgreSQL uniquement sur une base dédiée avec rôle haas_test.

## Synchronisation du 7 octobre 2026

B11 est intégré dans `main` (`f1f6238`). B14 récupère ce main par merge normal depuis `6a0db1e` ; l'unique conflit du registre IA est résolu en gardant B14 et B35. PR #24 reciblée vers main ; B15/#25 et B16/#26 restent dépendantes et doivent être synchronisées dans cet ordre.

Preuves de cette synchronisation : contrôles documentaires 18/18 et 7/7 ; 36 types API à jour sous PHP 8.5.10 ; empreintes des fichiers versionnés actualisées ; whitespace contrôlé avant commit. SHA réel et CI du head publiés dans le bilan. Les suites Laravel et PostgreSQL de cette branche ne sont pas réexécutées ici ; les contrôles documentaires ne les remplacent pas. Revue humaine encore requise, sans BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION.

## Synchronisation B15 du 7 octobre 2026

B15 récupère B14 synchronisé (`230b83b`) par merge normal depuis `0cfcde1`. Les conflits portent sur les documents et les empreintes ; les historiques B14/B15/B35 sont conservés, sans conflit de code. Contrôles : 18/18 documentaires, 7/7 déploiement documentaire, 38 types API à jour sous PHP 8.5.10 et whitespace avant commit. Suites Laravel/PostgreSQL non réexécutées sur B15 durant cette synchronisation.

PR #25 dépend de B14/#24. Publier le SHA réel et observer ses checks PHP 8.4/8.5 ; revue humaine requise. Prochaine synchronisation : B16 depuis ce B15. Aucun BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION.
