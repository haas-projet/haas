# B27 — Réception des kits versionnés

## Périmètre et état

Reprise de la PR #36 en brouillon, candidat initial `f5949de5ccf3dbaab7e21fdc8939996ab0464e2c`, base B26 `b568a511abf99ef606090816d5e9ad93d32c6335`. Cette preuve conserve les incidents et les étapes ; elle ne vaut ni approbation humaine, ni fusion, ni BACKEND_GATE.

La première tranche, commit local `13ae33e02f21b9a1a700647da50820ae88cd37b7`, livre la bibliothèque de lecture vérifiée et ses tests. La seconde tranche corrige les accès, les notices et leur registre SQL ; ses contrôles ciblés sont reçus ci-dessous. Les prérequis B24/B25/B26 corrigés devront encore être synchronisés par l'intégrateur avant la réception complète.

## Bibliothèque de fichiers vérifiés — 11 octobre 2026

- Registre serveur exclusivement : clés ASCII bornées sous `storage/app/private/artifacts/kits` et `artifacts/notices`. Aucun chemin fourni par une requête, aucune URL, aucun shell, aucune extraction ni exécution de code.
- Racine et composants doivent rester canoniques, sans lien symbolique/junction. Fichier régulier uniquement ; relecture des métadonnées après ouverture et invalidation du cache realpath.
- Kit : 20 Mio maximum ; notices : 1 Mio maximum. Taille exacte et SHA-256 des octets copiés contrôlés. Une copie `php://temp` bornée possède les octets vérifiés ; la réponse ne rouvre jamais le chemin source.
- Source fermée dans `finally`, copie fermée en erreur, après émission, lors d'une fermeture explicite ou à la destruction de l'objet. Une copie déjà autorisée n'est pas une garantie de révocation des octets déjà transmis.

Contrôle réel : `node .worktrees/run-pr-review-20261011.cjs b27 vendor/bin/phpunit tests/Unit/Capsules/VerifiedArtifactFilesTest.php` depuis la racine de travail. **24 tests / 52 assertions réussis**, PHP 8.5.10 sous Windows. Traversal/URL/namespace/segments interdits, tailles et copie après suppression ; vraies junctions sur racine, parent et parent remplacé après mise en cache. Sous Linux les mêmes cas créent de vrais symlinks, dont le fichier final ; cette exécution Linux reste à recevoir de la CI.

Les fichiers sont tous fictifs et limités au `storage/framework/testing` de ce worktree. Les unités utilisent un disque réel UUID via `Storage::set/build` : `Storage::fake` ignore un `root` fourni et nettoie son répertoire commun, ce qui avait perturbé deux ciblés SQL lancés en parallèle. Une première tentative de nettoyage par PowerShell demandait une confirmation ; la suppression d'une junction seule utilise désormais `Directory.Delete(false)` sur le chemin de fixture vérifié. Ces essais intermédiaires ne sont pas présentés comme réussis.

## Défauts reproduits avant correction des accès

Cinq probes réels, **5 tests / 7 assertions, cinq échecs** sur le comportement initial : émission 200 pour kit absent, notice absente ou octets modifiés ; stream 200 pour fichier corrompu après émission ; exposition du champ de registre `notices_path`. La fixture initiale calculait en outre le digest du chemin au lieu des octets et ne créait aucun fichier de notices. Elle a été corrigée sans prétendre constituer un vrai kit de production ni une licence approuvée.

La nouvelle garde de version avec un verrou enfant `FOR SHARE` a reproduit une désactivation SQL en deadlock **40P01**, 1 test / 3 assertions en échec. Le contrôle utilise maintenant le verrou de version commun au trigger, puis lit le registre par MVCC sans attendre le tuple enfant détenu par le désactivateur. La course réelle passe dans les 66 ciblés finaux.

## Accès, notices et audit — 11 octobre 2026

