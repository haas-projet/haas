# B10 — Profils et technologies, première livraison

2 octobre 2026. Branche temporaire `backend/socle-auth-profiles`, base B09 `6985eeeb57994dca20e9e42c115c0df4778043bf`. Les PR #10/#11/#13 sont préservées. Contrat [PROFILES.md](../api/PROFILES.md), OpenAPI 0.8.0. Aucun package ajouté.

## Livré et restant

Profil public en liste blanche, profil propre avec capacités/version, PATCH partiel strict, référentiel technologies paginé. Avatar en initiales Unicode, démonstration explicitement étiquetée. Liens GitHub HTTPS bornés, jamais récupérés ; anciens liens invalides non exposés. Pas d'annuaire automatique.

UserPolicy contrôle public/actif/vérifié et propriété ; aucun admin ne modifie un profil tiers. DTO readonly et service transactionnel avec relecture des droits sous verrou compte/profil. Huit technologies existantes maximum, version optimiste, rollback complet sur erreur. Migration additive, précontrôle des dépassements existants sans suppression, CHECK de version non négative. CORS ajoute PATCH sans élargir les origines.

**B10 reste IN_PROGRESS** : les liens/compteurs de contributions exigent les sources métier non encore livrées (B19/B25/BV209, visibilité et retraits). `contributions: null` ne représente pas un nombre. Aucune fausse validation d'AC27 ou de livraison complète du lot. Voir [BLOCKERS.md](../BLOCKERS.md). La PR présente cette première partie séparément.

## Contrôles locaux exécutés

PHP 8.5.10, Composer 2.10.3 déjà vérifié lors de B08. Nouveau cluster PostgreSQL 17.0 local isolé, 127.0.0.1:54690, base `haas_profiles_test`, rôle `haas_test`, DB_URL vide. Aucune base applicative ciblée.

| Commande | Résultat |
|---|---|
| composer format puis composer lint | Format appliqué, contrôle final réussi |
| composer analyse | Niveau 8, aucune erreur, sans ignore ni baseline |
| composer test | 213 tests / 2008 assertions réussis |
| composer test:integration | 81 tests / 800 assertions PostgreSQL réussis |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |

Total distinct : **294 tests / 2808 assertions**. Les tests ciblés intermédiaires ne s'ajoutent pas à ce total.

Scénarios : limites Unicode 500/501, huit/neuf technologies, UUID dupliqués et inconnus, liste blanche même champs nulls, faux liens GitHub, liens anciens, invité/non vérifié/admin tiers, relecture après suspension/retrait de vérification, CSRF réellement actif et CORS, confidentialité publique stricte et contrat, PATCH partiel/effacement, absence de création à la lecture, cache no-store et disparition après suspension, pagination stable, migration/rollback préservant les données.

Concurrence : deux processus PHP indépendants, fixtures déjà commitées ; le parent observe **les deux workers en attente de verrou dans pg_stat_activity** avant de libérer la barrière. Un succès et un conflit seulement, version 1 et huit technologies du gagnant, aucune sélection mélangée. Le premier essai n'atteignait pas la barrière faute de pompage des pipes Process ; corrigé, puis scénario et suite complète relancés avec succès.

Une contrainte SQL de fixture interdit l'insertion du pivot après modification du profil : rollback réel de bio/version/pivot. L'exception ne contient ni bindings, ni biographie, ni exception SQL chaînée. Cette contrainte est limitée à la base dédiée et annulée avec le test.

## Documentation et CI

`node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` : aucune erreur. Serveur PostgreSQL B10 arrêté après les tests. Empreintes actualisées avant commit.

CI à observer après publication ; aucun résultat distant présumé. Ordre d'intégration : #10 → #11 → #13 → première livraison B10 ; recibler et retester avant fusion. Les trois branches permanentes sont conservées ; branches temporaires retirées seulement après intégration vérifiée.

## Limites

Compteurs de contributions et AC27 complets restent à implémenter/tester avec les domaines concernés ; audit partagé à raccorder en B12. Aucun navigateur sur domaines réels, SMTP réel, Qodana ou déploiement exécuté. Aucun BACKEND_GATE, revue humaine ou GO_FRONTEND simulé. Pour Systalink, carte B10 « En cours » ; même une fusion de cette première partie ne termine pas le raccordement restant. Prochain lot indépendant du responsable socle : B12, audit et révisions.
