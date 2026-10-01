# HAAS — Architecture de développement

**Décision active :** communauté d’abord, atelier distinctif ; F01–F18. Backend Laravel complet avant React ; un VPS Systalink + Vercel. Compléments après mail : ADR-005 et COMMUNAUTE_ET_PROJETS.md. Architecture cible à réaliser, pas code déjà livré.


**Équipe :** trois développeurs Laravel / React
**Statut :** architecture cible et conventions de réalisation ; ce document n'est pas un dépôt applicatif installé ou testé.  
**Base :** cahier consolidé, mail [M], ADR-001/002/004/005. Détails atelier dans docs/product/ATELIER_COLLABORATIF.md.  
**Décision :** monolithe Laravel organisé en couches et domaines fonctionnels, SPA React / TypeScript organisée par fonctionnalité, monorepo privé.

## 1. Décisions de départ

- Backend : cible Laravel 13 / PHP 8.4 du cahier des charges, sous réserve de compatibilité de l'hébergement et des dépendances.
- Frontend : React, TypeScript strict, Vite et React Router ; versions exactes testées et figées au démarrage.
- Données : PostgreSQL ; base et identifiants de laboratoire distincts de ceux de l'application.
- Authentification : session Laravel Sanctum pour la SPA de confiance, cookies et protection CSRF. Pas de bearer token dans localStorage.
- État distant React : TanStack Query. Formulaires : React Hook Form et Zod proposés. État visuel simple : useState / useReducer.
- Transport : une instance Axios pour l'API HAAS ; aucun appel HTTP dispersé dans les composants.
- Qualité : GitHub Actions, tests, formatage, analyse statique PHP et TypeScript, Qodana lorsque l'accès/licence est confirmé.
- Pas de microservices, de framework DDD additionnel, de BaseRepository universel ou de store global supplémentaire sans besoin identifié.

Les noms de classes, conventions de dossiers et bibliothèques frontend précisés ci-dessous sont des propositions d'implémentation. Ils ne constituent pas des fonctionnalités supplémentaires imposées par le cahier des charges.

## 2. Monorepo

```text
haas/
├── backend/
├── frontend/
├── modules/
│   ├── B1-idempotency/          # Brique et scénarios approuvés
│   └── B2-offline-form/        # Démonstrateur isolé de la session HAAS
├── docs/
│   ├── ARCHITECTURE.md
│   ├── OPENAPI.yaml
│   ├── DATA_DICTIONARY.md
│   ├── VERSIONS.md
│   ├── DECISIONS.md
│   ├── TEST_PLAN.md
│   ├── THIRD_PARTY_NOTICES.md
│   ├── AI_USAGE.md
│   └── RUNBOOK.md
├── ops/
│   ├── deploy/
│   ├── backup/
│   ├── restore/
│   └── health/
├── .github/
│   ├── workflows/
│   │   ├── backend-ci.yml
│   │   ├── frontend-ci.yml
│   │   ├── contracts-ci.yml
│   │   ├── qodana.yml
│   │   └── deploy.yml
│   ├── CODEOWNERS
│   └── pull_request_template.md
└── README.md
```

`modules/` contient les deux briques démontrables. Ce dossier n'est ni un second backend ni un répertoire d'extensions utilisateur exécutables automatiquement.

## 3. Modules fonctionnels

| Domaine | Responsabilité |
|---|---|
| Identity | Comptes, sessions, profil, vérification du courriel, suspension et rôles |
| HelpRequests | Demandes, visibilité, cycle de vie, résolutions et réouvertures |
| Collaboration | Commentaires, propositions, édition historisée |
| Capsules | Capsules, versions, revue, contributeurs, kits, favoris et retours humains |
| Lab | Définitions approuvées, quotas, lancement, exécution et rapports machines |
| Moderation | Signalements, retrait et décisions motivées |
| Notifications | Notifications internes et traitements différés associés |

Ces domaines restent dans une seule application et une seule transaction lorsque le cas d'usage l'exige. Les relations Eloquent interdomaines sont admises ; l'orchestration d'une commande a un seul propriétaire. Pas de services A → B → A ni de doubles écritures métier cachées dans des observers.

## 4. Arborescence Laravel

Le squelette Laravel reste reconnaissable. Les sous-dossiers métier sont répétés dans les couches qui en ont besoin. Les Models et Policies gardent les emplacements conventionnels.

