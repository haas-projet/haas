# HAAS — Communauté, projets et découverte

**Base :** mail de cadrage fourni [M], décisions de l’équipe [U], cahier des charges consolidé.  
**Statut :** spécification de conception [H] à implémenter et à vérifier. Aucun usage réel n’est attesté par ce document.

## 1. Positionnement et limite

HAAS est d’abord une plateforme communautaire : découvrir des développeurs et leurs projets, poser des questions, partager des connaissances et collaborer. L’atelier est sa fonctionnalité distinctive pour certains cas, pas une condition d’entrée. Un échange utile peut s’achever sans code, capsule, laboratoire ou comparaison.

**Promesse : Présentez vos projets. Trouvez de l’aide. Construisez ensemble.**

Deux compléments P0 sont retenus : **F16, fiches projet et demandes liées ; F17, découverte volontaire des développeurs**. F02 est précisé pour accepter une question de connaissance sans formulaire de bug. Il ne s’agit pas de trois nouveaux produits. Pas de fil social infini, de messagerie privée, de recrutement, de gestion d’équipe, de dépôt de code interne ni de nouveau moteur de test.

## 2. F16 — Fiche projet légère

### Données et présentation

| Champ | Règle proposée |
|---|---|
| id | UUID serveur ; adresse stable /projets/:id. |
| owner_id | Utilisateur authentifié ; jamais accepté du navigateur. |
| name | 3–80 caractères. |
| summary | 30–240 caractères ; description lisible sur une carte. |
| description | 80–4 000 caractères ; Markdown restreint et texte inerte. |
| project_stage | idea, prototype, in_development, launched ; état déclaré par l’auteur, pas validation de qualité. |
| technologies | 1–5 entrées du référentiel partagé ; aucune nouvelle taxonomie séparée. |
| help_sought | Facultatif, 1 500 caractères maximum ; ce que l’auteur recherche. |
| demo_url / repository_url | Facultatifs, HTTPS, 2 048 caractères maximum, sans identifiants intégrés ; jamais récupérés par le serveur. |
| publication_state | draft, published, archived ; distinct de project_stage et de la modération. |
| hidden_at / moderation_reason | Champs serveur pour retrait ; raison publique expurgée si nécessaire. |
| lock_version / timestamps | Conflit 409 si édition obsolète ; UTC, historique et audit. |

Aucune image téléversée, capture automatique, iframe de démonstration, prévisualisation OpenGraph, clone Git, import de dépôt ou exécution n’est requis. Utiliser un visuel initiales/technologies. Présenter un projet ne transfère pas les droits sur son dépôt, ne prouve pas sa propriété intellectuelle et n’autorise aucune copie de son code.

### Cycle et permissions

Le propriétaire actif et vérifié peut créer un brouillon, modifier ses champs, publier et archiver. La publication exige une fiche complète et une confirmation de visibilité. Les modifications publiées sont historisées ; le nom ou le statut n’est pas modifié silencieusement dans les traces de contribution. Un projet archivé reste consultable s’il était public et non masqué, avec l’étiquette « Archivé » ; il n’accepte plus de nouvelle demande liée et il est exclu par défaut du catalogue.

Un modérateur peut masquer/restaurer avec motif, pas réécrire le contenu au nom du propriétaire. Une restauration ne republie pas un brouillon et ne modifie pas la phase déclarée. Un membre suspendu ne peut pas publier, modifier, relier ou archiver. La suspension masque de la découverte ses projets ; la revue décide de la suite selon les règles de modération existantes.

Créer et publier sont des commandes métier distinctes. La création utilise l’idempotence commune ; les mises à jour et transitions contrôlent lock_version. Il n’y a ni publication sur simple PATCH de publication_state, ni suppression destructive de conversations.

### Catalogue et détail

