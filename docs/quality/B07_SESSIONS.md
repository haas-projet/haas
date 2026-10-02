# B07 — Sessions Sanctum

2 octobre 2026. Branche `backend/socle-auth-sessions`, créée depuis main `075e6eb6da5dd50a3d9b64ccca81fe03eaf5f43a` après [fusion autorisée de B05/B06](MERGE_B05_B06.md). Contrat : [SESSIONS.md](../api/SESSIONS.md), OpenAPI 0.5.0.

## Livraison

Sanctum 4.3.3, licence MIT vérifiée avant ajout et dans le package installé. Une dépendance runtime ajoutée ; les 106 autres packages sont inchangés, aucun installateur n'a réécrit les routes ou publié de table de tokens. [Inventaire exact](B07_DEPENDENCIES.json).

Initialisation CSRF, login et logout par session web, DTO secret masqué, validation stricte, service de vérification Argon2id avec rehash éventuel, adaptation HTTP séparée, Resource minimale. Rotation de session/CSRF, rejet des anciens cookies, authentification API stateful, refus des bearer tokens, limites par identifiants/IP et par IP. Courriel non vérifié admis à la connexion, compte suspendu refusé ; session suspendue invalidée au prochain accès.

Configuration locale et production explicites, origines CORS exactes et contrôle Origin/Referer, cookies HttpOnly/Lax/Secure selon environnement, pas de wildcard preview ou confiance automatique dans Host. Jeton CSRF exigé même avec Sec-Fetch-Site=same-origin. Contrôle de configuration avant traitement HTTP auth/API ; la sonde /up et les commandes d'installation ne forcent pas de connexion SQL.

## Vérifications locales exécutées

PHP 8.5.10 explicite, Composer 2.10.3 temporaire vérifié, PostgreSQL 17.0 sur 127.0.0.1:54681, base `haas_quality_test`, rôle `haas_test`, DB_URL vide. Nouveau cluster temporaire B07, arrêté en finally après les tests ; service SQL habituel inchangé.

| Commande | Résultat |
|---|---|
| composer format puis composer lint | Style appliqué puis contrôle réussi |
| composer analyse | Niveau 8, aucune erreur, sans baseline/ignore |
| composer test | 140 tests / 1189 assertions réussis |
| composer test:integration | 42 tests / 349 assertions réussis sur PostgreSQL |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |
| node scripts/validate-pack.mjs | 18/18 contrôles documentaires réussis |
| node scripts/check-deployment-docs.mjs | 7/7 contrôles documentaires réussis |
| git diff --check | Aucune erreur |
| actionlint 1.7.12 -shellcheck= -pyflakes= .github/workflows/backend-ci.yml | Workflow valide ; shellcheck/pyflakes non exécutés |

Total distinct : **182 tests / 1538 assertions**. Les tests SQL B05/B06, dont deux processus concurrents d'inscription, restent inclus. Nouveaux scénarios : cookies chiffrés réutilisés d'une requête à l'autre, identifiant SQL remplacé au login/détruit au logout, token renouvelé, mauvais mot de passe/compte absent/bcrypt historique sans fuite différentielle du contenu, Unicode long et rehash, expiration, changement de mot de passe, suspension, faux logout sans CSRF, token altéré, deux origines HAAS, B2/preview/suffixe trompeur interdits, prévols et erreurs CORS 401/403/419/422/429, configuration production trop large refusée et cookies locaux host-only sur HTTP.

Les middlewares CSRF sont effectivement actifs dans les nouveaux scénarios via une classe de test qui retire uniquement l'exemption PHPUnit. Aucun actingAs utilisé. Routes de fixture protégées uniquement dans les tests ; elles ne constituent pas un endpoint /me livré. La session PostgreSQL est relue depuis les cookies, avec gardes/stores de requête recréés.

Corrections après échecs réels : initialisation différée du limiteur pour éviter une résolution prématurée de cache/DB ; contrôles de configuration placés à la frontière HTTP ; types des enums et entrées de configuration précisés ; backend array conservé entre requêtes de test, garde de conteneur recréé. Une révocation suspendue conservait l'utilisateur dans le garde Sanctum : oubli explicite de ce cache après logout web. Après changement de mot de passe, le client de test initialise de nouveau CSRF avant connexion, conformément au contrat. Les exécutions initiales en échec ne sont pas présentées comme réussies.

## CI observée

La première CI, [36956745374](https://github.com/haas-projet/haas/actions/runs/36956745374) sur le commit applicatif `a8bfcaaefa485e6374db4a9332127a75a0e3ffba`, a échoué : APP_URL utilisait encore localhost sans port dans le workflow, en conflit avec Sanctum localhost:8000. Workflow et .env.testing.example alignés sur les quatre variables d'origine par `645e74ea0dd868317c89782fee479fddb37d2d06`, sans assouplir les contrôles applicatifs. Les suites HTTP et PostgreSQL ci-dessus ont été relancées avec ces valeurs explicites. Un test HTTP dédié vérifie les cookies locaux. Le run initial en échec n'est pas une validation.

[PR #10](https://github.com/haas-projet/haas/pull/10), contre main. [Run 36957084503](https://github.com/haas-projet/haas/actions/runs/36957084503) réussi sur `645e74e` ; journaux lus : PHP 8.4.26 et 8.5.11, chacun 140 tests / 1189 assertions puis 42 tests / 349 assertions PostgreSQL, soit 182 tests / 1538 assertions par version. Lint, analyse niveau 8, installation verrouillée, validation Composer, prérequis, audit et documentation réussis ; contrôle final backend-ci vert. B07 IN_REVIEW, aucun avis humain présumé. Le dernier SHA documentaire et sa CI figurent dans la PR et le bilan de session.

## Limites

Aucun navigateur sur domaines réels, DNS/TLS Vercel/Systalink, Qodana ou déploiement exécuté ; DEP-AC02 et BACKEND_GATE restent non validés. Conditions réelles absentes : inscription 503 par défaut. B08 doit fournir les courriels et la réinitialisation (nécessaire aux anciens comptes bcrypt), B09 le profil privé et les Policies. B32 doit gérer la révocation globale lors des commandes de modération/rôle ; seul le rejet de la session utilisée est couvert ici.