```text
backend/
├── app/
│   ├── Data/
│   │   ├── Identity/
│   │   ├── HelpRequests/
│   │   │   ├── CreateHelpRequestData.php
│   │   │   ├── UpdateHelpRequestData.php
│   │   │   ├── ResolveHelpRequestData.php
│   │   │   └── ReopenHelpRequestData.php
│   │   ├── Collaboration/
│   │   ├── Capsules/
│   │   └── Lab/
│   ├── Enums/
│   │   ├── Identity/Role.php
│   │   ├── Identity/AccountStatus.php
│   │   ├── HelpRequests/HelpRequestState.php
│   │   ├── Collaboration/ProposalState.php
│   │   ├── Capsules/CapsuleVersionState.php
│   │   ├── Lab/LabRunState.php
│   │   └── Moderation/ReportState.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Identity/
│   │   │   ├── HelpRequests/
│   │   │   │   ├── HelpRequestController.php
│   │   │   │   ├── ResolveHelpRequestController.php
│   │   │   │   ├── ReopenHelpRequestController.php
│   │   │   │   └── ArchiveHelpRequestController.php
│   │   │   ├── Collaboration/
│   │   │   ├── Capsules/
│   │   │   ├── Lab/
│   │   │   └── Moderation/
│   │   ├── Controllers/Auth/
│   │   ├── Requests/
│   │   │   ├── Identity/
│   │   │   ├── HelpRequests/
│   │   │   │   ├── ListHelpRequestsRequest.php
│   │   │   │   ├── StoreHelpRequestRequest.php
│   │   │   │   ├── UpdateHelpRequestRequest.php
│   │   │   │   ├── ResolveHelpRequestRequest.php
│   │   │   │   ├── ReopenHelpRequestRequest.php
│   │   │   │   └── ArchiveHelpRequestRequest.php
│   │   │   ├── Collaboration/
│   │   │   ├── Capsules/
│   │   │   └── Lab/
│   │   ├── Resources/
│   │   │   ├── Identity/MeResource.php
│   │   │   ├── Identity/PublicProfileResource.php
│   │   │   ├── HelpRequests/HelpRequestResource.php
│   │   │   ├── HelpRequests/HelpRequestSummaryResource.php
│   │   │   ├── Collaboration/
│   │   │   ├── Capsules/
│   │   │   └── Lab/
│   │   └── Middleware/
│   │       ├── EnsureAccountIsActive.php
│   │       └── AttachRequestId.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Profile.php
│   │   ├── Technology.php
│   │   ├── HelpRequest.php
│   │   ├── Comment.php
│   │   ├── Proposal.php
│   │   ├── Resolution.php
│   │   ├── Capsule.php
│   │   ├── CapsuleVersion.php
│   │   ├── Artifact.php
│   │   ├── LabDefinition.php
│   │   ├── LabRun.php
│   │   ├── LabResult.php
│   │   ├── ReuseReport.php
│   │   ├── Report.php
│   │   └── AuditEvent.php
│   ├── Policies/
│   │   ├── HelpRequestPolicy.php
│   │   ├── ProposalPolicy.php
│   │   ├── CapsuleVersionPolicy.php
│   │   ├── LabRunPolicy.php
│   │   └── ReportPolicy.php
│   ├── Queries/
│   │   ├── HelpRequests/ListHelpRequestsQuery.php
│   │   ├── HelpRequests/FindVisibleHelpRequestQuery.php
│   │   ├── Capsules/
│   │   └── Moderation/
│   ├── Services/
│   │   ├── Identity/
│   │   ├── HelpRequests/
│   │   │   ├── CreateHelpRequestService.php
│   │   │   ├── UpdateHelpRequestService.php
│   │   │   ├── ResolveHelpRequestService.php
│   │   │   ├── ReopenHelpRequestService.php
│   │   │   └── ArchiveHelpRequestService.php
│   │   ├── Collaboration/
│   │   ├── Capsules/
│   │   ├── Lab/
│   │   │   ├── StartLabRunService.php
│   │   │   ├── ExecuteLabRunService.php
│   │   │   └── ReconcileLabRunsService.php
│   │   └── Moderation/
│   ├── Contracts/Lab/LabRunner.php
│   ├── Infrastructure/Lab/
│   │   ├── ApprovedRunnerRegistry.php
│   │   └── DuplicateEventRunner.php
│   ├── Events/
│   ├── Listeners/
│   ├── Jobs/Lab/ExecuteLabRunJob.php
│   ├── Jobs/Notifications/
│   ├── Notifications/
│   ├── Exceptions/
│   │   ├── BusinessException.php
│   │   ├── HelpRequests/StaleHelpRequestVersion.php
│   │   └── HelpRequests/InvalidHelpRequestTransition.php
│   ├── Support/
│   │   ├── Http/ApiExceptionRenderer.php
│   │   ├── Audit/AuditWriter.php
│   │   └── Idempotency/IdempotencyService.php
│   └── Providers/AppServiceProvider.php
├── bootstrap/app.php
├── config/
├── routes/
│   ├── web.php
│   ├── auth.php
│   ├── api.php
│   └── api/v1/
│       ├── identity.php
│       ├── help-requests.php
│       ├── collaboration.php
│       ├── capsules.php
│       ├── lab.php
│       └── moderation.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── tests/
│   ├── Unit/
│   ├── Feature/
│   ├── Integration/
│   └── Architecture/
├── phpstan.neon
├── qodana.yaml
├── composer.json
├── composer.lock
└── .env.example
```

