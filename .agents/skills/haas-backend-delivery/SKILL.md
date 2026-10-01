---
name: haas-backend-delivery
description: "Livrer un cas métier Laravel HAAS avec Requests, DTO, Services, Policies, tests PostgreSQL et contrat API ; utiliser pour tout lot backend B01–B44."
---

# Backend HAAS


## Périmètre
Implémenter une opération P0 du backend, pas l’interface React. Lire AGENTS.md, backend/AGENTS.md, le lot concerné de HAAS_CODEX_MASTER.md et les AC correspondants. Préserver l’existant.

## Procédure
1. Identifier l’acteur, la ressource, la transition, les données privées et les erreurs avant de coder.
2. Définir entrée/sortie OpenAPI et test nominal/négatif. Clarifier les trous de règle dans une décision, sans ajouter un produit entier.
3. Créer FormRequest, DTO readonly et Policy initiale. Les champs serveur soumis par le client sont rejetés.
4. Placer le workflow dans un Service ; visibilité dans une Query ; contrat public dans une Resource. Le Controller adapte, il n’orchestre pas les écritures.
5. Protéger la concurrence avec transaction, verrou, contraintes SQL et version. Recontrôler les droits sur l’état relu.
6. Écrire audit atomique ; notifications après transaction. Relance idempotente et réconciliation pour les tâches critiques.
7. Exécuter les tests pertinents sur PostgreSQL et les contrôles PHP configurés. Pour la concurrence, deux connexions/processus et fixtures déjà validées, pas une boucle séquentielle.
8. Inspecter le diff, documenter résultat réel, proposer ou créer le commit local autorisé.

## Cas HAAS à garder visibles
Seul l’auteur accepte une résolution ; admin ne contourne pas cette règle. Revue de capsule par une personne habilitée distincte. Version publiée immuable. Aucune exécution de code utilisateur dans les labs. La queue nommée lab n’est pas une isolation à elle seule.

## Sortie
Livrable ciblé, contrat, tests exécutés avec résultat, points non vérifiés, fichiers et commit réel. Ne pas déclarer backend terminé sans BACKEND_GATE ; ne pas commencer React pour masquer un backend incomplet.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.

## Déploiement retenu — Systalink + Vercel
ADR-004 prévaut pour l’hébergement : un VPS Systalink pour Laravel/PostgreSQL et un service laboratoire local restreint ; React sur Vercel ; sauvegarde distante. Un seul run actif global ; enfants séquentiels. Aucun second VPS ou staging permanent imposé. Ultimate est déclaré disponible, sans présumer les fonctions Ultimate Plus.
Deux origines de confiance : app.haas.example.com et api.haas.example.com (exemples). SESSION_DOMAIN=.haas.example.com ; baseURL absolue via VITE_API_URL ; CORS précis pour API et auth. Aucun jeton en localStorage ni wildcard vercel.app. B2 est hors du périmètre des cookies. Lire docs/deployment/ARCHITECTURE_DEPLOIEMENT.md et AUTH_CORS_SANCTUM.md depuis la racine.
Déployer seulement après revue et GO_PRODUCTION : API compatible d’abord, frontend ensuite ; artefacts/empreintes liés au commit testé. Aucun achat, partage de compte, contournement de forfait, publication de dépôt ou déploiement autorisé par ce seul pack.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.
