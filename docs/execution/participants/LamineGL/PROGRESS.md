# Suivi — communauté et entraide / LamineGL

## 3 octobre 2026 — Reprise B11

Intervention Codex à la demande de l'utilisateur pour continuer la partie de Lamine ; aucune identité Git ni revue de Lamine n'est simulée. Les contributions B11 publiées par mdev44-code à `1c380c4` sont conservées, ainsi que son suivi individuel et sa branche source. Reprise sur la branche permanente `backend/communaute-entraide`, socle `main` `7a8c672`, merge local `93009c3` sans conflit.

B11 : schéma, modèles, factories et enums présents ; compléments de versions positives, protection des dates serveur, factory cohérente avec l'auteur, tests de FK et de migrations ajoutés. Preuves et commandes : [B11_COLLABORATION.md](../../../quality/B11_COLLABORATION.md). Contrat : [COLLABORATION_DATA.md](../../../architecture/COLLABORATION_DATA.md).

Statut : en préparation de revue, pas encore fusionné. Les autres 26 lots de la coordination #2 restent à faire. Prochaine étape : publication de la PR et contrôle de sa CI, puis B14 après intégration du prérequis. Le SHA final sera communiqué dans le bilan et la PR après commit.

## 4 octobre 2026 — B14, en conservant le travail de Madina

- Demande de poursuivre en tenant compte de Madina ; confirmation explicite qu'elle n'a commencé ni B14 ni HelpIntent/BV201.
- PR #23 de Madina constatée, mêmes commits B11 déjà présents dans #22. #22 reciblée vers sa branche et présentée comme complément ; aucune clôture, fusion ou réécriture de son travail. B14 isolé depuis `5854e04` pendant les revues.
- Livraison : POST /api/v1/requests, validation/DTO/Policy/Service/Resource, modes draft/publish, question sans code, audit atomique et idempotence commune, protection des champs serveur et motifs suspects, contrat OpenAPI/types. Enum HelpIntent unique à réutiliser dans BV201/BC07.
- Tests et limites : [B14_CREATION.md](../../../quality/B14_CREATION.md). Les fixtures de concurrence sont réellement commitées ; deux processus attendent simultanément un verrou PostgreSQL. Aucun résultat métier fictif.
- Prochain lot : B15 après intégration du prérequis, ou préparation séparée sur demande explicite pendant la revue. B11/B14 ne sont pas encore des cartes Terminé.

## 2026-10-04 — B15, lecture/recherche de la partie de Lamine

- Demande de continuer après B14. Branche `backend/communaute-entraide-b15`, worktree `.worktrees/b15`, depuis `6a0db1e`. PR #23 de Madina, #22 et #24 inchangées, PR #12 préservée ; la modification préexistante des routes capsules dans le répertoire principal est exclue et conservée.
- GET liste/détail, visibilité commune avant recherche/pagination/total, brouillons réservés à leur auteur, vue mine explicite, filtres/tri bornés et chargement des relations sans N+1. Aucune migration ni dépendance ajoutée. Contrat [HELP_REQUEST_READING.md](../../../api/HELP_REQUEST_READING.md), preuve [B15_READING.md](../../../quality/B15_READING.md).
- Résultats locaux : 284 tests / 2811 assertions hors SQL et 227 / 2023 sur PostgreSQL dédié (511 / 4834). Pint/PHPStan, Composer validation/prérequis/audit et documentation réussis, 38 types à jour. Quatre SELECT métier pour 5 comme pour 25 demandes. Cluster 54695/haas_b15_test arrêté après les tests.
- B15 IN_REVIEW ; SHA final et CI à constater dans la PR et le bilan après publication. Ordre #23 → #22 → #24 → B15, sans fusion ni revue humaine simulée. B11/B14/B15 En cours dans Systalink, 24 autres lots à faire. Prochain B16, édition sous version. Aucun BACKEND_GATE ni frontend/déploiement.

## 2026-10-04 — B16, édition versionnée et historique

- Suite demandée de la partie de Lamine, préparée sur `backend/communaute-entraide-b16` depuis B15 `0cfcde1`. PR #23 de Madina, #22, #24, #25 et #12 conservées. Le fichier de routes capsules modifié dans le répertoire principal reste exclu du worktree et de cette livraison.
- PATCH partiel réservé à l'auteur, revalidation du contenu final, version et note après contribution ; publication explicite des brouillons et historique paginé. Notes de préparation privées après publication ; audit minimal, idempotence, droits actuels et rollback atomique. Migration additive et adaptation des tests de retour B05/B11 à sa nouvelle dépendance ; aucune migration de Madina réécrite.
- Contrat [HELP_REQUEST_EDITING.md](../../../api/HELP_REQUEST_EDITING.md), OpenAPI 0.15.0 et 42 types ; résultats des commandes et limites dans [B16_EDITING.md](../../../quality/B16_EDITING.md). Six scénarios concurrents utilisent deux processus réellement en attente d'un verrou PostgreSQL.
- Validation locale : 551 tests / 5515 assertions distincts, dont 267 / 2444 PostgreSQL. Pint/PHPStan, validation/prérequis/audit Composer et documentation réussis ; cluster dédié arrêté.
- B16 IN_REVIEW ; SHA final, PR et CI du commit testé à consulter dans le bilan après publication. Ordre #23 → #22 → #24 → #25 → B16 ; aucune revue humaine ou fusion présumée. B11/B14/B15/B16 En cours dans Systalink, 23 autres lots à faire. Prochain B17, commentaires sous verrou parent. Aucun BACKEND_GATE ni frontend/déploiement.
