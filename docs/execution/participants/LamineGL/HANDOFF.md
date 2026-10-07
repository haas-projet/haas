# Reprise — communauté et entraide

## 7 octobre 2026 — Correctif de simulation de session après publication B17

B17 est publié dans [#32](https://github.com/haas-projet/haas/pull/32), base B16, lot métier `238d5e9`, synchronisation `6737081`. La CI `37657236028` réussit sous PHP 8.5 mais échoue sous PHP 8.4 dans un ancien test d'idempotence après 23 heures simulées. Le navigateur de test gardait ses cookies expirés ; la collecte forcée reproduit réellement le refus CSRF 419.

Correctif limité aux tests : échéances de cookies par nom/valeur, purge avant XSRF avec l'horloge simulée, snapshots préservés. Le test de session expirée transmet encore volontairement l'ancien cookie et exige 401. Les 20 tests ciblés / 139 assertions passent, puis les suites générales complètes : hors SQL **328 / 3563**, PostgreSQL **343 / 3242**, soit **671 / 6805 distincts**. Pint et PHPStan réussissent. Preuves et commandes dans [B17_COMMENTS.md](../../../quality/B17_COMMENTS.md). La CI du nouveau head doit être observée ; aucune fusion ni approbation humaine présumée.

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

## Synchronisation du 7 octobre 2026

B11 est intégré dans `main` (`f1f6238`). B14 récupère ce main par merge normal depuis `6a0db1e` ; l'unique conflit du registre IA est résolu en gardant B14 et B35. PR #24 reciblée vers main ; B15/#25 et B16/#26 restent dépendantes et doivent être synchronisées dans cet ordre.

Preuves de cette synchronisation : contrôles documentaires 18/18 et 7/7 ; 36 types API à jour sous PHP 8.5.10 ; empreintes des fichiers versionnés actualisées ; whitespace contrôlé avant commit. SHA réel et CI du head publiés dans le bilan. Les suites Laravel et PostgreSQL de cette branche ne sont pas réexécutées ici ; les contrôles documentaires ne les remplacent pas. Revue humaine encore requise, sans BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION.

## Synchronisation B15 du 7 octobre 2026

B15 récupère B14 synchronisé (`230b83b`) par merge normal depuis `0cfcde1`. Les conflits portent sur les documents et les empreintes ; les historiques B14/B15/B35 sont conservés, sans conflit de code. Contrôles : 18/18 documentaires, 7/7 déploiement documentaire, 38 types API à jour sous PHP 8.5.10 et whitespace avant commit. Suites Laravel/PostgreSQL non réexécutées sur B15 durant cette synchronisation.

PR #25 dépend de B14/#24. Publier le SHA réel et observer ses checks PHP 8.4/8.5 ; revue humaine requise. Prochaine synchronisation : B16 depuis ce B15. Aucun BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION.

## Synchronisation B16 du 7 octobre 2026

B16 récupère B15 synchronisé (`a3eb9b9`) par merge normal depuis `8e9a60e`. Les conflits sont documentaires ; les ajouts B14/B15/B16 et B35 sont conservés, sans conflit applicatif. Contrôles exécutés : 18/18 documentaires, 7/7 déploiement documentaire, 42 types API à jour sous PHP 8.5.10 et whitespace avant commit. Empreintes actualisées depuis les fichiers versionnés ; suites Laravel et PostgreSQL non réexécutées sur B16 durant cette synchronisation.

PR #26 dépend de B15/#25, elle-même après B14/#24. SHA réel et CI PHP 8.4/8.5 du head à rapporter après publication ; revue humaine requise. B17 reste un lot séparé. Aucun BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION.

## Reprise après synchronisation B14/B15/B16 — 7 octobre 2026

B14 230b83b, B15 a3eb9b9 et B16 d3d8407 sont publiés dans leurs PR existantes. B17 métier 238d5e96593cb7dca32844b50d4077eee6f0adca est conservé ; B16 d3d84073b6b380b9ced2d8d9f68f6821636341a0 rejoint ensuite cette branche par merge normal. Les trois conflits concernent seulement AI_USAGE, PROGRESS de Lamine et les empreintes ; les historiques et notes B17 sont conservés. Aucun fichier backend ne change par cette synchronisation documentaire, selon git diff HEAD -- backend ; les tests671/6805 précédents correspondent au même code. Publier B17 vers B16 et constater sa CI exacte ; aucune fusion de PR ou revue humaine présumée.
