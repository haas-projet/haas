# Reprise — communauté et entraide

## 7 octobre 2026 — B17 prêt pour revue

Lot courant : **B17**, branche `backend/communaute-entraide-b17`, worktree `.worktrees/b17`, depuis B16 `8e9a60e` et main `f1f6238`. L'implémentation non commitée préexistante est préservée et complétée. Lire [COMMENTS.md](../../../api/COMMENTS.md), [B17_COMMENTS.md](../../../quality/B17_COMMENTS.md) et [SYSTALINK_TASKS.md](SYSTALINK_TASKS.md). Le SHA final et la CI exacte sont à consulter dans le bilan et la PR après publication.

Commentaires et révisions sous version/verrou parent, droits courants avant rejeu, Markdown inerte et visibilité avant pagination/compteurs. Notifications internes après commit, sans auto-envoi ni texte privé ; sept cas couvrent aussi le retrait du parent et l'inéligibilité de l'auteur. Tests distincts : **671 / 6805 assertions**, dont **343 / 3242 PostgreSQL**, PHP 8.5.10 ; Pint/PHPStan, Composer, 49 types et contrôles documentaires réussis. Aucun contrôle absent n'est compté comme réussi.

B11 DONE après les fusions #22 puis #23 réellement constatées. #24 est reciblée vers main ; B15/B16 gardent leurs bases de prérequis. Synchroniser les têtes, attendre la CI exacte et l'approbation humaine avant intégration, puis supprimer uniquement les temporaires intégrés sans PR dépendante. La correction README et la preuve du premier nettoyage sont publiées dans #31. B14/B15/B16/B17 restent En cours dans Systalink ; 22 lots À faire. Aucune carte distante modifiée ici.

Prochain lot : **B18 — Propositions de solution**, après coordination des revues et prérequis ; une préparation séparée pendant revue reste possible dans le cadre de la demande de poursuivre. Les écrivains suivants doivent conserver l'ordre des verrous et la visibilité parent ; projets à raccorder lors de BC04/BC05. Le travail local des routes capsules reste dans le répertoire principal. Aucun BACKEND_GATE, frontend ni déploiement.

## Reprise B16 antérieure conservée

Lot courant : **B16**, branche `backend/communaute-entraide-b16`, worktree `.worktrees/b16`, depuis B15 `0cfcde1`. Contrat [HELP_REQUEST_EDITING.md](../../../api/HELP_REQUEST_EDITING.md), preuve [B16_EDITING.md](../../../quality/B16_EDITING.md), tableau [SYSTALINK_TASKS.md](SYSTALINK_TASKS.md). SHA final et CI vérifiés à communiquer après commit dans le bilan et la PR.

Livraison : édition partielle avec revalidation du contenu final sous verrou, version incrémentée, note après contribution, publication explicite et historique paginé. Notes des brouillons privées même après publication ; audit sans texte/code, rollback atomique. Validation B14 mutualisée ; aucune migration de Madina ni dépendance modifiée. Les futures commandes B17/B18/B19 et modération devront respecter le verrou parent.

Ordre #23 → #22 → #24 → #25 → PR B16, temporairement basée sur `backend/communaute-entraide-b15`. Intégrer les prérequis avant reciblage/synchronisation et nouveaux contrôles ; aucun merge/revue humaine présumé. PR #12 et modification locale des routes capsules dans le répertoire principal préservées. Garder les trois branches permanentes et main après intégrations autorisées et nettoyage des temporaires vérifiés.

Prochain lot **B17 — Commentaires**, après intégration des prérequis ou préparation séparée sur demande de continuer pendant revue. B11/B14/B15/B16 restent En cours dans Systalink ; 23 lots à faire. Aucun GO_FRONTEND/GO_PRODUCTION.

## Reprise B15 antérieure conservée


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
