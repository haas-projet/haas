# B09 — Autorisations et compte courant

2 octobre 2026. Base B08 `c8c0a30a01ffd8d4a41e892ac1f3f9ac7dce4278`, branche temporaire `backend/socle-auth-permissions`. Les PR #10/#11 restent ouvertes ; leurs commits et les branches des collègues sont préservés. Aucun package ni migration ajouté. Contrat : [CURRENT_ACCOUNT.md](../api/CURRENT_ACCOUNT.md), OpenAPI 0.7.0.

## Livré

GET /api/v1/me : session Sanctum, FormRequest sans entrée, Query restreinte au compte courant relu en base, Resource privée en liste blanche et capacités calculées via UserPolicy. Les comptes non vérifiés lisent leur compte sans pouvoir participer ; aucun bypass admin de propriété. MemberAccess fournit actif/vérifié et propriété pour les futurs domaines sans fabriquer leurs modèles.

EnsureAccountIsActive révoque la session d'un compte suspendu et distingue ACCOUNT_SUSPENDED après authentification. GET /api/v1/account-access reste public après révocation et expose seulement une procédure générale et l'adresse de recours configurée. Adresse vide explicitement indisponible ; invalide donne 503. Aucun envoi de message ni motif privé exposé.

## Contrôles locaux exécutés

PHP 8.5.10, Composer 2.10.3 conservé depuis B08. Nouveau cluster PostgreSQL 17.0 temporaire local, 127.0.0.1:54689, base `haas_permissions_test`, rôle `haas_test`, DB_URL vide. Serveur applicatif habituel inchangé.

| Commande | Résultat |
|---|---|
| composer format puis composer lint | Format appliqué puis contrôle réussi |
| composer analyse | Niveau 8, aucune erreur, aucun ignore ajouté |
| composer test | 177 tests / 1721 assertions réussis |
| composer test:integration | 67 tests / 685 assertions réussis sur PostgreSQL |
| php vendor/bin/phpunit --testsuite Integration --filter test_verified_ownership_rule_is_enforced | 1 test / 14 assertions après clarification de la fixture ; déjà compté dans les 67 |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Prérequis satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |
| php artisan route:list --path=api/v1 --json | Deux routes B09 ; /me protégé par auth:sanctum |

Total distinct : **244 tests / 2406 assertions**. Matrice visiteur, trois rôles, vérifié/non vérifié, actif/suspendu ; accès tiers refusé même pour admin, aucune capacité inconnue accordée. Cookies/session SQL/CSRF réels, contrat privé strict, champs forgés rejetés, rôle relu, suspension après login, ancien cookie refusé, recours après révocation, origine B2/preview refusée, panne 503 sans destruction de session. Suites antérieures d'inscription/reset/concurrence conservées.

Les premiers essais ont détecté un accès nullable à typer explicitement, une assertion de compteur que l'analyse statique considérait immuable (remplacée par l'observation d'un événement de fixture), puis une factory de dates initialisant le pilote JDBC hérité de l'environnement. La matrice en mémoire fixe désormais le format de dates sans ouvrir de connexion ; aucune configuration applicative changée pour masquer cet échec. Seules les relances réussies figurent dans le bilan ci-dessus.

## Vérifications documentaires et CI

`node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` : aucune erreur. Serveur PostgreSQL B09 arrêté après les vérifications. Empreintes actualisées avant commit.

B09 IN_PROGRESS avant observation de sa CI propre. Proposer une PR contre backend/socle-auth tant que B08 n'est pas intégré, puis recibler dans l'ordre #10 → #11 → B09. Les résultats distants seront ajoutés après exécution réelle.

## Limites

AC02/AC04 couverts partiellement côté identité ; AC03/AC09 ne sont pas reçus de bout en bout : la fixture de propriété ne crée aucune demande/résolution, B11/B16/B19 restent nécessaires. La suspension de B09 révoque la session courante ; la commande et la révocation globale lors de suspension/changement de rôle restent B32.

Adresse de recours réelle à configurer et vérifier, SMTP réel non testé, conditions d'inscription non fournies. Pas de navigateur sur domaines finaux, Qodana ou déploiement exécuté. Aucun avis humain, BACKEND_GATE ou GO_FRONTEND simulé. Après intégration : B10, profils publics et modification du profil. Pour Systalink, B09 reste « En cours » tant que la fusion n'est pas vérifiée.
