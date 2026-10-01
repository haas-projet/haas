---
name: haas-react-architecture
description: "Implémenter les fonctionnalités React TypeScript HAAS après validation backend, avec guards, client HTTP unique, modèles, hooks et API typée."
---

# React HAAS


## Précondition
Consulter docs/quality/BACKEND_GATE.md. Sans validation et accord GO_FRONTEND, limiter le travail à la documentation, pas au code React.

## Procédure
1. Lire l’écran et les contrats disponibles, ses erreurs et capacités serveur.
2. Utiliser feature/pages/components/hooks/api/models/schemas ; services seulement pour une orchestration utile.
3. Générer les types depuis OpenAPI. Ne pas éditer generated/api-types.ts à la main.
4. Mettre les données serveur dans TanStack Query, les formulaires dans React Hook Form et le visuel local dans React. Pas de copie persistante de session.
5. Router par guards d’expérience, sans les confondre avec autorisation serveur. Distinguer chargement session, anonyme et panne réseau.
6. Centraliser Axios. Normaliser les erreurs et conserver cancellation/Retry-After. Interceptors installés avec eject ; pas de navigation impérative depuis core.
7. Invalider les clés concernées après succès réel, pas de succès optimiste pour résoudre/publier/tester. Propager AbortSignal et purger les caches privés au changement de compte.
8. Tester états critiques et actions au clavier. Vérifier le build et les imports.

## Limites
Ne pas introduire Next.js, RSC, Redux, SWR ou un deuxième client HTTP parce qu’un skill externe en parle. Aucune règle métier sensible réimplémentée seulement dans le frontend. B2 hors origine HAAS, données fictives exclusivement.

## Sortie
Comportement visible relié à l’API réelle, tests, erreurs gérées, type de preuve et limites. Tout mock est réservé aux tests, pas présenté comme fonctionnalité intégrée.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.

## Déploiement retenu — Systalink + Vercel
ADR-004 prévaut pour l’hébergement : un VPS Systalink pour Laravel/PostgreSQL et un service laboratoire local restreint ; React sur Vercel ; sauvegarde distante. Un seul run actif global ; enfants séquentiels. Aucun second VPS ou staging permanent imposé. Ultimate est déclaré disponible, sans présumer les fonctions Ultimate Plus.
Deux origines de confiance : app.haas.example.com et api.haas.example.com (exemples). SESSION_DOMAIN=.haas.example.com ; baseURL absolue via VITE_API_URL ; CORS précis pour API et auth. Aucun jeton en localStorage ni wildcard vercel.app. B2 est hors du périmètre des cookies. Lire docs/deployment/ARCHITECTURE_DEPLOIEMENT.md et AUTH_CORS_SANCTUM.md depuis la racine.
Déployer seulement après revue et GO_PRODUCTION : API compatible d’abord, frontend ensuite ; artefacts/empreintes liés au commit testé. Aucun achat, partage de compte, contournement de forfait, publication de dépôt ou déploiement autorisé par ce seul pack.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.