Les fichiers ci-dessus décrivent des emplacements et responsabilités. Les dossiers ne sont créés qu'au moment où un cas d'usage les utilise.

## 5. Contrat de chaque couche Laravel

| Couche | Fait | Ne fait pas |
|---|---|---|
| Route | URL, méthode, middlewares et contrôleur | Logique métier |
| Middleware | Session/compte, limite transversale, corrélation | Décision détaillée sur une ressource |
| FormRequest | Valide la forme des données, appelle la Policy pour l'autorisation initiale | Écrit en base ou accepte une résolution |
| Data / DTO | Transporte des valeurs validées, typées et immuables | Dépend de Request, auth() ou d'une réponse HTTP |
| Controller | Adapte HTTP vers DTO/service et service vers Resource | Requêtes Eloquent directes ou workflows |
| Policy | Décide qui peut agir sur quelle ressource | Effectue des mutations |
| Service | Exécute un cas d'usage, ses invariants, sa transaction et son audit | Retourne JsonResponse, lit Request ou masque une exception inattendue |
| Query | Applique visibilité, recherche, filtres autorisés, eager loading et pagination | Change l'état d'une ressource |
| Model | Relations, casts, attributs persistants et scopes simples | Gère un workflow complet ou l'affichage HTTP |
| Resource | Expose le contrat JSON, sans données privées inutiles | Fait des écritures ou déclenche des tâches |
| Job | Exécute une tâche bornée, idempotente et observable | Reconstruit une seconde version des règles métier |

### Services plutôt que couches redondantes

La convention retenue est `VerbeObjetService::handle(...)` pour une commande métier significative. Pas de `HelpRequestService` contenant toutes les opérations et pas de chaîne Controller → Action → Service → Manager → Repository si chaque classe délègue sans valeur ajoutée.

Les Query objects constituent une séparation pragmatique lecture/écriture, sans infrastructure CQRS distincte. Eloquent est utilisé directement par Services et Queries. Une interface est réservée à une frontière utile, comme l'exécution d'un laboratoire.

## 6. États et DTO

Valeurs proposées, à figer dans OpenAPI :

- HelpRequestState : draft, open, in_progress, resolved, archived.
- ProposalState : proposed, accepted, not_selected.
- CapsuleVersionState : draft, in_review, changes_requested, published, withdrawn.
- LabRunState : queued, running, passed, failed, error, timed_out.
- ReportState : new, under_review, resolved, dismissed.
- Role : member, moderator, admin.

Les traductions françaises restent des libellés d'interface. Les technologies et autres référentiels évolutifs restent en base, pas en enums.

```php
<?php

declare(strict_types=1);

namespace App\Enums\HelpRequests;

enum HelpRequestState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Archived = 'archived';
}
```

```php
<?php

declare(strict_types=1);

namespace App\Data\HelpRequests;

final readonly class ResolveHelpRequestData
{
    public function __construct(
        public string $proposalId,
        public string $validationNote,
        public int $lockVersion,
    ) {}
}
```

La présence d'un enum ne suffit pas à faire respecter les transitions. Le service vérifie l'état courant et les préconditions après verrouillage. Le DTO n'est jamais rempli à partir de `$request->all()` : une conversion explicite utilise uniquement les données validées.

## 7. Cas de référence : résoudre une demande

Route existante au cahier : `POST /api/v1/requests/{id}/resolve`.

1. Session, compte actif, courriel vérifié et limitation d'usage.
2. ResolveHelpRequestRequest : format UUID de proposal_id, texte de validation, lock_version entier ; rejet des champs protégés ; Policy initiale.
3. Contrôleur : acteur authentifié + identifiant de demande + DTO vers ResolveHelpRequestService.
4. Transaction : relire et verrouiller la demande, vérifier de nouveau les droits sur l'état courant, vérifier lock_version et la transition autorisée.
5. Vérifier que la proposition appartient à cette demande ; un UUID valide ne suffit pas.
6. Mettre à jour la proposition et les états pertinents, créer la résolution active et incrémenter lock_version.
7. Écrire l'audit dans la même transaction. Aucune notification externe avant validation de celle-ci.
8. Après commit : notification asynchrone. Une défaillance de notification n'efface pas la résolution.
9. Retourner la ressource actualisée. Le client met son cache à jour puis invalide les listes et contributions concernées.