- Le service relit le membre actuel sous `FOR SHARE`, puis la capsule et la version sous `FOR UPDATE`. La Policy contrôle les trois rattachements, le compte actif et vérifié, la capsule visible, la version publiée et la distribution approuvée. Aucun bypass administrateur ; compte supprimé, suspendu ou dévérifié et retrait/masquage/désactivation coupent les anciens liens.
- Les signatures relatives concernent une cible, une version, un artefact et un membre précis, TTL 300 secondes. L'origine vient de `APP_URL` validé et pas du Host reçu. Les paramètres supplémentaires sont refusés. Authentification par session et règles SPA/CORS du socle conservées ; les trois routes partagent 30 accès/minute/membre et 60/minute/IP.
- L'émission vérifie réellement le kit et les notices avant les liens et l'audit. Le stream recontrôle les deux fichiers et les permissions, puis transmet seulement la copie dont les octets ont été vérifiés. Les notices ont une route signée, une taille et un SHA-256 publics ; aucun chemin serveur ne sort de la Resource. Réponses privées sans cache, téléchargement en pièce jointe et `nosniff`.
- Une migration additive enregistre l'empreinte et la taille des notices sans inventer celles des anciennes données. Les métadonnées du kit et des notices deviennent immuables dès la publication, y compris après retrait. La désactivation de distribution reste permise. Un ancien kit sans notices vérifiables reste indisponible. Le rollback verrouille les tables et refuse de perdre ces empreintes ou un historique de kit publié.
- Le verrou de version sérialise les révisions d'audit. Un défaut d'origine ou de signature doit annuler toute l'émission : les deux liens sont construits dans la transaction avant l'audit. Aucun chemin ni lien signé n'est copié dans le journal.

Défaut d'atomicité reproduit avant correction : **1 test / 2 assertions, un échec**, réponse 404 mais une révision `download_issued` conservée après l'échec de construction des liens. Le contrôle final vérifie réponse 404 et zéro révision. Autre probe : **9 tests / 17 assertions, cinq échecs** sur des origines avec espace, antislash, tabulation, saut de ligne ou hôte encodé ; `parse_url` seul était permissif. `FILTER_VALIDATE_URL` précède maintenant l'analyse ; les quatre origines valides, dont localhost et IPv6, restent admises.

### Contrôles ciblés réels

Toutes les commandes PHP passent par `node .worktrees/run-pr-review-20261011.cjs b27` depuis la racine ; le runner exporte explicitement PostgreSQL 17 à `127.0.0.1:55447`, base `haas_b27_review_test`, rôle `haas_test`. Aucun autre service ou base n'est modifié. Les totaux ci-dessous se recouvrent et ne s'additionnent pas en une réception complète.