Catalogue public paginé : recherche dans nom/résumé, technologie, phase déclarée, tri date de publication puis id ; pas de score de popularité. Filtres dans l’URL, page de 20, maximum 50. Indexer seulement les requêtes retenues et mesurer le résultat. Le détail montre le propriétaire, les technologies, le besoin d’aide et les échanges accessibles. Aucun compteur ne révèle des demandes privées.

### Lien entre projet et demande

`help_requests.project_id` est facultatif. Dans ce périmètre, **seul le propriétaire d’un projet peut lui rattacher sa propre demande**, et uniquement quand le projet est publié, non masqué, non archivé. Cette restriction simple évite des associations trompeuses. Un visiteur contribue ensuite dans les demandes publiques du projet ; il ne devient pas membre d’une équipe ni coauteur du dépôt.

Le projet ne reçoit pas un second fil de commentaires : toutes les discussions utilisent les demandes, commentaires et propositions existants. Sur une fiche propriétaire : « Demander de l’aide pour ce projet ». Pour un autre membre : « Voir les échanges » et, lorsqu’une demande est ouverte, « Proposer une aide » vers cette demande. Si aucune demande n’est ouverte et help_open=true, proposer « Je peux t’aider » vers F18 : une offre bornée, puis création d’un fil après consentement et acceptation du propriétaire. Si les coups de main sont fermés, l’expliquer et proposer d’explorer d’autres besoins ; aucun faux bouton de contact.

Changer le rattachement exige une demande encore en brouillon et aucun commentaire/proposition. Après publication, le lien est stable ; un retrait nécessaire passe par la modération auditée. Une demande liée n’hérite jamais d’un droit d’accès depuis une URL.

### Propagation de visibilité

Un projet masqué ou un propriétaire suspendu rend les listes et détails des demandes rattachées inaccessibles au public, ainsi que leurs commentaires, cas et propositions. Contrôler l’ancêtre dans Query/Policy, recherches, compteurs, notifications, caches et accès direct. Les personnes habilitées conservent une vue de traitement. Un lien enregistré n’accorde jamais le droit de lire.

Une capsule est un objet documentaire indépendant et soumis à revue : si elle cite ce projet/demande devenu masqué, supprimer les détails et extraits du lien source dans la Resource. Si elle contient elle-même un secret ou le contenu retiré, la masquer/revoir selon la procédure existante ; ne pas promettre qu’un simple masquage efface un secret déjà divulgué.

## 3. F17 — Découverte volontaire des développeurs

L’annuaire est un moyen simple de se retrouver, pas un système d’amis, de messagerie ou de recrutement. La publication d’un profil dans l’annuaire est une préférence distincte des contributions publiques déjà attribuées.

| Champ dans profiles | Règle proposée |
|---|---|
| directory_visible | Booléen, false par défaut ; choix explicite modifiable par le membre. |
| help_availability | not_specified, available, unavailable ; auto-déclaré, jamais présence en ligne. |
| contribution_interests | Texte facultatif, 300 caractères maximum, sans coordonnées privées obligatoires. |
| availability_updated_at | Horodatage serveur lors d’un changement de disponibilité ; pas last_login. |

Une inscription visible dans l’annuaire exige compte actif, courriel vérifié et directory_visible=true. Seules les propriétés publiques autorisées sont retournées : pseudonyme, bio courte, technologies, langue, intention d’aide et liens vers les contributions/projets visibles. Ne pas exposer courriel, IP, sessions, téléphone, date de dernière connexion, statut administratif ou données de modération.

Filtres P0 : pseudonyme/recherche textuelle simple, technologie, disponibilité déclarée. Pagination 20/max50 et tri stable par pseudonyme puis id. Pas d’inférence d’expertise, de recommandation IA, de géolocalisation ou de filtre politique/personnel. Le pays demeure facultatif dans le profil existant, mais aucun filtre pays n’est nécessaire au P0.