La base impose aussi une seule résolution non révoquée par demande :

```sql
CREATE UNIQUE INDEX resolutions_one_active_per_request
ON resolutions (request_id)
WHERE revoked_at IS NULL;
```

Les conventions de verrouillage sont partagées par toutes les commandes concurrentes, notamment accepter, rouvrir et modifier une proposition. La réouverture révoque la résolution active, conserve l'historique et marque les capsules liées pour revue.

Ne pas ajouter un `Gate::before` qui autorise automatiquement tous les administrateurs : le cahier réserve l'acceptation à l'auteur de la demande, même face à un modérateur ou administrateur.

## 8. Arborescence React

```text
frontend/
├── src/
│   ├── app/
│   │   ├── providers/
│   │   │   ├── AppProviders.tsx
│   │   │   ├── QueryProvider.tsx
│   │   │   └── HttpInterceptorsProvider.tsx
│   │   ├── router/
│   │   │   ├── router.tsx
│   │   │   ├── paths.ts
│   │   │   └── guards/
│   │   │       ├── AuthGuard.tsx
│   │   │       ├── GuestGuard.tsx
│   │   │       ├── VerifiedEmailGuard.tsx
│   │   │       ├── ActiveAccountGuard.tsx
│   │   │       └── PermissionGuard.tsx
│   │   ├── layouts/
│   │   │   ├── PublicLayout.tsx
│   │   │   ├── MemberLayout.tsx
│   │   │   └── AdminLayout.tsx
│   │   └── App.tsx
│   ├── core/
│   │   ├── config/env.ts
│   │   └── http/
│   │       ├── api-client.ts
│   │       ├── api-error.ts
│   │       ├── normalize-api-error.ts
│   │       └── interceptors/error.interceptor.ts
│   ├── generated/api-types.ts
│   ├── features/
│   │   ├── auth/
│   │   │   ├── api/auth.api.ts
│   │   │   ├── models/session.model.ts
│   │   │   ├── services/auth.service.ts
│   │   │   ├── queries/session.queries.ts
│   │   │   ├── hooks/useSession.ts
│   │   │   ├── hooks/useLogin.ts
│   │   │   ├── hooks/useLogout.ts
│   │   │   ├── schemas/login.schema.ts
│   │   │   ├── components/
│   │   │   ├── pages/
│   │   │   ├── tests/
│   │   │   └── index.ts
│   │   ├── help-requests/
│   │   │   ├── api/help-requests.api.ts
│   │   │   ├── models/help-request.model.ts
│   │   │   ├── models/resolve-help-request.input.ts
│   │   │   ├── schemas/help-request-form.schema.ts
│   │   │   ├── mappers/help-request.mapper.ts
│   │   │   ├── services/help-request-draft.service.ts
│   │   │   ├── queries/help-requests.keys.ts
│   │   │   ├── queries/help-requests.queries.ts
│   │   │   ├── hooks/useHelpRequests.ts
│   │   │   ├── hooks/useHelpRequest.ts
│   │   │   ├── hooks/useCreateHelpRequest.ts
│   │   │   ├── hooks/useResolveHelpRequest.ts
│   │   │   ├── components/HelpRequestCard.tsx
│   │   │   ├── components/HelpRequestForm.tsx
│   │   │   ├── components/ResolveProposalDialog.tsx
│   │   │   ├── pages/HelpRequestsPage.tsx
│   │   │   ├── pages/HelpRequestDetailsPage.tsx
│   │   │   ├── pages/CreateHelpRequestPage.tsx
│   │   │   ├── tests/
│   │   │   └── index.ts
│   │   ├── collaboration/
│   │   ├── capsules/
│   │   ├── lab/
│   │   ├── moderation/
│   │   ├── profiles/
│   │   └── notifications/
│   ├── shared/
│   │   ├── ui/
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── utils/
│   │   └── types/
│   ├── styles/
│   └── main.tsx
├── tests/e2e/
├── qodana.yaml
├── eslint.config.js
├── tsconfig.json
├── vite.config.ts
├── package.json
├── package-lock.json
└── .env.example
```

`services/` et `mappers/` ne sont pas obligatoires pour une opération triviale. Un hook peut appeler directement une fonction API lorsque rien n'est à orchestrer ou transformer. Le service de brouillon proposé prépare/compare les valeurs : il ne persiste pas automatiquement du code privé ou des secrets dans le navigateur.

## 9. Contrat des couches React

