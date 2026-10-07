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

## 7 octobre 2026 — Synchronisation B14 avec main

- À la demande de l'utilisateur, récupération par merge normal de `origin/main` à `f1f6238` (B11 intégré et B35 présent), depuis B14 `6a0db1e`, dans un worktree isolé. Aucun historique réécrit.
- Seul conflit : `docs/AI_USAGE.md`, résolu en conservant les entrées B14 et B35. Aucun conflit de code ; les fichiers applicatifs récupérés proviennent de main. Le travail préexistant du répertoire principal est préservé.
- Contrôles réellement exécutés : `node scripts/validate-pack.mjs` 18/18 ; `node scripts/check-deployment-docs.mjs` 7/7 ; `php scripts/generate-api-types.php --check` avec PHP 8.5.10, 36 types à jour. Les premières tentatives de génération utilisaient un ancien chemin PHP absent puis PHP 8.3.12, refusé par la plateforme ; le contrôle final sous PHP 8.5 réussit.
- `SHA256SUMS` régénéré depuis les fichiers Git versionnés, hors lui-même et chemins ignorés. Contrôle de whitespace avant commit ; SHA réel et CI du nouveau head à communiquer dans le bilan après publication.
- Suites Laravel hors SQL et PostgreSQL non réexécutées sur cette branche pendant cette synchronisation. La CI PHP 8.4/8.5 du nouveau head reste à observer ; revue humaine requise. Aucun gate ni statut DONE ajouté par ce merge.
- Prochaine action : synchroniser B15 depuis B14, puis B16 depuis B15, et poursuivre les revues des PR #24/#25/#26 dans l'ordre des prérequis.
