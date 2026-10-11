# B27 — Réception des kits versionnés

## Périmètre et état

Reprise de la PR #36 en brouillon, candidat initial `f5949de5ccf3dbaab7e21fdc8939996ab0464e2c`, base B26 `b568a511abf99ef606090816d5e9ad93d32c6335`. Cette preuve conserve les incidents et les étapes ; elle ne vaut ni approbation humaine, ni fusion, ni BACKEND_GATE.

La première tranche livre la bibliothèque de lecture vérifiée et ses tests. La correction des routes, des permissions courantes, de l'audit et du registre SQL reste à recevoir dans les tranches suivantes, puis les prérequis B24/B25/B26 corrigés devront être synchronisés avant la réception complète.

## Bibliothèque de fichiers vérifiés — 11 octobre 2026

- Registre serveur exclusivement : clés ASCII bornées sous `storage/app/private/artifacts/kits` et `artifacts/notices`. Aucun chemin fourni par une requête, aucune URL, aucun shell, aucune extraction ni exécution de code.
- Racine et composants doivent rester canoniques, sans lien symbolique/junction. Fichier régulier uniquement ; relecture des métadonnées après ouverture et invalidation du cache realpath.
- Kit : 20 Mio maximum ; notices : 1 Mio maximum. Taille exacte et SHA-256 des octets copiés contrôlés. Une copie `php://temp` bornée possède les octets vérifiés ; la réponse ne rouvre jamais le chemin source.
- Source fermée dans `finally`, copie fermée en erreur, après émission, lors d'une fermeture explicite ou à la destruction de l'objet. Une copie déjà autorisée n'est pas une garantie de révocation des octets déjà transmis.

Contrôle réel : `node .worktrees/run-pr-review-20261011.cjs b27 vendor/bin/phpunit tests/Unit/Capsules/VerifiedArtifactFilesTest.php` depuis la racine de travail. **24 tests / 52 assertions réussis**, PHP 8.5.10 sous Windows. Traversal/URL/namespace/segments interdits, tailles et copie après suppression ; vraies junctions sur racine, parent et parent remplacé après mise en cache. Sous Linux les mêmes cas créent de vrais symlinks, dont le fichier final ; cette exécution Linux reste à recevoir de la CI.

Les fichiers sont tous fictifs et limités au `storage/framework/testing` de ce worktree. Les unités utilisent un disque réel UUID via `Storage::set/build` : `Storage::fake` ignore un `root` fourni et nettoie son répertoire commun, ce qui avait perturbé deux ciblés SQL lancés en parallèle. Une première tentative de nettoyage par PowerShell demandait une confirmation ; la suppression d'une junction seule utilise désormais `Directory.Delete(false)` sur le chemin de fixture vérifié. Ces essais intermédiaires ne sont pas présentés comme réussis.

## Défauts reproduits avant correction des accès

Cinq probes réels, **5 tests / 7 assertions, cinq échecs** sur le comportement initial : émission 200 pour kit absent, notice absente ou octets modifiés ; stream 200 pour fichier corrompu après émission ; exposition du champ de registre `notices_path`. La fixture initiale calculait en outre le digest du chemin au lieu des octets et ne créait aucun fichier de notices. Elle a été corrigée sans prétendre constituer un vrai kit de production ni une licence approuvée.

La nouvelle garde de version avec un verrou enfant `FOR SHARE` a reproduit une désactivation SQL en deadlock **40P01**, 1 test / 3 assertions en échec. Le contrôle doit utiliser le verrou de version commun au trigger, puis lire le registre par MVCC sans attendre le tuple enfant détenu par le désactivateur. La réception de cette correction est à consigner après la suite ciblée finale.

## Limites de distribution

ARB05 n'est pas approuvé par ces tests. `inactive` reste le défaut ; aucun endpoint n'approuve une licence ou la distribution, aucun artefact de production n'est créé. Des notices présentes et une empreinte valide ne confèrent aucun droit de réutilisation. Aucun nouveau package ou changement de licence n'est ajouté. Un kit disponible ne prouve aucun rapport, aucune exécution ni résultat du laboratoire.

La capsule documentaire publiée reste indépendante de l'état de son propriétaire et de sa source : masquer une source ne retire pas automatiquement la capsule (COMMUNAUTE_ET_PROJETS). Le téléchargement exige le compte actuel du membre, la visibilité actuelle de la capsule, une version actuellement publiée et une distribution approuvée non révoquée. Les anciens liens doivent être contrôlés à nouveau à chaque accès.

## Réception complète

En attente des tranches d'accès, d'audit et SQL, de la synchronisation des prérequis corrigés, des suites complètes locales et de la CI du head exact. Publication et état de PR confiés à l'intégrateur. Revue humaine, BACKEND_GATE, GO_FRONTEND et GO_PRODUCTION non reçus.
