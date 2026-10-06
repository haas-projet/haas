# HAAS — Sanctum entre Vercel et Systalink
**Choix de conception :** sessions de confiance, pas de bearer token persistant dans le navigateur. Configuration illustrative, à adapter et tester. [D03]

Implémentation B07 et limites observées : [SESSIONS.md](../api/SESSIONS.md). Les chemins de courriels ci-dessous sont prévus pour B08 ; seuls les endpoints effectivement livrés sont activés dans CORS. La recette sur les domaines réels reste à exécuter.

## Domaines retenus (exemples)
- SPA : `https://app.haas.example.com` ; API : `https://api.haas.example.com`.
- Parent de cookies : `.haas.example.com` ; réservé aux hôtes de confiance.
- B2 : `https://demo.example.com` et `https://demo-api.example.com`, hors de ce parent.

La SPA et l'API sont cross-origin mais same-site. `SameSite=Lax` ne supprime ni les contrôles CSRF ni CORS. Ne jamais élargir le domaine de cookie à `.example.com` si B2 ou des hôtes non fiables y résident.

## Variables backend (valeurs publiques d'exemple)
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.haas.example.com
FRONTEND_URL=https://app.haas.example.com
SESSION_DRIVER=database
SESSION_COOKIE=haas_session
SESSION_DOMAIN=.haas.example.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=app.haas.example.com,api.haas.example.com
CORS_ALLOWED_ORIGINS=https://app.haas.example.com
```
`FRONTEND_URL` et `CORS_ALLOWED_ORIGINS` sont des conventions du projet : leur lecture doit être implémentée dans la configuration. Les variables seules ne configurent pas Laravel par magie. Ne pas inclure schéma/protocole dans SANCTUM_STATEFUL_DOMAINS ; les ports sont explicites en local. Activer le middleware stateful API de Sanctum dans la version Laravel retenue.

## Réglages CORS à implémenter
`allowed_origins` contient uniquement la SPA approuvée ; `supports_credentials=true`. Pas de `*` avec credentials. Limiter les méthodes aux routes livrées. Autoriser les headers effectivement utilisés : Accept, Content-Type, X-Requested-With, X-XSRF-TOKEN, Idempotency-Key ; exposer Retry-After et X-Request-ID si utilisés pour la lecture frontend.

Les paths couverts comprennent `api/*`, `sanctum/csrf-cookie`, `login`, `logout`, `register`, `forgot-password`, `reset-password`, `email/*` selon les routes effectivement retenues. Tester les prévols OPTIONS et la présence de CORS aussi sur les erreurs 401/403/419/422/429. CORS est un contrôle navigateur, pas une autorisation métier : Policies, session, quotas et CSRF restent obligatoires.

## Instance HTTP principale
```ts
// api-client.ts : exemple à compléter par la validation de l'environnement.
export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  timeout: 15000,
  headers: { Accept: 'application/json' },
});
```
`VITE_API_URL=https://api.haas.example.com` ; pas de secret. Les fonctions API appellent `/api/v1/...`, l'authentification `/login`, `/logout`, `/register` et `/sanctum/csrf-cookie` sur cette même origine API. Ne pas utiliser `baseURL='/'` en production Vercel.

## Parcours et erreurs
Initialiser CSRF sur l'API, envoyer la connexion, récupérer `/api/v1/me`. Le login régénère la session ; le logout l'invalide et renouvelle le token CSRF. Le cookie de session reste HttpOnly ; XSRF-TOKEN doit rester lisible par le client pour le mécanisme documenté. Le HTTPS et les attributs des deux cookies doivent être vérifiés dans le navigateur réel. [D03]

Un 401 attendu à l'ouverture de `/me` n'est pas une panne. Un 419 mène à une récupération contrôlée, sans renvoyer automatiquement toutes les écritures. Un 503 réseau n'est pas une déconnexion. Annuler les lectures et vider les caches privés à la fin d'une session. Les données de formulaire peuvent rester en mémoire ; aucun secret n'est persisté par défaut.

Vérification du courriel et réinitialisation : construire les liens à partir des origines autorisées. Une URL signée Laravel destinée à l'API ne doit pas être modifiée pour changer son hôte ; l'écran React peut recevoir une redirection autorisée après validation. Interdire les paramètres de redirection externes libres. Le message de réinitialisation ne révèle pas l'existence d'un compte.

## Local, CI et previews
Utiliser des hôtes locaux de confiance sous un même parent, ou un proxy local documenté. Ne pas mélanger localhost et 127.0.0.1 dans une session. Les tests des vrais cookies doivent activer le middleware CSRF, souvent neutralisé dans les tests applicatifs ordinaires ; conserver des tests navigateur dédiés.

Les previews Vercel ne pointent pas vers une API privée de production. Pour une recette distante authentifiée, créer temporairement un couple SPA/API de recette et des données/secrets dédiés sous un parent distinct, ou effectuer la recette en CI. Une preview uniquement visuelle est étiquetée comme telle et ne valide pas l'intégration Sanctum.