| Commande après le préfixe du runner | Résultat |
|---|---|
| `vendor/bin/phpunit tests/Integration/Capsules/ArtifactDownloadConcurrencyTest.php tests/Integration/Capsules/ArtifactPublicationSchemaTest.php tests/Integration/Capsules/ArtifactDownloadHttpTest.php tests/Integration/Capsules/ArtifactIssuanceAtomicityTest.php --log-junit storage/logs/b27-access-final.xml` | **66 tests / 258 assertions OK** : 14 courses PostgreSQL avec attentes réelles, 17 cas SQL, 34 HTTP et l'atomicité. |
| `vendor/bin/phpunit tests/Unit/Capsules/ArtifactLinksTest.php tests/Unit/Capsules/VerifiedArtifactFilesTest.php --log-junit storage/logs/b27-files-links-final.xml` | **33 tests / 69 assertions OK**, après le durcissement final de l'origine. |
| `vendor/bin/phpunit tests/Integration/Capsules/ArtifactIssuanceAtomicityTest.php tests/Feature/ApiInventoryTest.php tests/Architecture/DomainBoundariesTest.php tests/Feature/CapsuleModelProtectionTest.php` | **7 tests / 127 assertions OK**, après le durcissement final de l'origine. |
| `vendor/bin/pint --dirty` | `passed`. |
| `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | Aucun défaut, niveau 8 ; aucune baseline ajoutée. |
| `../scripts/generate-api-types.php --check` | **66 types** à jour ; aucune réponse B27 nullable, car une notice manquante interdit l'émission. |
| Composer PHAR absolu : `validate --strict`, `check-platform-reqs`, `audit --locked` | Réussis, aucun advisory ; messages de dépréciation du PHAR externe sous PHP 8.5 conservés. |

`node scripts/validate-pack.mjs` : **18/18** ; `node scripts/check-deployment-docs.mjs` : **7/7**. Ces contrôles de documents ne constituent pas un contrôle du serveur hébergé. Des premiers ciblés perturbés par le disque de fixture commun, un chemin de fichier de test incorrect, un YAML non quoté puis les alertes PHPStan initiales ont été corrigés et relancés ; ils ne sont pas comptés comme réussis.

## Limites de distribution

ARB05 n'est pas approuvé par ces tests. `inactive` reste le défaut ; aucun endpoint n'approuve une licence ou la distribution, aucun artefact de production n'est créé. Des notices présentes et une empreinte valide ne confèrent aucun droit de réutilisation. Aucun nouveau package ou changement de licence n'est ajouté. Un kit disponible ne prouve aucun rapport, aucune exécution ni résultat du laboratoire.

La capsule documentaire publiée reste indépendante de l'état de son propriétaire et de sa source : masquer une source ne retire pas automatiquement la capsule (COMMUNAUTE_ET_PROJETS). Le téléchargement exige le compte actuel du membre, la visibilité actuelle de la capsule, une version actuellement publiée et une distribution approuvée non révoquée. Les anciens liens doivent être contrôlés à nouveau à chaque accès.

## Réception complète

En attente de la synchronisation des prérequis corrigés, des suites complètes locales et de la CI du head exact, notamment les symlinks Linux. Publication et état de PR confiés à l'intégrateur. Revue humaine, Qodana, BACKEND_GATE, GO_FRONTEND et GO_PRODUCTION non reçus.

## Correction de répétabilité après synchronisation — 11 octobre 2026

Le candidat synchronisé `134ebe0dab1de7454a266c619e1d10ca98b1d29e` reprend B25/B26 corrigés. L'intégrateur a interrompu deux réceptions Integration B27 en erreur ; sa relance `--stop-on-error` a identifié dès `AccountMailDeliveryTest` la fonction `b27_guard_artifact` déjà présente, SQLSTATE `42723`, migration `2026_10_11_100000` ligne 18. Ces réceptions restent **NON TERMINÉES** et ne sont pas présentées comme réussies. La cause n'est pas une garde down à affaiblir : `migrate:fresh` enlève les tables et leurs triggers, mais conserve cette fonction PostgreSQL.

Une nouvelle régression dédiée, `ArtifactFreshMigrationTest`, invoque explicitement deux vrais `migrate:fresh` sur la base B27 gardée. Après chaque cycle, elle crée uniquement un kit de fixture publié et tente de modifier son empreinte : refus SQL `23514` / `artifacts_published_immutable`, digest inchangé. Le nettoyage dépose les seules fixtures sous `TestDatabaseGuard` avant rollback du schéma vide ; il ne reçoit pas un downgrade destructif de production. Avant correction, le probe a réellement donné **1 test / 1 assertion / 1 erreur SQL42723**, JUnit `b27-fresh-migration-red.xml`.

La seule modification de production est `CREATE OR REPLACE FUNCTION` au lieu de `CREATE FUNCTION` dans cette migration. Le corps de garde, l'ordre des verrous, le trigger, les contraintes et les refus down restent inchangés. Première relance du probe après ce correctif : **1 test / 11 assertions, réussi**, JUnit `b27-fresh-migration-green.xml`.

PHPStan a ensuite trouvé trois alertes dans le nouveau test : retour `PendingCommand|int` du helper `artisan` et `errorInfo` potentiellement nul. Le ciblage plus large a été arrêté avant édition et reste **NON TERMINÉ** (`b27-fresh-migration-final-targeted.xml`). Le test appelle maintenant `Artisan::call`, vérifie son code entier et accède au SQLSTATE avec présence vérifiée, sans cast, ignore ou baseline. PHPStan niveau 8 et Pint `--test` sont verts sur cette version ; `../scripts/generate-api-types.php --check` constate **67 types à jour** sur les contrats composés.

La réception complète et la CI du nouveau SHA restent à recevoir de l'intégrateur après le microcommit. ARB05 reste inactive ; aucune approbation humaine, publication, fusion de PR, DONE, gate ou livraison frontend n'est annoncée.

Contrôles ciblés définitifs de cette correction, via le même runner B27 / PHP 8.5.10 / PostgreSQL 17 et la seule base `haas_b27_review_test` :

- `vendor/bin/phpunit --testsuite Integration --filter 'ArtifactFreshMigrationTest|ArtifactPublicationSchemaTest|ArtifactDownloadHttpTest|ArtifactDownloadConcurrencyTest|ArtifactIssuanceAtomicityTest|AccountMailDeliveryTest' --colors=never --log-junit storage/logs/b27-fresh-migration-final-targeted-v2.xml` : **69 tests / 285 assertions, réussi**. Inclut les deux cycles frais, l'immuabilité kit/notices et les refus down, les accès HTTP/audit, les courses réelles PostgreSQL et le premier test initialement en erreur dans la réception globale.
- `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` : niveau 8, aucune erreur.
- `vendor/bin/pint --test` : réussi.
- `../scripts/generate-api-types.php --check` : 67 types à jour.
- `node scripts/validate-pack.mjs` et `node scripts/check-deployment-docs.mjs` depuis le worktree : 18 et 7 contrôles documentaires réussis, aucun test applicatif ou serveur.

Les ciblages précédents se recouvrent ; ne pas additionner leurs nombres. La lecture indépendante de `pr_capsule_versions` n'a trouvé aucun défaut concret dans le diff minimal et la nouvelle régression ; elle n'est pas comptée comme un test. Journaux et JUnit restent ignorés. Le SHA du microcommit sera transmis après sa création ; la suite complète B27 doit être relancée par l'intégrateur sur ce code corrigé.

## Composition B25/B27 : ordre de la fixture de publication

Au candidat documentaire `8fe85fe4f91fc6f2878b1881839c46985bfc5f3a`, la réception Integration de l'intégrateur s'est arrêtée avec **245 tests / 1323 assertions / 1 erreur**, SQL `23514` dans `CapsulePublishHttpTest::test_new_draft_after_publication_preserves_old_digest_and_proofs`. Cette réception globale reste en échec et ne valide aucune suite complète. Avant toute relance, son JUnit `b27-final-integration-20261011.xml` a été copié vers `b27-final-integration-20261011-before-fixture-fix.xml`, original conservé ; les deux fichiers ont le même SHA-256 `5f30779dc3110a5f4e8d5eb2853d80d8347e1c2ed1c424c2a4f64178d7e27011`. Ces journaux restent ignorés.

Reproduction ciblée réelle sur le runner/base B27 : **1 test / 1 assertion / 1 erreur**, JUnit `b27-publication-artifact-fixture-red.xml`. La fixture ajoutait un artefact à une version déjà publiée, ce que la garde B27 refuse correctement avec `artifacts_published_immutable`. La recherche des insertions d'artefacts dans les tests et supports n'a trouvé qu'une autre insertion après publication : le probe négatif explicite de `ArtifactPublicationSchemaTest`, qui vérifie justement ce refus. Les autres fixtures positives enregistrent le kit pendant le brouillon ou la revue.

La fixture corrigée enregistre maintenant son artefact sur la version en revue, puis publie réellement par le Service B25 avec un modérateur indépendant, et crée le brouillon suivant avec le propriétaire. Elle vérifie en plus la conservation de tous les attributs de l'ancien artefact ; le corps et l'empreinte publiés restent conservés, aucune preuve n'est recopiée sur le nouveau brouillon. Seul ce test est modifié dans le backend. Relance du scénario : **1 test / 8 assertions, réussi**, JUnit `b27-publication-artifact-fixture-green.xml`. PHPStan niveau 8 et Pint `--test` réussissent sur ce correctif. Les protections de production et les refus d'insertion après publication sont conservés.

Ciblage de composition réellement terminé, via le runner B27 : `vendor/bin/phpunit --testsuite Integration --filter 'CapsulePublishHttpTest|CapsulePublicationNotificationsTest|CapsulePublicationSchemaTest|CapsuleWritesConcurrencyTest|ArtifactPublicationSchemaTest|ArtifactsSchemaTest|ArtifactFreshMigrationTest' --colors=never --log-junit storage/logs/b27-publication-artifact-composition-final.xml` : **74 tests / 436 assertions, zéro erreur, échec ou skip**. Inclut le scénario corrigé, la publication/notification/journal/concurrence B25 et les contraintes/immuabilité/fresh des artefacts B22/B27. Les résultats ciblés se recouvrent et ne s'additionnent pas. La lecture indépendante de `pr_capsule_versions` n'a trouvé aucun défaut concret dans cette fixture et n'est pas comptée comme un test. Réception SQL complète B27 et CI exacte restent à reprendre par l'intégrateur ; aucune approbation humaine, fusion, DONE ou gate.

## Réception complète B27 sur la CI exacte — 11 octobre 2026

Source reçue : `bef006c481ed8f2692277b3c91cab1a9de13fb1c`, [CI 38162823982](https://github.com/haas-projet/haas/actions/runs/38162823982). Les deux journaux PHP ont chacun été lus séparément et leur attribution aux étapes réussies vérifiée. Les trois jobs, y compris backend-ci, sont terminés avec succès.

| Job | Unit/Feature/Architecture | Integration PostgreSQL | Total disjoint | Types | Empreintes couvertes |
|---|---|---|---|---|---|
| [PHP 8.4 / PostgreSQL 17](https://github.com/haas-projet/haas/actions/runs/38162823982/job/114539132244) | 454/4351 | 708/5028 | 1162/9379 | 67 | 798 |
| [PHP 8.5 / PostgreSQL 17](https://github.com/haas-projet/haas/actions/runs/38162823982/job/114539132046) | 454/4351 | 708/5028 | 1162/9379 | 67 | 798 |

Les suites complètes ont été exécutées sur les runners Linux avec PostgreSQL 17. Aucun ciblé antérieur n’est ajouté au total **1162 tests / 9379 assertions par version PHP**. PHPStan, Pint, Composer et les contrôles documentaires du workflow passent. Les 67 types et les 798 empreintes couvrant tous les fichiers suivis sont confirmés par chacun des journaux.

### Résultats locaux distincts

- PHP 8.5.10 : Unit/Feature/Architecture **454 tests / 4351 assertions**, erreurs/échecs/skips=0, JUnit ignoré b27-final-unit-20261011.xml. Commande via le runner dédié : vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b27-final-unit-20261011.xml.
- Composition ciblée de la fixture corrigée : **74 tests / 436 assertions**, avec les statiques reçus, détaillée dans l’addendum précédent ; ces tests ne sont pas ajoutés à la CI.
- Le premier résultat PostgreSQL en échec **245/1323/1 erreur** est conservé dans b27-final-integration-20261011-before-fixture-fix.xml. La nouvelle protection refusait l’insertion du kit après publication ; la fixture a été corrigée avant publication, sans modifier la protection de production.
- La relance locale complète suivante a été **interrompue sans résultat final** : dernier suivi 189/708 puis 21 points, canal 33025 devenu inconnu, processus B27 absent, JUnit final vide (0 octet), puis pg_ctl status indique aucun serveur sur le cluster de tests. Cette exécution n’est pas reçue comme réussite. La cause de l’interruption du processus n’est pas déduite de ces observations. La récupération PostgreSQL conserve le répertoire existant ; elle est distincte des résultats CI.

Les commandes locales de documentation passent : node scripts/validate-pack.mjs **18/18**, node scripts/check-deployment-docs.mjs **7/7**, node scripts/check-manifest.mjs **798**, git diff --check. Le commit de bilan conserve les octets applicatifs du candidat ; ses changements sont documentaires. Une nouvelle CI du SHA de bilan sera consignée dans #36 après publication.

B27 IN_REVIEW pour le backend reçu sur CI. ARB05 reste inactive sans décision réelle ; la distribution est inactive par défaut. Le prérequis B26 reste partiel pour B33/badge/has_lab/Q16, et #35/#36 restent en brouillon. Aucun résultat complet PostgreSQL Windows, avis humain, fusion de PR, BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION, Qodana ou déploiement n’est déclaré. Racine modifiée et travail B18 conservés.
