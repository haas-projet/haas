# B14 — Création des demandes

## Livraison du 4 octobre 2026

À la demande de l'utilisateur, suite de la partie de Lamine en tenant compte de Madina. Confirmation reçue : Madina n'a pas commencé B14 ni HelpIntent/BV201. Le schéma de sa PR #23 (`1c380c4`) est déjà dans #22 (`5854e04`) ; ses cinq migrations, ses commits et son suivi sont conservés. #22 est reciblée sur sa branche comme complément. B14 est préparé séparément depuis `5854e04` sur `backend/communaute-entraide-b14`, sans modifier les PR de code en revue.

Livraison : POST /api/v1/requests, FormRequest → DTO → Service → Resource, Policy de création/visibilité, enum HelpIntent unique, migration additive, audit atomique et idempotence commune. [Contrat et limites](../api/HELP_REQUEST_CREATION.md), [OpenAPI](../api/openapi/community.yaml). Les prérequis d'intégration restent #23 → #22 → B14 ; les PR seront reciblées vers main après fusion des prérequis et leur CI revérifiée. Aucune revue humaine ni fusion n'est supposée.

## Contrôles réellement exécutés

PHP 8.5.10 local ; PostgreSQL 17.0 temporaire, limité à 127.0.0.1:54694, base dédiée haas_community_test, rôle haas_test, environnement testing et garde-fou SQL actif. La base temporaire de la veille était incomplète ; un nouveau cluster distinct a été initialisé, sans toucher aux bases applicatives ni aux paramètres privés de Madina.

Commandes PHP lancées depuis backend avec PHP 8.5.10 explicitement en tête du PATH et DB_HOST/PORT/DATABASE/USERNAME définis pour cette base :

| Contrôle | Résultat |
|---|---|
| `php vendor/bin/pint --test` | Réussi |
| `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Aucune erreur, aucune baseline ajoutée |
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | 284 tests / 2686 assertions, réussis |
| `php vendor/bin/phpunit --testsuite Integration` | 192 tests / 1732 assertions, réussis |
| `composer validate --strict --no-check-publish --no-interaction` | Réussi ; dépréciations de Composer 2.8.5 sous PHP 8.5 |
| `composer audit --locked --no-interaction --format=json` | Aucune alerte, aucune dépendance abandonnée |
| `php ../scripts/generate-api-types.php --check` | 36 types API à jour, aucune application frontend créée |
| `node scripts/validate-pack.mjs` (racine) | 18/18 |
| `node scripts/check-deployment-docs.mjs` (racine) | 7/7 |
| `git diff --check` et SHA256SUMS | Sans erreur ; 510 empreintes vérifiées |

Total : 476 tests / 4418 assertions, sans compter deux fois les tests ciblés rejoués pendant les corrections. Les tests ciblés du domaine ont aussi couvert B11 et B14 ensemble. L'inventaire API a d'abord détecté la route non encore documentée ; il réussit après ajout du contrat complet. Les erreurs de types ont été corrigées sans ignore ; les casts enum sont documentés comme sur User.

## Preuves de comportement

- Création publiée ou brouillon minimal ; brouillon réservé à son auteur même face à un admin. La Policy reconnaît la visibilité publique d'une publication ; son endpoint de lecture attend B15.
- Quatre intentions dans le même workflow. ask_question fonctionne sans code, expected, attempts ni environnement. Champs longs, manquants, protégés et pivots inconnus refusés sans effets. « aucune » admis ; technologies distinctes, existantes et bornées.
- Vraies sessions SPA et CSRF actifs. Visiteur/non vérifié refusés ; suspension et masquage relus au rejeu, version modifiée en 409. Réponse sans données privées de compte.
- Code HTML/script retourné comme texte JSON, espaces conservés. Secrets suspects refusés sans écho de valeur. La détection reste indicative et le rendu navigateur attend le frontend.
- Même clé et charge canonique : même identifiant/version, une demande, un pivot par technologie, un audit et une intention. Autre charge : conflit. Test aux maxima Unicode avec le plafond commun de 128 Kio.
- Deux processus indépendants attendent réellement le verrou du même acteur dans pg_stat_activity ; après libération, une seule création/audit/intention. Charge identique : même UUID des deux côtés ; charge différente : une création et un conflit. Fixtures commitées et nettoyage limité à l'auteur de test.
- Échec d'audit : rollback de la demande, des pivots et de l'intention, puis relance de la même clé réussie. Aucun texte de demande ou clé client dans l'audit ni la réponse mémorisée.
- Migration additive depuis B11 : conservation des données, intention unblock par défaut, contrainte d'enum ; retour refusé si des brouillons/questions utilisent les colonnes nullables. Aucun renommage des migrations de Madina.

## Revue et suite

B14 IN_REVIEW après publication ; CI du SHA publié à observer dans la PR et le bilan. Aucun merge, déploiement, Qodana ou gate déclaré exécuté. Systalink : B11 et B14 restent En cours tant qu'ils ne sont pas relus et intégrés. Les 25 autres lots de la coordination restent à faire. Prochain lot B15 ; BV201/BC07 réutilisent HelpIntent mais ne sont pas déclarés réalisés par ce seul ajout.