« Ouvert à une relecture Laravel » ne promet ni réponse ni délai. La date de mise à jour est consultable. Retirer sa présence de l’annuaire purge les résultats/cache concernés, sans effacer automatiquement l’attribution d’une contribution publique. Un compte suspendu ne figure plus dans l’annuaire, même par filtre ou requête directe. Les paramètres privés du propriétaire restent consultables selon le parcours existant du compte.

La collaboration se poursuit dans une demande publique. Le profil conduit aux projets/contributions ; sur un projet ouvert, F18 permet de proposer un coup de main même sans fil initial. Ce n’est ni un contact privé général ni une invitation d’équipe. Le propriétaire accepte et confirme la projection publique du résumé consenti.

## 4. F02 précisé — Poser une question sans être en panne

`help_intent` ajoute **ask_question** aux valeurs existantes unblock, review_solution et reproduce_behavior. Cette extension conserve le même fil, les mêmes autorisations et le même service de discussion ; elle ne crée pas un forum parallèle.

Pour ask_question : titre 15–140, contexte dans goal 30–2 000, question dans observed 30–4 000, 1–5 technologies. Les champs expected, attempts, code et environnement sont facultatifs ; ils restent affichables s’ils existent. Expected/attempts deviennent nullables pour ce mode ; migration non destructive et validation conditionnelle explicite. Les autres intentions conservent leurs règles. Le simple mot « aucune » n’est pas exigé pour remplir artificiellement un champ inutile.

Libellés : « Votre question », « Ce que vous souhaitez comprendre », « Ajouter un exemple — facultatif ». Expliquer avant publication que l’échange sera public. Une discussion peut apprendre quelque chose sans accepter une proposition, publier une capsule ou exécuter un test. Les commentaires restent disponibles ; une réponse structurée et sa résolution utilisent les règles actuelles sans transformer l’absence de laboratoire en erreur.

## 5. Architecture — extensions ciblées

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

## 6. Navigation et usage

Navigation principale : **Explorer · Projets · Développeurs**, bouton d’action **Demander de l’aide**. Dans le compte : **Mon espace · Mes contributions · Notifications**. Sur mobile, liens principaux dans un menu nommé, pas deux barres empilées avec dix destinations. Pas de laboratoire en action principale de l’accueil.

Accueil : promesse communautaire, « Explorer les projets » et « Demander de l’aide », trois aperçus de contenu public étiqueté s’il est éditorial. Un lien secondaire mène aux développeurs. Aucun compte demandé avant de comprendre l’utilité ; connexion demandée au moment de contribuer.

Le parcours de réception est : A publie un projet, ouvre une demande liée ; B le découvre et échange ; un cas ou une proposition apporte de la valeur ; A accepte si pertinent ; une capsule documentée permet à C de reprendre la solution. L’atelier B1 reste une seconde branche du même récit, jamais une étape forcée de toutes les interactions.

## 7. Charge et validation

F16/F17/ask_question ont apporté BC01–BC08/FC01–FC04. Le plan actif compte maintenant 122 lots S2/B72/F40/R8, car F18 ajoute BH01–BH10/FH01–FH05/RH01. Les 106 identifiants précédents sont conservés. Ne pas écraser le suivi du dépôt par ces états TODO documentaires.

AC53–AC68 restent la recette communautaire ; AC69–AC90 complètent les coups de main. UX18–20 restent applicables, avec leurs CTA F18 ; UX21–23 décrivent la découverte d’occasions, l’offre et sa décision. Les gates couvrent F01–F18 ; aucune ancienne approbation ne reçoit automatiquement ces ajouts.

## 8. F18 — Du profil au premier échange

Le parcours d’offre remplace l’impasse sans demande initiale : projet volontaire, proposition bornée, consentements explicites, propriétaire qui accepte et autorise un fil. Les détails actifs figurent dans COUPS_DE_MAIN.md et COUPS_DE_MAIN_API.md. Pas de messagerie privée, accès au dépôt ou équipe ; l’opt-in annuaire n’est pas obligatoire pour aider. Les demandes ordinaires restent utilisables directement.
