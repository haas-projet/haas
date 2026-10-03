# Réception partielle du socle — B39 à B44

3 octobre 2026. Responsable : ousseynoufayeisidk-sys. Branche backend/socle-auth-reception, base de code 264695dd7e8dfd23e0e0c872ff0ccd3b740d2708 (PR #19). Cette preuve couvre les opérations livrées du socle ; **BACKEND_GATE NON REÇU**, aucun GO_FRONTEND ni GO_PRODUCTION. Les exigences des domaines encore absents restent ouvertes.

## Livraisons vérifiées avant cette réception

| Lot | Commit de code | PR / CI réellement réussie | Résultat par PHP 8.4 et 8.5, PostgreSQL 17 |
|---|---|---|---|
| B32 administration | fc2785f68ea05a43c5cb4cf334e3e83daf4e97a5 | [#17](https://github.com/haas-projet/haas/pull/17), [37135203385](https://github.com/haas-projet/haas/actions/runs/37135203385) | 367 tests / 3244 assertions |
| B29 infrastructure et boîte privée | 29803cb0977b5646e0d7395cb1ecf42f1fbda456 | [#18 brouillon](https://github.com/haas-projet/haas/pull/18), [37135770117](https://github.com/haas-projet/haas/actions/runs/37135770117) | 371 tests / 3356 assertions |
| B30/B31 adaptateur profil | 264695dd7e8dfd23e0e0c872ff0ccd3b740d2708 | [#19 brouillon](https://github.com/haas-projet/haas/pull/19), [37140211552](https://github.com/haas-projet/haas/actions/runs/37140211552) | 379 tests / 3643 assertions |

Les journaux ont été lus : formatage, PHPStan niveau 8, tests sans base et SQL, audit Composer et documentation réussis. Les totaux sont ceux d'une version PHP, pas la somme artificielle des deux matrices. B32 est prêt pour revue ; B29–B31 restent partiels. Aucun avis humain ni fusion inventés.

## B39 — sécurité du périmètre disponible

Les suites B06–B13/B32/B29/B30–B31 exécutent sessions/CSRF sans neutraliser les contrôles concernés, compte non vérifié ou suspendu, IDOR des profils et notifications, file privée, champs serveur forgés, limites, version périmée, chiffrement des motifs, liste blanche d'audit et rollback SQL. Les courses utilisent deux processus PHP, avec attente de verrou constatée sur PostgreSQL, pour quota/doublon/décision/dernier administrateur et idempotence. Voir les preuves de chaque lot.

Cela ne vaut pas revue de sécurité globale : demandes, résolutions, versions/kits, laboratoire, cas/comparaisons/fiches, projets/annuaire/offres et leurs liens directs doivent être reçus avec leurs pilotes. Pas de test de rendu HTML/Markdown dans une SPA inexistante, ni de qualification du runner ou des domaines réels. B39 IN_PROGRESS, réception transversale bloquée par les prérequis métier, notamment BH10.

## B40 — contrat du socle et types sans SPA

ApiInventoryTest compare les opérations OpenAPI aux routes réelles API/auth/health dans les deux sens et impose des operationId distincts. Les tests de contrat propres aux domaines complètent cet inventaire ; il ne prouve pas à lui seul toutes les formes de réponse.

`scripts/generate-api-types.php` génère les 28 schémas nommés actuellement présents dans OPENAPI.yaml et ses fragments. `--check` et GeneratedApiTypesTest refusent un fichier périmé. Artefact : docs/api/generated/haas-api.d.ts. Il représente les formes JSON (refs, unions, intersections, enums, champs requis), pas une validation d'exécution, de formats, de bornes, d'exclusivité de oneOf ou de droits. Les types des opérations encore absentes restent à définir ; aucune application frontend n'a été créée.

Commandes locales réussies : `php scripts/generate-api-types.php --check` ; compilation `tsc --noEmit --strict --skipLibCheck false docs/api/generated/haas-api.d.ts`. TypeScript 5.9.3 installé uniquement dans un répertoire temporaire, scripts d'installation désactivés, licence Apache-2.0 et intégrité relevées depuis le registre npm officiel. Aucune dépendance npm/runtime ajoutée au dépôt. La CI vérifie la fraîcheur de l'artefact, pas la compilation TypeScript (preuve locale distincte).

## B41 — parcours réellement exécutable

ModerationTest relie profil → signalement privé → revue → décision de masquage → retrait de l'URL publique → livraison différée → notification privée du propriétaire. La transaction ne produit aucun message avant livraison ; l'édition personnelle ne republie pas le profil. Le contrôle de santé vérifie aussi la reprise de la livraison.

Le parcours demandé A demande → B proposition → A accepte → capsule → C teste/réutilise reste **NON EXÉCUTÉ** : ses domaines sont absents de cette branche. Aucun AC32 ou comparaison B1 déclaré reçu ; aucune fixture ne simule ces fonctionnalités pour faire passer le gate.

## B42 — exploitation et restauration locales

Nouveau `php artisan ops:check` : connexion SQL, jobs échoués, retard de notifications et courriels account-mail. Sortie JSON bornée sans payload/identité/hôte ; exit 0 sain, exit 1 retard ou indisponibilité. Les trois tests SQL vérifient état sain, retard/reprise, panne de stockage, jobs différés et autres queues non confondus avec un courriel en retard. Runbook : [OPERATIONS_RUNBOOK.md](../deployment/OPERATIONS_RUNBOOK.md).

Exercice réel avec scripts/ops/exercise-backup.php, PHP 8.5.10/OpenSSL, PostgreSQL 17.0, loopback 127.0.0.1:54693, rôle haas_test. Source haas_backup_source_test créée pour l'exercice ; cible haas_backup_restore_test neuve et vide. Données fictives créées via les services livrés : profil, rapport, décision, notification livrée et suspension. Dump custom uniquement en mémoire, AES-256-GCM, clé d'archive de 32 octets séparée d'APP_KEY. Clés conservées hors dépôt, jamais affichées. Authentification et empreinte avant restauration ; pg_restore atomique sans --clean.

| Données vérifiées | Source | Après restauration |
|---|---:|---:|
| users | 3 | 3 |
| profiles | 1 | 1 |
| account_decisions | 1 | 1 |
| content_revisions | 5 | 5 |
| reports | 1 | 1 |
| report_decisions | 1 | 1 |
| internal_notifications | 1 | 1 |

Vérifications supplémentaires réussies : compte suspendu/security_version=1 ; profil masqué/lock_version=2 ; signalement résolu ; détail déchiffrable avec l'APP_KEY séparément conservée. Refus effectifs (exit 1) : archive altérée, mauvaise clé, cible déjà remplie ; haas_bad_restore_test reste sans table. Clé absente : message générique seul, sans warning contenant le chemin. Aucune base applicative ni serveur existant ciblé, aucun DROP.

Limites : archive bornée à 64 Mio, mémoire nécessaire ; outil d'exercice local seulement. Sauvegarde distante, politiques de rétention, restauration du domaine demandes/versions, rollback de release, supervision et configuration distincte du worker/runner sur VPS réel **NON EXÉCUTÉS**. B42 partiel, contrôle distant bloqué faute d'accès/capacités vérifiés ; aucun déploiement.

## Commandes finales de cette branche

- PHP 8.5.10 : `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --no-progress` : 251 tests / 2452 assertions réussis.
- PostgreSQL dédié haas_socle_test : `vendor/bin/phpunit tests/Integration/OperationsCheckTest.php --no-progress` : 3 tests / 11 assertions réussis.
- `vendor/bin/pint --test` et `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` : réussis (niveau 8). Une ambiguïté de type sur timestamp dans la nouvelle fixture a été corrigée en utilisant getTimestamp(), sans suppression de diagnostic.
- Syntaxe des deux scripts et fraîcheur des types : réussies. Contrôles documentaires validate-pack 18/18, check-deployment-docs 7/7 et git diff --check réussis. La CI de cette branche reste à consigner après publication ; ne pas attribuer la CI de #19 au code nouveau.

## B43 et B44 — réserves bloquantes

B43 : Ultimate déclaré disponible, mais aucun projet/token configuré pour ce dépôt au constat (`gh secret list` retourne une liste vide). Projet demandé à l'utilisateur, aucun jeton dans le chat. Pas de Qodana exécuté, pas de licence/digest inventé, pas de dérogation acceptée. Pint/PHPStan/tests/audit continuent de fonctionner sans être rebaptisés Qodana. B43 BLOCKED.

B44 : toutes les fonctions P0 F01–F18 ne sont pas livrées, B39–B42 incomplets, Qodana sans décision, aucune revue humaine de ces PR. [BACKEND_GATE](BACKEND_GATE.md) reste NON REÇU, B44 BLOCKED. L'exigence du skill haas-release-readiness est respectée : « Une signature humaine ou accord GO_FRONTEND doit venir d’une personne, pas être inventé. »

Prochaine reprise : intégrer les petites PR dans l'ordre après revue humaine, raccorder B10/B29/B30/B31 aux contributions des deux pilotes, compléter les tests transversaux et la recette, fournir Qodana via les secrets CI autorisés, recevoir l'exploitation sur des capacités constatées. Nettoyer uniquement les branches temporaires fusionnées et sans PR dépendante. Le tableau [Systalink](../execution/participants/ousseynoufayeisidk-sys/SYSTALINK_TASKS.md) distingue les cartes terminées de celles encore en revue ou dépendantes.
