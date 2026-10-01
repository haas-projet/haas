# Frontend HAAS

Ne pas initialiser React avant BACKEND_GATE validé et accord humain GO_FRONTEND. Le dossier peut ne contenir que ces instructions ; préserver celles-ci au bootstrap. Lire docs/design/DESIGN_SYSTEM.md, SCREEN_SPECIFICATIONS.md et le lot courant du MASTER avant de construire un écran.

## Architecture
React + TypeScript strict + Vite. React Router pour routes/layouts/guards. Axios unique dans core/http ; TanStack Query pour données serveur ; React Hook Form et Zod pour formulaires. Aucun changement vers Next.js, SWR ou Redux suggéré par un skill générique sans décision du projet.
app → features → core/shared. Les features exposent une API publique index.ts raisonnable ; ne pas importer leurs fichiers internes depuis une autre feature. Les exemples Next.js/RSC d’un skill externe ne s’appliquent pas à cette SPA.
Pages composent ; composants rendent ; hooks gèrent queries/mutations ; api connaît HTTP ; services orchestrent seulement si utile ; models typent ; schemas valident le formulaire ; mappers convertissent. Aucun hook dans un service/API.

## Sessions et HTTP
AuthGuard distingue chargement, anonyme, connecté et erreur réseau. Les guards ne remplacent jamais les Policies. Avec Sanctum, ne pas mettre de bearer token dans localStorage. Cookies de session séparés du XSRF-TOKEN.
Interceptors : normaliser, ne pas avaler les erreurs ni déconnecter sur 403/503. Installer puis eject au nettoyage ; callback de session injecté sans dépendance inverse core → auth. Ne pas rejouer automatiquement les mutations sur 419 ou coupure.
Types de transport générés depuis OpenAPI ; pas de correction manuelle des fichiers générés. Propager AbortSignal et purger les caches privés au changement de session. Une mutation peut avoir réussi malgré une coupure : réconcilier.

## Interface
Réutiliser les tokens et composants du design system. Fond clair, encre sombre, action verte lisible. Corps 16 px minimum, métadonnées secondaires 14 px minimum, actions tactiles 44 px comme cible interne. Pas de texte essentiel gris pâle, de contenu porté uniquement par couleur, de bouton muet ou de faux compteur.
Chaque écran a ses états chargement, vide, succès, erreur et interdit. Formulaire de demande en trois étapes ; état de sauvegarde sincère ; code horizontal uniquement dans son conteneur. Boutons avec libellés français précis ; routes publiques lisibles sans inscription.

## Validation
Tests composants, erreurs API simulées en test uniquement, puis parcours réels contre Laravel. Contrôles clavier, focus, 320/360/390/768/1280 px, zoom 200 %, réorganisation au zoom 400 % quand applicable, mouvement réduit. Captures de l’interface exécutée, pas rendus fictifs.
Ne pas annoncer WCAG conforme après un seul audit automatique. Les skills haas-interface-design, haas-accessibility, haas-french-ux-writing, haas-react-architecture et haas-ui-review sont chargés selon le travail demandé. Aucune nouvelle charte par page.


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