| Couche | Responsabilité |
|---|---|
| pages | Assemblage de l'écran et liaison des composants aux hooks |
| components | Rendu, interaction, accessibilité ; pas de connaissance d'Axios |
| hooks | Requêtes et mutations TanStack Query, états pending/error et invalidation |
| api | Méthode, chemin, paramètres et DTO de transport ; aucune UI |
| services | Orchestration frontend utile, sans hooks React ni duplication des règles serveur |
| models | Types TypeScript et valeurs de domaine, sans appels réseau |
| schemas | Validation des formulaires et, lorsque prévu, validation de données externes à l'exécution |
| mappers | Conversions explicites DTO ↔ formulaire / vue |
| guards | Navigation selon la session et les permissions reçues |
| interceptors | Normalisation transversale des erreurs et signalement d'expiration de session |

Dépendances : `app → features → core/shared`. `core` et `shared` n'importent pas les fonctionnalités. Une fonctionnalité n'importe une autre que via son `index.ts` public lorsqu'un besoin est identifié. Les imports circulaires sont interdits.

Aucune requête HTTP dans un composant JSX. Aucun hook dans une fonction API ou un service. Aucun rôle provenant de localStorage ne donne un droit.

## 10. Authentification et guards

La SPA React est sur Vercel et l’API Laravel sur Systalink. Exemples : app.haas.example.com et api.haas.example.com ; cookies sous .haas.example.com. Deux origines, un même parent de confiance. B2 reste en dehors de ce périmètre.

Le client HTTP utilise VITE_API_URL absolue, withCredentials et withXSRFToken. Auth : GET /sanctum/csrf-cookie, POST /login, GET /api/v1/me. Aucun bearer token en localStorage. CORS couvre routes API et auth et autorise seulement les origines approuvées.

Lire docs/deployment/AUTH_CORS_SANCTUM.md pour les réglages et tests. Un guard distingue chargement, anonyme, connecté et panne réseau. Une permission reçue pilote l’interface, jamais l’autorisation définitive. Le logout annule les lectures et purge les caches privés. Les previews non fiables ne touchent pas aux données de production.

## 11. Interceptors et erreurs

Un `ApiError` normalisé porte `status`, `code`, `message`, `fields` et `requestId`. Il conserve un statut réseau distinct lorsque le serveur n'a pas répondu. Une annulation volontaire n'est pas transformée en alerte utilisateur.

| Cas | Comportement |
|---|---|
| 401 hors parcours login/session attendu | Signaler une session absente/expirée ; purger les données privées et laisser le routeur décider |
| 403 | Afficher l'interdiction, sans déconnecter |
| 404 | Écran ressource introuvable selon contexte |
| 409 | Conflit de version ou d'état ; conserver le formulaire en mémoire et proposer de recharger |
| 419 | Réinitialiser le contexte CSRF/session de manière contrôlée ; ne pas rejouer aveuglément une mutation |
| 422 | Transmettre les erreurs aux champs |
| 429 | Respecter Retry-After lorsqu'il existe ; montrer le délai |
| 503 / réseau | Disponibilité ou connectivité ; ne pas conclure que la commande n'a jamais atteint le serveur |

L'interceptor ne fait pas de navigation impérative ni de notification pour chaque requête. Il rejette toujours l'erreur après normalisation. Son callback de session est injecté par `app`; `core/http` n'importe pas le module auth. Installation avec nettoyage `eject` pour éviter les doublons au remontage.

## 12. Contrats API et typage

Les chemins, payloads, réponses, erreurs et enums sont définis dans `docs/OPENAPI.yaml` et vérifiés contre les tests Laravel. Les types de transport TypeScript sont générés dans `generated/api-types.ts` ; les types de vue ne sont ajoutés que lorsqu'ils diffèrent. Aucun type généré n'est modifié à la main.

Un type TypeScript n'est pas une validation des données à l'exécution. Les schémas de formulaires ne remplacent pas FormRequest ; les inputs externes sensibles sont parsés lorsque nécessaire.

Erreur du cahier, à produire explicitement par ApiExceptionRenderer :

```json
{
  "error": {
    "code": "HELP_REQUEST_STALE_VERSION",
    "message": "Cette demande a été modifiée. Rechargez sa dernière version.",
    "fields": {}
  },
  "request_id": "7e4a2eaf-6136-4ab7-903c-6509f7556a40"
}
```

Ce format n'est pas supposé être la sortie Laravel par défaut. Le renderer est enregistré dans `bootstrap/app.php` et couvre aussi les exceptions de validation, authentification et autorisation. Les exceptions inattendues restent des 500 journalisées, sans trace publique. Les en-têtes utiles tels que Retry-After sont préservés.

