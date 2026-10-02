# Contrat HTTP commun — B04

Les routes métier utilisent `/api/v1`. Une route API inconnue répond JSON même avec `Accept: text/html`. En dehors de ce préfixe, `Accept: application/json` déclenche le même renderer, notamment pour les futurs endpoints d'authentification. Les pages HTML hors API gardent leur rendu Laravel. B04 ne crée aucun endpoint métier ni parcours de connexion.

Chaque requête reçoit un UUID serveur dans `X-Request-ID`. Les erreurs le répètent dans `request_id`, et les exceptions journalisées le reçoivent en contexte. L'en-tête client n'est jamais repris. Le stockage se limite à l'objet Request, sans contexte statique réutilisé entre requêtes. Les erreurs restent neutres même avec APP_DEBUG=true ; la production doit néanmoins garder APP_DEBUG=false.

```json
{"error":{"code":"RESOURCE_NOT_FOUND","message":"Cette ressource est introuvable.","fields":{}},"request_id":"7e4a2eaf-6136-4ab7-903c-6509f7556a40"}
```

| Statut | Code commun | Comportement |
|---|---|---|
| 400 | BAD_REQUEST | Requête invalide |
| 401 | AUTHENTICATION_REQUIRED | Pas de redirection HTML dans l'API |
| 403 | ACTION_FORBIDDEN | Interdiction sans déconnexion |
| 404 | RESOURCE_NOT_FOUND | Même rendu pour ressource absente ou masquée par une Policy |
| 405 | METHOD_NOT_ALLOWED | En-tête Allow conservé |
| 409 | RESOURCE_CONFLICT | Relecture nécessaire ; préserver la saisie côté client |
| 413 | PAYLOAD_TOO_LARGE | Taille HTTP dépassée |
| 415 | UNSUPPORTED_MEDIA_TYPE | Format refusé |
| 419 | CSRF_TOKEN_MISMATCH | Récupération contrôlée de session/CSRF, sans rejeu automatique |
| 422 | VALIDATION_FAILED | `error.fields` associe chaque champ à une liste de messages |
| 429 | RATE_LIMIT_EXCEEDED | Retry-After conservé s'il existe |
| 500 | INTERNAL_ERROR | Exception inattendue toujours journalisée ; détails internes absents de la réponse |
| 503 | SERVICE_UNAVAILABLE | Retry-After conservé s'il existe ; ne prouve pas l'échec final d'une mutation |

Les autres statuts d'exception HTTP conservent leur statut, avec REQUEST_FAILED côté 4xx ou INTERNAL_ERROR côté 5xx. Les en-têtes de transport utiles conservés sont Retry-After, WWW-Authenticate, Allow, X-RateLimit-Limit et X-RateLimit-Remaining ; Content-Type et X-Request-ID sont maîtrisés par le renderer. Chaque erreur est `Cache-Control: no-store`. `fields` reste un objet, même vide. Les messages de validation doivent être publics et ne jamais interpoler de secret.

Les codes métier spécifiques décrits dans les contrats futurs (HELP_OFFER_STALE_VERSION, etc.) ne sont pas encore implémentés. Leur lot devra ajouter un mapping explicite vers des messages publics dans le renderer commun et des tests ; ne pas exposer arbitrairement getMessage() ni construire un autre format d'erreur dans les Services.

## Pagination pour les trois domaines

Étendre `PaginatedRequest` et ajouter les filtres/tris autorisés via `parent::rules()`. Tout paramètre absent des règles est rejeté en 422, y compris un champ serveur comme author_id. Surcharger authorize() pour les lectures privées. La base commune n'accorde aucun droit métier. Transmettre `$request->pageData()` à une Query avec l'acteur séparé, appliquer la visibilité **avant** paginate(), puis passer explicitement perPage et page. Aucun filtre ne devient une autorisation de lecture.

Page initiale 1 ; taille 20 ; maximum 50. Zéro, négatifs, décimaux, tableaux et valeurs vides sont refusés. Le numéro de page est borné à 2147483647 pour garder une entrée entière portable et éviter les offsets débordants sur le runtime 64 bits retenu.

Étendre `PaginatedResourceCollection` avec `#[Collects(MaResource::class)]` et un LengthAwarePaginator. Les champs publics sont définis par la Resource du domaine, jamais par la sérialisation brute du modèle. Sortie `data` et `meta.current_page/per_page/last_page/total/from/to`. Une page vide a data=[], from/to=null ; la dernière page vaut au moins 1. Les liens automatiques sont omis pour ne pas réinjecter des paramètres ou hôtes client.

## OpenAPI et travail parallèle

`docs/OPENAPI.yaml` est le point d'entrée. Schémas, paramètres et réponses communs appartiennent au pilote du socle. Les trois fragments `openapi/identity.yaml`, `community.yaml` et `capsules-lab.yaml` appartiennent à leurs pilotes ; leurs chemins restent vides tant qu'aucun endpoint métier n'est livré. B05 définit les types partagés dans identity.yaml. L'extension x-haas-domain-fragments est un index documentaire, pas un assemblage automatique de routes.

Lors de l'intégration d'un endpoint, le pilote du socle ajoute une référence Path Item explicite dans le point d'entrée, par exemple `/api/v1/projects: {$ref: './api/openapi/community.yaml#/paths/~1api~1v1~1projects'}` lorsque ce chemin est effectivement implémenté. Un composant de fragment peut référencer `../../OPENAPI.yaml#/components/schemas/ApiError`. Ne pas copier les composants communs dans chaque domaine. Aucun chemin fictif n'est déclaré disponible.

Les tests lisent le YAML, résolvent les références locales et contrôlent les réponses HTTP par rapport aux propriétés du contrat. Ce contrôle ciblé n'est pas une validation exhaustive de toute la norme OpenAPI. Les tests PostgreSQL démontrent aussi les pages vides, la dernière page et la projection explicite des champs.

Références d'implémentation : [exceptions Laravel 13](https://laravel.com/docs/13.x/errors), [pagination Laravel 13](https://laravel.com/docs/13.x/pagination). L'exposition CORS de X-Request-ID et Retry-After sera testée avec le lot Sanctum/CORS ; elle n'est pas présumée active ici.
