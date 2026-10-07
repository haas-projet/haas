# Revue et intégration du travail de Madina — 7 octobre 2026

## Périmètre et autorisation

Demande explicite de l'utilisateur : vérifier puis fusionner le travail de Madina dans le dépôt distant privé `haas-projet/haas`. L'auteur GitHub des contributions examinées est `mdev44-code`. Intervention Codex (GPT-6), avec revues automatisées parallèles du code ; aucune approbation humaine inventée. La revue GitHub de #22 par `mdev44-code` est réellement présente ; elle ne constitue pas une approbation des autres PR.

Le travail préexistant sur `backend/communaute-entraide-b14`, notamment la modification locale de `backend/routes/api/capsules-lab.php`, est préservé. Les examens et tests ont lieu dans des worktrees distincts. Aucune branche distante supprimée, aucun force-push, aucun frontend ni déploiement.

## Fusions réalisées

| PR | Contenu et destination | Commit de fusion réel | CI vérifiée avant fusion |
|---|---|---|---|
| [#12](https://github.com/haas-projet/haas/pull/12) | Enums capsules/laboratoire/comparaisons → `main` | `33eafa0b495ec2fd9e8cb8cf6aa94b230ff62a18` | [37484120138](https://github.com/haas-projet/haas/actions/runs/37484120138), head `71daddf959673d42e0afe836618ada757fcfab9c` |
| [#22](https://github.com/haas-projet/haas/pull/22) | Compléments B11 → branche B11 de #23 | `957029bfacb077c376e344bbb383a39ab5821988` | [37149602255](https://github.com/haas-projet/haas/actions/runs/37149602255), head `5854e04` ; approbation réelle de `mdev44-code` |
| [#23](https://github.com/haas-projet/haas/pull/23) | Schéma B11 et ses compléments → `main` | `17c6daaa605ff36ed6513d57fd1393d8175e6c14` | [37634775893](https://github.com/haas-projet/haas/actions/runs/37634775893), head `2771d3013332e70747316f55e09d87f54281aa36` |
| [#27](https://github.com/haas-projet/haas/pull/27) | Brique B1 interne corrigée → `main` | `f1f6238e9deaa5d4577ed3c728777a98f1f4aa28` | [37637092346](https://github.com/haas-projet/haas/actions/runs/37637092346), head `108aa7d09850e366ff8d9c95cfd9eefc1fc4ea23` |

Les heads ont été synchronisés par merges ordinaires, avec contrôle du SHA exact avant fusion. Les conflits concernaient uniquement les historiques documentaires : sections B11/B35 et suivis de l'intégrateur conservés, ancien HANDOFF B35 maintenu comme instantané daté. Les commits de l'auteur sont conservés. #27 a été reciblée vers `main` après réception de ses prérequis.

CI de `main` après ces fusions : [37633677747](https://github.com/haas-projet/haas/actions/runs/37633677747) sur `33eafa0`, [37635179763](https://github.com/haas-projet/haas/actions/runs/37635179763) sur `17c6daa` et [37637569825](https://github.com/haas-projet/haas/actions/runs/37637569825) sur `f1f6238`, toutes réussies.

## Défaut B1 corrigé et limites

- Défaut reproduit sur PostgreSQL : `received_at = 2026-10-05T12:00:00+02:00` était stocké comme 12:00 UTC au lieu de 10:00 UTC, soit 7 200 secondes d'écart. Le nouveau test `test_received_at_preserves_the_instant_from_a_non_utc_timezone` échoue avant correction.
- Correctif réel `cdbe96f246266f10fa5f552cdbde152650d527d4` : normalisation UTC dans `ProcessTestEventService` avant `insertOrIgnore`. Le test compare l'instant réellement stocké. CI du correctif [37634846833](https://github.com/haas-projet/haas/actions/runs/37634846833) réussie ; synchronisation B11 ultérieure `108aa7d` retestée entièrement.
- Transaction, unicité `(run_id, event_id)`, atomicité, validation des entrées, rejeu et concurrence B1-05 vérifiés. La course utilise deux processus PHP indépendants, chacun avec sa connexion PostgreSQL, une barrière et un chevauchement forcé.
- **B35 reste IN_PROGRESS** : `LabConnection::NAME = null` utilise encore la base applicative ; le câblage de `haas_lab` et l'isolation du runtime restent à livrer avant activation du worker B36. Les tests distincts du défaut pédagogique attendus par le plan ne sont pas livrés. Aucune route HTTP B1 ni exécution de code utilisateur ajoutée. La fusion reçoit cette brique interne partielle, pas AC16/AC21 ni le laboratoire complet.

## B2 #28 — fusion bloquée par des tests réels

Head examiné : `897c70d46f900a2fd064110c1bd603a22d697183`. La [CI existante](https://github.com/haas-projet/haas/actions/runs/37553377350) est verte mais les tests HTTP de cette PR omettent l'en-tête `Origin`. Deux probes supplémentaires ont été exécutées dans un worktree isolé : **2 tests, 3 assertions, 2 échecs**.

| Probe HTTP exécutée | Attendu par B38 | Observé |
|---|---|---|
| POST valide depuis `Origin: https://demo.example.com` (origine de test fictive distincte) | 201 | **403** |
| POST valide depuis `Origin: http://localhost:5173` (origine HAAS stateful du test) | 201, aucun cookie | 201 et **deux cookies de réponse** |

Les valeurs de cookies n'ont pas été enregistrées. La seconde assertion échoue avant le contrôle final du démarrage de session ; ce contrôle final n'est donc pas déclaré exécuté. Les probes sont conservées comme [patch reproductible](probes/B38_ISOLATION.patch), à appliquer avec `git apply --unidiff-zero docs/quality/probes/B38_ISOLATION.patch` sur le head B2 examiné, sans ajouter de tests volontairement rouges à la suite de `main`. Exécuter ensuite `vendor/bin/phpunit --filter test_review_b2_` avec une base PostgreSQL locale dédiée et les origines de test indiquées ci-dessus.

Causes lues dans le code : `ProtectSpaRequests` est un middleware global ajouté dans `bootstrap/app.php` ; `withoutMiddleware` sur la route ne le retire pas. `statefulApi()` reste actif sans exclusion d'`EnsureFrontendRequestsAreStateful` pour B2. `DemoConnection::NAME = null` utilise la base HAAS par défaut ; le contrat prévoit une base fictive et un runtime distincts. La configuration cryptographique est également partagée avec l'application.

**B38 BLOCKED, #28 ouverte.** Corriger le routage non stateful, le contrat CORS de l'origine B2, la base/runtime/configuration séparés et leur refus d'accès aux données HAAS ; ajouter les tests d'isolation et de concurrence avant une nouvelle demande de fusion. L'isolation ne se résume pas à changer un nom de connexion.

## Capsules #29/#30 — revues statiques, brouillons conservés

Ces constats proviennent de la lecture du code et des contrats ; aucun test HTTP supplémentaire B22/B23 n'a été exécuté dans cette intervention. Les deux PR restent en brouillon, malgré leur CI verte.

### B22, [#29](https://github.com/haas-projet/haas/pull/29)

Head `58fe552f787fd68d36312afb63b6f0b06066f01c` ; [CI 37619853761](https://github.com/haas-projet/haas/actions/runs/37619853761). Origine exclusive, FK et contraintes d'unicité présentes.

- Priorité haute : `create_capsule_versions_table` impose `published_at IS NULL OR state = 'published'`. Le retrait d'une version publiée conservant sa date réelle est ainsi interdit, contrairement à la transition `published → withdrawn` et à l'historique attendu. Adapter la contrainte et tester le retrait sans effacer la date.
- Champs serveur massivement assignables dans les modèles (état/relecteur/date de publication, approbation/chemin/empreinte/taille d'artefact, auteur/visibilité). Restreindre avant les consommateurs ; aucun exploit HTTP actuel affirmé.
- Version optimiste des brouillons et association des technologies/compatibilités à compléter avant réception B22. La table de technologies ajoutée dans #30 ne clôt pas à elle seule le contrat de B22.

### B23, [#30](https://github.com/haas-projet/haas/pull/30)

Head `f20f01f7868e09c9b564d00b0e7a60bfe32e6962` ; [CI 37625262339](https://github.com/haas-projet/haas/actions/runs/37625262339). Le contrôle courant de l'acteur avant rejeu est correctement hérité du service d'idempotence.

- Priorité haute : `CreateCapsuleDraftService` vérifie auteur et résolution active, mais pas l'état réellement `resolved` de la demande source ni sa stabilité sous verrou ; la fixture présentée comme résolue reste en état draft. Exiger une provenance résolue cohérente et tester les courses/retraits.
- Validation conditionnelle source/origine incomplète dans `StoreCapsuleDraftRequest` ; choix double ou manquant peut être rejeté tardivement ou ignoré. Compléter les règles HTTP et la liste des champs autorisés.
- Conflits slug/version, technologies inconnues ou dupliquées, et écart longueur Unicode/caractères versus octets : risques de remontée générique 500 au lieu du contrat de validation/conflit. Ces réponses sont des prédictions statiques, pas des résultats HTTP exécutés.
- Capsule cible à relire/verrouiller ; version mémorisée à contrôler avant projection. Rejeu à confronter à la projection courante du brouillon. Ajouter les tests HTTP B23 et ceux de création d'une nouvelle version, actuellement absents.

**B22/B23 IN_PROGRESS**, sans réception complète. Corriger B22 avant B23 et conserver les branches et preuves existantes.

## Commandes et preuves exécutées

PHP local 8.5.10, PostgreSQL 17 temporaire sur `127.0.0.1:55447`, rôle de test et bases `haas_maadinaa_test`, `haas_b11_review_test`, `haas_b2_review_test`. Les cibles ont été identifiées avant exécution ; aucune base applicative ou distante utilisée. Clés de test éphémères hors Git. Le cluster temporaire est arrêté en fin de session, sans toucher au service existant.

| Contrôle | Résultat réel |
|---|---|
| B11 consolidé : `vendor/bin/phpunit --testsuite Unit,Feature,Architecture` puis `--testsuite Integration` | 312 tests / 2538 assertions + 151 / 1316, réussis |
| B1 corrigé avant B11 | Pint et PHPStan niveau 8 réussis ; 281 / 2507 + 158 / 1373 réussis |
| B1+B11 combinés, head `108aa7d` | **312 / 2538 + 176 / 1439 = 488 tests / 3977 assertions réussis** |
| CI finale de #27, PHP 8.4 et 8.5 / PostgreSQL 17 | Chacun 488 / 3977 ; Pint, PHPStan niveau 8, audit et documentation réussis, journaux lus |
| Probes B2 sur le head de #28 | 2 tests / 3 assertions / **2 échecs**, blocages reproduits |
| `node scripts/validate-pack.mjs` | 18/18 |
| `node scripts/check-deployment-docs.mjs` | 7/7 |
| `git diff --check` | Sans erreur sur les lots corrigés et synchronisés |

Empreintes des fichiers versionnés réactualisées dans le commit de bilan. Aucun contrôle Qodana, recette visuelle, frontend, hébergement ou déploiement exécuté ici. **B11 DONE ; 13 lots backend entiers DONE. BACKEND_GATE non reçu, aucun GO_FRONTEND/GO_PRODUCTION.** Prochaines actions de Madina : correction B22/B23 et isolation B2 ; intégrateur : câblage/isolation du laboratoire avec B36. B14 peut reprendre depuis B11 reçu.
