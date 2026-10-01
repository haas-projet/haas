# Backend HAAS

Ces règles précisent les instructions de la racine. Le backend P0 est entièrement réalisé avant frontend/src. Au bootstrap, ce répertoire peut déjà contenir ce fichier : ne pas supprimer le répertoire pour contourner un installateur. Générer le squelette dans un répertoire temporaire puis fusionner seulement les fichiers attendus après inspection.

Cible de référence : Laravel 13 / PHP 8.4 / PostgreSQL, à confirmer avec les versions effectivement disponibles et l’hébergement. Sanctum SPA par sessions. Aucun basculement silencieux vers SQLite pour les tests d’intégrité/concurrence.

## Code
- Controllers : adaptation HTTP uniquement ; appel d’un service ou d’une query, Resource en sortie.
- FormRequests : liste blanche des entrées, validation et Policy initiale. Les champs protégés soumis sont rejetés, pas acceptés puis oubliés.
- DTO readonly : données typées, aucun objet Request ni helper auth(). Acteur transmis séparément par le serveur.
- Services par cas d’usage : transaction, vérification de l’état courant, verrouillage cohérent, audit atomique. Aucun JsonResponse.
- Queries : visibilité obligatoire avant recherche/pagination ; tris en liste autorisée ; eager loading.
- Models : relations, casts enum et scopes simples. Les règles critiques ne dépendent pas d’observers cachés.
- Policies : propriété et capacités, sans bypass administrateur universel. Recontrôler les commandes sensibles après verrouillage.
- Exceptions : rendu API commun dans bootstrap/app.php ; ne pas convertir toute panne en 422 ou 200.

## Tests et preuves
Chaque écriture possède des tests d’autorisation, validation, invariants et cas nominal. Les accès aux contenus privés, masqués et retirés sont testés par URL/API directes. Concurrence réelle avec plusieurs connexions/processus PostgreSQL et fixture validée avant lancement, pas une boucle séquentielle renommée « concurrente ».
Idempotence : acteur + route normalisée incluant la cible + clé + empreinte ; même intention = même clé ; droits actuels avant rejeu. Notifications après transaction avec stratégie de déduplication et reprise documentée.
Workers lab : configuration propre, base de fixtures et permissions restreintes ; l’étiquette de queue ne prouve pas l’isolation. Tester les interdictions d’accès réelles.

## Contrôles
Utiliser les scripts Composer du dépôt une fois créés : lint, analyse, test, test:integration. Consigner leurs commandes exactes dans docs/COMMANDS.md. Ne pas modifier une baseline pour cacher une nouvelle alerte. Mettre à jour OpenAPI dans le commit qui change l’API.
Lire le skill haas-backend-delivery pour une nouvelle commande métier ; haas-atomic-commits pour préparer un commit. Conserver BACKEND_GATE à PENDING tant que les preuves ou la revue manquent.


## Extension de l’atelier
Lire docs/product/ATELIER_COLLABORATIF.md et ADR-002 depuis la racine. Les cas humains, comparaisons approuvées et fiches versionnées sont P0. Les règles globales de quotas, pannes et visibilité s’appliquent aussi aux nouveaux endpoints et écrans.

## Déploiement retenu — Systalink + Vercel
ADR-004 prévaut pour l’hébergement : un VPS Systalink pour Laravel/PostgreSQL et un service laboratoire local restreint ; React sur Vercel ; sauvegarde distante. Un seul run actif global ; enfants séquentiels. Aucun second VPS ou staging permanent imposé. Ultimate est déclaré disponible, sans présumer les fonctions Ultimate Plus.
Deux origines de confiance : app.haas.example.com et api.haas.example.com (exemples). SESSION_DOMAIN=.haas.example.com ; baseURL absolue via VITE_API_URL ; CORS précis pour API et auth. Aucun jeton en localStorage ni wildcard vercel.app. B2 est hors du périmètre des cookies. Lire docs/deployment/ARCHITECTURE_DEPLOIEMENT.md et AUTH_CORS_SANCTUM.md depuis la racine.
Déployer seulement après revue et GO_PRODUCTION : API compatible d’abord, frontend ensuite ; artefacts/empreintes liés au commit testé. Aucun achat, partage de compte, contournement de forfait, publication de dépôt ou déploiement autorisé par ce seul pack.


## Cadrage actif : communauté d’abord

Mail source : docs/sources/MAIL_CADRAGE_CADEV.md (chemin relatif à la racine). ADR-005 adopte F16 projets et F17 annuaire volontaire, et précise F02 ask_question sans code obligatoire. Appliquer COMMUNAUTE_ET_PROJETS.md ; aucune UI de laboratoire n’est un passage imposé pour échanger. Projets/développeurs figurent dans la navigation et les parcours de réception.

122 lots proposés ; nouveaux BC01–BC08/FC01–FC04 ; AC01–90 (AC47 conditionnel), UX01–23. Préserver commits et preuves existantes. Une ancienne réception ne valide pas les nouveaux contrats. Aucun nouveau module de messagerie ou gestion d’équipe. Les identifiants historiques BV/FV sont conservés, pas des versions alternatives à développer.

## F18 — Rencontre par le coup de main

Lire ADR-006, docs/product/COUPS_DE_MAIN.md et docs/api/COUPS_DE_MAIN_API.md depuis la racine. Projet ouvert volontairement, offre pending privée, deux consentements avant projection publique, propriétaire seul décide. Réutiliser les fils : aucune invitation d’équipe, aucun chat privé, aucun accès au dépôt. L’acceptation ouvre une collaboration, ne certifie pas un résultat.

BH01–10 précèdent B39/B44 ; FH01–05 et UX21–23 après GO_FRONTEND ; RH01 avant réception. 122 lots actifs, 90 AC (AC47 conditionnel), 23 familles UX. Préserver les 106 identifiants antérieurs et les preuves existantes. Les anciennes règles « sans demande, aucune aide spontanée » sont remplacées par le parcours d’offre consenti. Systalink + Vercel et les limites du laboratoire restent inchangés.
