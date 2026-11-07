# B18 — Socle des propositions — 11 octobre 2026

## Périmètre reçu localement

Branche `backend/communaute-entraide-b18-propositions`, worktree isolé `.worktrees/lamine-b18-20261008`, depuis main `d0d47c5dd62887fcf18361f499a33a800538b2b1`. Les neuf fichiers de préparation préexistants sont conservés et complétés ; le fichier modifié de routes capsules du répertoire principal est exclu. Assistance Codex, sans identité ni approbation de Lamine inventée.

Ce premier commit reçoit les données, modèles, validation, Policy, Queries et migration additive. **B18 reste IN_PROGRESS** : aucune route HTTP de propositions, transition automatique, commande idempotente ou notification de proposition n'est encore livrée dans ce socle. B19, frontend et déploiement restent hors de ce changement.

- Quatre descriptions obligatoires, 20–4 000 caractères après trim Unicode ; une URL ou des liens seuls, y compris listes numérotées et Markdown de référence, sont refusés. Les caractères de format seuls ne remplissent pas la longueur minimale ; les caractères internes d'un texte valide restent conservés.
- Code facultatif, nullable, 12 000 caractères au maximum, conservé octet pour octet par le DTO et jamais exécuté. UTF-8, caractères de contrôle et motifs connus de secrets vérifiés ; cette détection ne garantit pas l'absence de tout secret possible.
- Champs serveur et clés inconnues refusés par le DTO ; version entière bornée pour une édition future. Aucun acteur ni état fourni par le client.
- Visibilité SQL avant total/pagination/détail/historique : parent publié et visible, comptes auteurs actifs vérifiés, proposition non masquée. Auteur seul pour éditer une proposition `proposed` sur un parent ouvert/en cours ; aucun bypass admin, aucune édition `accepted` ou `not_selected`.
- Migration : code/dates nullable sans backfill ; historique unique par proposition/version, FK restrictive, contraintes de taille/état/version. `before_edit` peut conserver fidèlement un ancien texte non conforme, sans inventer les versions précédentes. Aucun historique n'est créé automatiquement à la migration.
- Down transactionnel avec verrous : refus dès qu'un code, une date ou une révision existe, même pour un code vide. Les tests B11/B05 déposent les enfants avant leurs parents ; aucune FK ni migration historique assouplie.

## Défauts réellement reproduits

Sur PostgreSQL, les anciens tests de composition B11/B05 échouaient avec `2BP01` car `proposal_revisions` restait liée à `proposals` : 1 test / 11 assertions / 1 erreur pour B11, puis 1 / 0 / 1 pour B05. Seul l'ordre de préparation des tests est corrigé.

Tests règle/DTO : les cinq nouveaux cas (WORD JOINER, padding invisible, liste numérotée d'URL, lien de référence, URL parenthésée avec titre) échouaient réellement, **81 tests / 254 assertions / 10 échecs**, avant correction. Le parseur CommonMark déjà installé reconnaît les liens sans fetch réseau ; aucune dépendance ajoutée. Les premiers tests SQL nouveaux révélaient cinq assertions mal préparées comparant des dates Eloquent avant relecture aux dates PostgreSQL ; les snapshots sont maintenant pris après relecture, sans changer les attentes de conservation.

## Commandes exécutées et résultats

PHP 8.5.10 ; PostgreSQL 17, cluster local réservé aux revues, `127.0.0.1:55447`, application `haas_b18_review_test`. Le runner ignoré `.worktrees/run-pr-review-20261011.cjs` lit uniquement la configuration de test locale, contrôle environnement/connexion/base exacte et exporte ces valeurs avant PHPUnit. Aucun ciblage de la base Laragon sur 5432 ni de production.

Commandes depuis la racine, avec le runner devant les exécutables PHP :

```text
node .worktrees/run-pr-review-20261011.cjs b18 vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b18-foundation-nonsql-final-20261011.xml
node .worktrees/run-pr-review-20261011.cjs b18 vendor/bin/phpunit --testsuite Integration --filter 'ProposalRevisionsMigrationTest|HelpRequestsMigrationTest|IdentityMigrationTest|ProposalVisibilityPolicyTest' --colors=never --log-junit storage/logs/b18-foundation-postgres-final-20261011.xml
node .worktrees/run-pr-review-20261011.cjs b18 vendor/bin/phpstan analyse --memory-limit=512M --no-progress
node .worktrees/run-pr-review-20261011.cjs b18 vendor/bin/pint --test
node .worktrees/run-pr-review-20261011.cjs b18 ../scripts/generate-api-types.php --check
node .worktrees/run-pr-review-20261011.cjs b18 C:/ProgramData/ComposerSetup/bin/composer.phar validate --strict
node .worktrees/run-pr-review-20261011.cjs b18 C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs
```

| Contrôle | Résultat réel |
|---|---|
| Unit + Feature + Architecture complètes | **472 tests / 4 164 assertions**, aucun échec/erreur/skip |
| Intégration ciblée migrations + accès B18 | **51 tests / 308 assertions**, aucun échec/erreur/skip |
| Total disjoint de ces deux commandes | **523 tests / 4 472 assertions** |
| PHPStan niveau configuré 8 | Aucune erreur, aucune baseline ajoutée |
| Pint | Réussi après formatage ciblé |
| Types API | 51 à jour, aucune nouvelle API annoncée |
| Composer validate / platform | Réussis ; avertissements de dépréciation internes au Composer installé sous PHP 8.5 conservés |
| `node scripts/validate-pack.mjs` dans le worktree | 18/18 documentaires |
| `node scripts/check-deployment-docs.mjs` dans le worktree | 7/7 documentaires, aucun serveur testé |

La suite Integration complète et la CI du nouveau commit B18 ne sont **pas exécutées pour ce premier socle**. Les tests SQL ciblés ne les remplacent pas. Composer audit non relancé ici ; dépendances et lockfile inchangés. Qodana, BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION, application accessible en ligne et revue humaine non reçus.

## Prochaine tranche

Creation/édition idempotentes avec comptes puis parent sous verrous, première transition `open → in_progress` une fois, version parent incrémentée seulement à cette transition, révisions/audits/outbox dans la transaction. Recontrôler les droits au rejeu ; refuser l'édition d'une proposition acceptée ; projections Markdown et code inertes, contrat OpenAPI et tests HTTP/concurrence réelle. Définir explicitement le traitement d'un éventuel secret legacy avant de le capturer dans un historique public. Aucun résultat métier n'est certifié par une proposition.
