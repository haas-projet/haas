# B12 — Audit et révisions

2 octobre 2026. Branche `backend/socle-auth-audit`, base B10 `3ab1c02b9751e3fda360c6c078d9d03ec1eae921`. Les PR #10/#11/#13/#14 et les branches des collègues sont préservées. Contrat [AUDIT_ET_REVISIONS.md](../architecture/AUDIT_ET_REVISIONS.md), description HTTP OpenAPI 0.8.1, aucune dépendance ajoutée.

## Livraison

Migration additive `content_revisions`, AuditWriter transactionnel, DTO limité aux noms de champs autorisés, intégration réelle à UpdateProfileService. Acteur et horodatage serveur, version de révision unique par ressource, états relus sous verrou, métadonnées minimales sans valeurs textuelles ni copie de requête. Une panne d'audit annule le métier ; une annulation du métier annule ses révisions.

Primitive interne de purge de métadonnées : modérateur/admin actif et vérifié relu, anciens contenus jamais lus ou recopiés, trace minimale du nombre purgé, deuxième appel sans nouvelle révision sans effet. Purge et trace atomiques. Aucun accès public à l'historique. Les données courantes et leurs autres projections seront retirées par les services métier B31 ; cette primitive ne prétend pas réaliser seule ce parcours.

## Contrôles locaux réels

PHP 8.5.10, Composer 2.10.3 temporaire déjà vérifié. Nouveau cluster PostgreSQL 17.0 local isolé, `127.0.0.1:54691`, base `haas_audit_test`, rôle `haas_test`, DB_URL vide. Aucune base applicative ni compte externe utilisé.

| Commande | Résultat |
|---|---|
| composer format puis composer lint | Format appliqué, contrôle final réussi |
| composer analyse | PHPStan/Larastan niveau 8, aucune erreur, aucun ignore ni baseline |
| php vendor/bin/phpunit --filter 'Audit\|ProfileRevisionData\|ProfileConcurrency' | 29 tests / 113 assertions réussis ; inclus dans les suites ci-dessous |
| composer test | 227 tests / 2023 assertions réussis |
| composer test:integration | 95 tests / 889 assertions PostgreSQL réussis |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |

Total distinct : **322 tests / 2912 assertions**. Les erreurs initiales d'analyse concernaient la validation runtime du DTO, une instruction de test inaccessible et des accès potentiellement nulls. Corrections des types et contrôles effectifs, puis analyse et suites relancées avec succès ; aucune règle assouplie.

Scénarios B12 : auteur/date réels, rejet des champs HTTP d'audit falsifiés, absence de texte/courriel/URL/hash/UUID de technologie dans metadata, rollback extérieur et panne SQL d'audit, conflit/refus sans événement, purge d'anciennes valeurs fictives sans copie et sans affecter un autre profil, droits périmés refusés, panne de trace de purge annulant l'effacement, absence de route d'historique, même connexion et transaction obligatoires. Migration aller/retour préserve le profil ; contraintes SQL de numéro positif, unicité, objet JSON, métadonnées vides après purge et FK acteur vérifiées. Suppression d'un acteur conserve l'événement avec attribution null.

Concurrence B12 : fixtures commitées dans la base de test avant le lancement, deux processus PHP indépendants. Le parent observe **deux workers en attente de verrou dans pg_stat_activity**, puis libère la barrière. Deux vraies éditions métier sérialisées réussissent : deux événements, révisions 1/2, versions de profil 1/2, aucun numéro dupliqué. Le test B10 concurrent confirme aussi qu'un perdant en conflit ne produit pas d'audit.

## Suivi et limites

`node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` sans erreur. Empreintes actualisées avant commit, cluster PostgreSQL B12 arrêté après les tests. CI à observer après publication. Le SHA réel de livraison figure dans le bilan et la PR, pas dans son propre commit. B12 reste en préparation avant observation de sa CI, puis en revue jusqu'à intégration vérifiée.

Premier adaptateur fourni : profil. Les consommateurs demandes, capsules, offres et modération ajouteront leurs projections et tests avec leur pilote, en réutilisant ce contrat ; aucun de leurs lots n'est déclaré reçu. AC25 global, B31, sauvegardes, audit des commandes de compte futures et BACKEND_GATE restent à valider aux lots concernés. Aucune UI React, revue humaine, Qodana, délivrabilité SMTP, exécution de laboratoire ou mise en production vérifiée ici.

Ordre d'intégration : #10 → #11 → #13 → première livraison #14 → B12, avec reciblage et contrôle des derniers commits. B10 reste IN_PROGRESS pour ses contributions non raccordées ; la CI de B12 ne ferme pas cet écart. Prochain lot du socle : **B13 — Idempotence des commandes**. Systalink B12 « En cours — prêt pour revue » seulement après CI verte, aucune carte à déclarer terminée sans fusion vérifiée.