Conventions : `/api/v1`, UUID, timestamps ISO 8601 UTC, data/meta pour les listes, taille maximale de page 50, tris et filtres en liste autorisée. `PATCH /requests/{id}` n'accepte pas arbitrairement state, author_id ou un résultat de test.

## 13. Cache, mutations et idempotence

Les clés TanStack Query sont centralisées par fonctionnalité. Les paramètres de filtres et de pagination appartiennent à la clé. Les queries de données privées sont liées à l'identité courante ou purgées systématiquement lors d'un changement de session.

Après résolution : remplacer le détail par la réponse serveur et invalider les listes, propositions, contributions et notifications concernées. Éviter un cache optimiste pour la validation d'une résolution ou un résultat de laboratoire.

Les mutations ne sont pas retentées automatiquement sans garantie d'idempotence. Pour une commande couverte par Idempotency-Key : créer une clé par intention, la conserver lors de la reprise de la même opération et utiliser une nouvelle clé pour une nouvelle intention. Une même clé et une charge utile différente donnent 409. L'authentification et l'autorisation courantes restent requises avant de rejouer une réponse enregistrée.

Les GET peuvent recevoir une reprise bornée selon le statut. Les AbortSignal sont propagés jusqu'au client HTTP. Le résultat d'une commande interrompue par le réseau est réconcilié avec le serveur, pas déclaré échoué sans vérification.

## 14. Laboratoires — service local restreint

StartLabRunService/StartComparisonService réservent les unités, manifestes et scénarios. Le job Laravel reste orchestrateur côté APP. Un service standalone haas-lab, distinct de Laravel et de son .env, exécute seulement les scénarios approuvés via un canal Unix local restreint. Il possède seulement ses fixtures et leur rôle SQL, pas la base métier ni la queue privée.

Un seul run actif global, comparaisons séquentielles, cinq unités/h/membre et deux unités par comparaison. Les finalisations sont uniques ; délais, pannes et reprises sont traçables. Aucun code utilisateur exécutable ou récupération d’URL libre. B2 utilise une API et une base fictives séparées.

Même VPS ne signifie pas même identité ; mais cela ne vaut pas deux machines isolées. La recette de droits et de charge conditionne l’ouverture. Voir ARCHITECTURE_DEPLOIEMENT.md et DEPLOYMENT_GATE.md.

## 15. Qualité et intégration continue

### Backend

- Validation Composer et verrouillage des dépendances.
- Laravel Pint et analyse PHPStan/Larastan configurée au niveau convenu.
- Tests unitaires des règles pures ; tests Feature des routes et Policies.
- Tests d'intégration sur PostgreSQL, notamment transactions, index partiels et concurrence.
- Tests des jobs, transitions, quotas, réouvertures et visibilité des contenus retirés.
- Tests d'architecture interdisant les réponses HTTP dans Services, les accès Request dans Data et les usages de modèles dans les contrôleurs hors binding/typage.

### Frontend

- ESLint, formatage, TypeScript strict, build reproductible.
- Tests composants avec React Testing Library / Vitest proposés.
- Tests API simulée, guards, erreurs 401/403/409/419/422/429 et annulations.
- Parcours Playwright réels, y compris session/CSRF, résolution et publication.
- Contrôle des frontières d'import et des types générés.

### Qodana

Deux configurations backend/frontend peuvent être utilisées pour obtenir des rapports distincts. Qodana PHP et JS/TS sont des outils d'analyse statique ; ils ne remplacent pas les tests métier. Vérifier les licences, le périmètre d'envoi à Qodana Cloud et les permissions avant activation. Le linter PHP documenté en septembre 2026 est proposé sous Ultimate/Ultimate Plus. Les seuils de qualité sont explicites ; aucune analyse absente n'est présentée comme verte.

### GitHub Actions

Les jobs de tests ne portent pas de secrets de production. Les analyses nécessitant des secrets ne s'exécutent pas sur du code non approuvé provenant de forks. Permissions minimales, actions épinglées sur des références immuables vérifiées, pas de pull_request_target exécutant du code non fiable avec des secrets.

Les contrôles requis sont lancés même lorsque des filtres de chemins sont utilisés, ou un job agrégateur produit un état déterministe. Un job requis ignoré ne doit pas laisser la PR bloquée sans explication. Aucun déploiement tant que les jobs requis ont échoué ou n'ont pas réellement exécuté leur contrôle.

## 16. Tests d'acceptation minimum du module de référence

