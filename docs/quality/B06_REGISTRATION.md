# B06 — Inscription

Date : 2 octobre 2026. Branche `backend/socle-auth-registration`, issue de `4de376f8a00bcc1a91b31ed68661319ffdfb7828` (B05, PR #8 ouverte). Aucune fusion de B05 présumée. [Contrat et limites](../api/REGISTRATION.md), OpenAPI 0.4.0.

## Livraison

FormRequest avec liste blanche stricte, DTO readonly avec secret masqué, service transactionnel, Resource minimale et route POST /register sous middleware web/CSRF et throttle. Membre actif non vérifié, profil et reçu versionné d'acceptation créés atomiquement. PostgreSQL décide l'unicité du pseudonyme/courriel. Hachage Laravel Argon2id sans troncature, confirmation identique et messages français. Aucune nouvelle dépendance.

La version des conditions est volontairement non configurée par défaut : aucun texte validé n'est fourni dans le dépôt. Sans version publiée renseignée, réponse 503. Les fixtures ne constituent aucune approbation. Aucune connexion automatique ni notification ; parcours Sanctum en B07, courriels en B08. Les anciens hashes bcrypt restent inchangés et nécessitent une stratégie explicite avant ouverture de la connexion.

## Contrôles locaux réels

PHP 8.5.10 explicite, Composer 2.10.3 vérifié en copie temporaire, PostgreSQL 17.0. Le cluster temporaire de la précédente session ne démarrait plus (répertoire pg_notify absent) ; nouveau cluster B06 initialisé, exclusivement 127.0.0.1:54681, base haas_quality_test, rôle haas_test, DB_URL vide. Cluster arrêté après les tests ; service PostgreSQL habituel inchangé.

- `composer test` : 118 tests / 922 assertions, réussis.
- `composer test:integration` : 35 tests / 296 assertions, réussis ; total distinct **153 tests / 1218 assertions**.
- Cas : entrées absentes/invalides, champs serveur même null/query string, conditions et ancienne version, 429, CSRF réellement actif sans jeton (419), compte sans élévation, courriel privé, normalisation, mots de passe 12/128 Unicode, espaces conservés, différence après 72 octets vérifiée, texte ressemblant à un hash rehaché.
- Concurrence réelle : deux processus PHP et deux connexions PostgreSQL, barrière avant INSERT, un succès et un doublon, une seule ligne compte/profil/acceptation. Écritures des enfants commitées, puis nettoyées explicitement sur la base dédiée.
- Panne réelle par trigger SQL de test : HTTP 500, rollback des trois écritures, journal applicatif formaté ne contenant ni courriel, ni mot de passe, ni hash, ni bindings SQL.

Corrections pendant vérification : diagnostic de confirmation rattaché au bon champ, types/nullabilité des assertions corrigés sans ignore, lecture de sortie complète des processus pour éviter de perdre leur signal READY sous Windows. Une première vérification des logs effectuait un dump récursif trop large ; processus PHPUnit identifié et arrêté, remplacé par l'inspection du journal réellement formaté. Ces tentatives interrompues/échouées ne sont pas comptées comme réussies.

Contrôles finaux exécutés : `composer lint` et `composer analyse` réussis (niveau 8, sans baseline) ; `composer validate --strict --no-check-publish` valide ; `composer audit --locked --no-interaction` sans avis de vulnérabilité. `node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` sans erreur. Empreintes des fichiers livrés actualisées.

B06 reste IN_PROGRESS tant que sa CI propre n'est pas observée. Aucun BACKEND_GATE, GO_FRONTEND, Qodana, recette sur domaines réels ou déploiement validé.
