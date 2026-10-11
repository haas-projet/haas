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