1. Une personne non connectée ne résout pas une demande.
2. Un membre non auteur reçoit un refus, y compris par appel HTTP direct.
3. Un administrateur non auteur ne peut pas forcer l'acceptation.
4. Une proposition d'une autre demande ne peut pas être acceptée.
5. Un lock_version ancien produit un conflit.
6. Deux commandes concurrentes n'aboutissent qu'à une seule résolution active.
7. Une réouverture conserve l'historique et invalide le caractère actuel de la résolution.
8. Le frontend ne montre pas un succès avant confirmation du serveur.
9. Une perte réseau après soumission ne crée pas une seconde opération lors de la reprise autorisée.
10. Un utilisateur B ne voit pas les caches privés laissés par un utilisateur A après déconnexion.
11. Un 503 lors de /me ne provoque pas une fausse déconnexion définitive.
12. Les erreurs de formulaire et de conflit conservent les saisies en mémoire, sans persistance automatique de secrets.

## 17. Livraison et exploitation — Systalink + Vercel

Un VPS Systalink héberge Laravel/PostgreSQL, les jobs et le runner local. React est publié sur Vercel ; les sauvegardes sont chiffrées et distantes. Aucun serveur LAB supplémentaire ou staging permanent n’est imposé. La recette locale/CI est complétée par les contrôles des vrais domaines avant ouverture.

Les artefacts validés sont identifiés par SHA et empreinte ; une release API compatible précède la promotion du frontend. La publication automatique Vercel ne contourne jamais les gates. Les secrets CI restent hors des contributions non approuvées. Qodana Ultimate est déclaré disponible, les fonctions Plus ne sont pas présumées.

Le rollback conserve les données ; les sauvegardes sont restaurées une fois hors production. Domaine, panier, forfait Vercel et service de courrier sont des paramètres à confirmer, pas des secrets ou adresses à inventer. Voir ADR-004.

## 18. Travail à trois — séquence active

Backend P0 complet, nouveaux cas et comparateur inclus → BACKEND_GATE et GO_FRONTEND humain → frontend → recette. Les trois personnes se répartissent les domaines backend, chacun avec ses tests et contrats ; voir docs/execution/BACKEND_A_TROIS.md pour les branches, responsabilités et dépendances. La répartition frontend sera fixée après GO_FRONTEND. Le précédent ordre vertical initial est archivé, pas actif.

Les nouveaux domaines sont VerificationCases, Comparisons et Evidence. Même structure : FormRequests/Data/Controllers/Policies/Queries/Services/Resources. Services : SubmitVerificationCaseService, ReviewVerificationCaseService, StartComparisonService, ExecuteComparisonService, ReconcileComparisonsService. Queries : VerificationSummaryQuery et FindVisibleComparisonQuery. Le calculateur de conclusion est une classe pure testable. Pas de duplication de runner ni de quota dans un deuxième moteur.

React ajoute features/verification-cases, features/comparisons et features/evidence ; API typée, hooks TanStack, modèles, schémas et composants. ComparisonReportPage est accessible sans permettre l’écriture d’un résultat. Les données de démo sont explicitement séparées.

La table comparison_runs référence les lab_runs enfants et un profil approuvé. L’immutabilité des manifests, la compatibilité des assertions, les quotas partagés et les invariants de révision sont décrits dans le contrat atelier. Les migrations sont additives, les champs protégés rejetés.

## 19. Références consultées

Sources produit : HAAS_Cahier_des_charges_v1.docx, sections 09, 21–27, 30–33. Les noms de fichiers et choix d'organisation sont la proposition d'architecture de ce document.

Sources techniques officielles consultées le 30 septembre 2026 :

- Laravel : Release Notes 13.x ; Validation ; Authorization ; Sanctum ; Query Builder ; Queues ; Error Handling.
- React Router : Routing, mode déclaratif et routes imbriquées.
- Axios : Interceptors et suppression des interceptors.
- TanStack Query : Query Invalidation et Query Cancellation.
- PostgreSQL : Partial Indexes.
- JetBrains Qodana : GitHub Actions ; PHP ; JavaScript and TypeScript.
- GitHub Actions : Secure use reference.

Ces documentations doivent être rapprochées des versions effectivement verrouillées dans le dépôt. Décision Déploiement retenu : ADR-004 prévaut sur les hypothèses d’hébergement antérieures. Ce document ne certifie ni la recevabilité des licences au concours ni le fonctionnement d'un déploiement non encore réalisé.


## Communauté — domaines et dépendances


### Laravel

Créer Models/Project, Enums/Projects/{ProjectStage,ProjectPublicationState}, Enums/Identity/HelpAvailability, ProjectPolicy, Data/Projects, Requests/Projects, Resources/Projects, Queries/Projects et Services/Projects. Commandes : CreateProject, UpdateProject, PublishProject, ArchiveProject et Moderation dédiée au type project. Les classes portent le suffixe Service selon la convention existante.

