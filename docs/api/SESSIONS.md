# B07 — Sessions de confiance

Complément B08 : [courriels de compte](ACCOUNT_MAIL.md), reset et empreinte du mot de passe enregistrée dès la connexion, contrôlée pour les sessions web comme API. La navigation GET de vérification signée accepte le Referer d'un client mail si Origin est absent ; les autres contrôles d'origine restent applicables.

Sanctum 4.3.3 utilise le garde Laravel `web` et les sessions PostgreSQL, sans jeton bearer ni remember-me. Contrat OpenAPI 0.5.0 : `GET /sanctum/csrf-cookie`, `POST /login`, `POST /logout`. L'inscription B06 utilise le même contrôle CSRF. Aucun endpoint `/api/v1/me` n'est encore livré : B09 ; aucune route de test n'est enregistrée en production.

## Contrat HTTP

1. Initialiser la session anonyme par `/sanctum/csrf-cookie` avec les cookies activés. Réponse 204, `haas_session` HttpOnly et `XSRF-TOKEN` lisible. En production : Secure, SameSite=Lax, domaine parent HAAS borné.
2. Envoyer `/login` avec uniquement `email` et `password`, le cookie de session et `X-XSRF-TOKEN` (valeur du cookie XSRF après décodage URL). Courriel normalisé ; mot de passe conservé tel quel, maximum 128 caractères Unicode. Les paramètres inconnus, dont remember/role, sont refusés.
3. Réponse 200 : `data.id`, `data.handle`, `data.email_verified`. Ancien identifiant de session détruit, identifiant et jeton CSRF renouvelés. Aucun courriel, rôle, hash ou token d'accès exposé ; `Cache-Control: no-store`.
4. Réutiliser les cookies pour les routes protégées par `auth:sanctum`, avec Origin ou Referer de confiance. La vérification du courriel et les Policies restent nécessaires aux opérations métier : se connecter n'autorise pas à publier.
5. `/logout` exige session et CSRF, renvoie 204, détruit la session précédente et renouvelle le token. Les anciens cookies ne réauthentifient plus. Une tentative sans CSRF échoue et ne déconnecte pas l'utilisateur.

Mauvais identifiants, compte absent et hash ancien non compatible : même 422 avec erreur générique sur email. Un compte suspendu avec mot de passe correct reçoit 403 ; une session déjà ouverte de ce compte est invalidée au prochain accès. La suppression globale des sessions lors des commandes de modération/changement de rôle reste B32. Compte non vérifié admis à la connexion, sans gain de droits métier.

Le service vérifie exclusivement Argon2id puis rehache si ses paramètres doivent évoluer. Les anciens hashes bcrypt exigent une réinitialisation via B08 avant connexion : aucun `HASH_VERIFY=false`, aucun test tronqué à 72 octets. Le service applique une durée minimale de 200 ms aux tentatives ; ce n'est pas une garantie formelle contre toute analyse temporelle. Les erreurs SQL ne propagent pas les bindings privés aux logs. Les secrets du DTO sont masqués.

Limites de connexion : cinq tentatives/minute par courriel normalisé et IP, vingt/minute par IP, succès compris. Les clés du premier compteur utilisent un HMAC, sans courriel en clair. Le cache partagé PostgreSQL assure le comptage entre processus en exploitation ; les tests de limite utilisent le cache array. Réponse 429 avec Retry-After et CORS. Les proxys fiables et IP transmises devront être qualifiés lors du déploiement ; aucun trust-all ajouté.

Après expiration ou invalidation pour changement de mot de passe, recommencer par l'initialisation CSRF avant une connexion. Un 419 exige une récupération contrôlée, jamais le rejeu automatique d'une écriture métier. B07 vérifie le comportement des sessions expirées et des cookies anciens côté serveur.

## Origines et configuration

Les configurations `spa.php`, `cors.php` et `sanctum.php` lisent réellement FRONTEND_URL, CORS_ALLOWED_ORIGINS et SANCTUM_STATEFUL_DOMAINS. Par défaut local : API `http://localhost:8000`, SPA `http://localhost:5173`, session host-only ; utiliser localhost des deux côtés, sans mélanger 127.0.0.1. Aucune SPA n'est créée par ce lot.

Exemple de production, **domaines à remplacer par les hôtes contrôlés**, sans activation de déploiement :

```dotenv
APP_ENV=production
APP_URL=https://api.haas.example.com
FRONTEND_URL=https://app.haas.example.com
CORS_ALLOWED_ORIGINS=https://app.haas.example.com
SANCTUM_STATEFUL_DOMAINS=app.haas.example.com,api.haas.example.com
SESSION_DRIVER=database
SESSION_COOKIE=haas_session
SESSION_DOMAIN=.haas.example.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Avant le traitement des routes auth/API, la configuration est contrôlée : origines exactes sans chemin/wildcard, cohérence CORS/Sanctum, HTTPS/Secure en production, parent commun exact si les hôtes diffèrent, HttpOnly/Lax. Une configuration incohérente refuse la requête (500 générique) et doit être corrigée par l'exploitant. Ce contrôle HTTP n'empêche pas l'installation Composer ni la sonde `/up` sans SQL.

Origin et Referer, lorsqu'ils existent, doivent tous deux appartenir aux origines configurées ; B2 et previews Vercel sont refusés. Sans ces en-têtes, les routes web exigent toujours CSRF et l'API ne devient pas stateful par le seul cookie. CORS autorise les chemins livrés et méthodes GET/HEAD/POST/OPTIONS ; ajouter les méthodes futures avec leurs contrats/tests. Headers permis : Accept, Content-Type, X-Requested-With, X-XSRF-TOKEN, Idempotency-Key ; Retry-After et X-Request-ID sont exposés.

Le middleware CORS peut renvoyer l'origine autorisée constante même à une origine étrangère : cette valeur diffère de l'origine appelante, donc ne l'autorise pas. En plus de CORS, le middleware HAAS refuse effectivement les requêtes applicatives d'origine étrangère (403, aucun nouveau cookie). Les prévols ne créent pas de session. Sanctum ne lit aucun bearer token, ne publie aucune migration de token et User n'utilise pas HasApiTokens.

Laravel 13 accepte nativement certaines requêtes sur la base de Sec-Fetch-Site ; HAAS conserve son mécanisme cryptographique mais exige toujours le token CSRF, y compris avec l'indication same-origin. Aucun bypass same-site ni exemption login/register/logout.

## Preuves et limites

Tests HTTP via le noyau Laravel et vrais cookies chiffrés, CSRF réactivé ; sessions réellement stockées dans PostgreSQL en intégration. Les gardes et stores sont recréés entre appels pour relire ces cookies, sans `actingAs` ni `Sanctum::actingAs` dans les nouveaux scénarios. Les routes protégées de fixture n'existent que dans les tests.

Cette recette serveur ne constitue pas une navigation réelle sur Vercel/Systalink : DNS, TLS, navigateur et livraison effective des cookies sur les domaines finaux restent DEP-AC02. Aucun GO_FRONTEND ou déploiement. Références du mécanisme : [Sanctum Laravel 13](https://laravel.com/framework/docs/13.x/sanctum), [authentification Laravel](https://laravel.com/framework/docs/13.x/authentication), sources exactes du package dans [B07_DEPENDENCIES.json](../quality/B07_DEPENDENCIES.json).