Identity conserve les préférences de profil et l’annuaire : UpdateProfileRequest/Data/Service étendus par liste de champs, ListDevelopersRequest/Query et DeveloperSummaryResource séparée de MeResource. Pas de sérialisation brute de User. HelpRequests vérifie le lien au projet et l’intention ; pas de ProjectService monolithique ni d’accès Eloquent dispersé dans les contrôleurs.

### Données

Table projects (UUID, owner_id, données éditoriales, états séparés, lock_version, published_at, hidden_at, timestamps). Pivot project_technologies unique (project_id,technology_id). Ajouter help_requests.project_id nullable avec FK ; aucune suppression en cascade des discussions. Étendre profiles et enum HelpIntent ; migration de données existantes contrôlée.

Index proposés : projects(publication_state,published_at,id), projects(owner_id,created_at), project_technologies(technology_id,project_id), help_requests(project_id,created_at). L’annuaire filtre le statut/vérification dans users et l’opt-in dans profiles ; mesurer avant d’ajouter un index supplémentaire.

### API cible

| Méthode et route /api/v1 | Contrat |
|---|---|
| GET /projects | Catalogue public filtré ; brouillons/masqués jamais exposés. |
| GET /projects/{project} | Détail accessible ; capacités calculées selon l’acteur. |
| POST /projects | Créer son brouillon ; membre actif vérifié ; Idempotency-Key. |
| PATCH /projects/{project} | Champs autorisés et lock_version ; propriétaire. |
| POST /projects/{project}/publish | Valider fiche/visibilité puis publier ; propriétaire. |
| POST /projects/{project}/archive | Archiver avec motif et lock_version ; propriétaire. |
| GET /projects/{project}/requests | Seulement demandes et compteurs visibles à cet acteur. |
| GET /developers | Liste volontaire, comptes actifs vérifiés, filtrage borné. |
| PATCH /me/profile | Étendre la route de profil existante ; si son chemin diffère dans le dépôt, garder un seul chemin et le documenter. |
| POST /requests | Ajouter project_id facultatif et help_intent=ask_question ; toutes les règles existantes s’appliquent. |
| POST /reports | Type project dans la liste autorisée ; pas de classe libre. |

Les actions de modération réutilisent les routes d’administration des signalements. Erreurs HAAS communes (401/403/404/409/422/429), tests de schéma et types OpenAPI générés. Une route ci-dessus est une cible, pas une implémentation déjà fournie.

### React

Créer features/projects et features/developers, avec api/models/schemas/hooks/queries/components/pages/tests selon besoin. Le client HTTP, guards, erreurs, forms et composants sont réutilisés. `shared` n’importe pas ces features. Les modèles de transport viennent d’OpenAPI, pas d’un doublon manuel.

Capacités `can.edit`, `can.publish`, `can.archive`, `can.create_request` calculées par le serveur. Les boutons suivent ces capacités sans remplacer les Policies. Invalider catalogue, détail, listes de demandes et profils concernés après mutation. Un retrait de l’annuaire purge la recherche et le cache de profil adapté sans fabriquer un nombre de membres.

Le parcours communautaire ne dépend pas du laboratoire. Une panne du runner ne doit bloquer ni projets, ni annuaire, ni échanges. La couche Identity porte les préférences volontaires ; aucun booléen local au navigateur ne décide d’un droit. La fiche projet présente un travail, pas un droit de propriété vérifié sur son dépôt externe.

## Extension retenue — F18 Coup de main

ADR-006 conserve les couches et l’hébergement. Lire COUPS_DE_MAIN.md et docs/api/COUPS_DE_MAIN_API.md pour le contrat complet. Ajouter HelpOfferState/HelpContributionCategory, HelpOffer, HelpOfferPolicy, DTO/Requests/Resources privés et publics séparés, Queries et Services par commande. L’acceptation englobe création/rattachement de demande et projection publique dans une seule transaction. Les règles de demande ordinaires sont réutilisées, pas réécrites.

Frontend : features/help-offers ; api/models/schemas/queries/hooks/components/pages/tests. Deux entrées d’accueil et UX21–23, pas de nouveaux clients HTTP ou stores persistants. Clés privées liées à l’acteur, invalidation des projets/offres/progrès/fils et purge complète au logout. Générer les types depuis le contrat réel. Aucun changement de code sans tests des nouveaux droits, consentements et courses.

Un projet sans demande peut recevoir une offre uniquement s’il a été ouvert volontairement ; le propriétaire accepte et autorise le fil. L’annuaire opt-in n’est pas requis. Offre pending privée, pas de notification privée réciproque libre ; après acceptation seule la projection consentie est publique dans le fil autorisé. Accepté ne signifie ni terminé ni testé.
