---
name: haas-collaborative-verification
description: "Implémenter et contrôler les cas collaboratifs, les comparaisons B1 et les fiches de vérification HAAS, sans exécution de code utilisateur ni faux résultat."
---

# Atelier HAAS — garde-fous de réalisation

## Quand l'utiliser
Pour F13/F14/F15, les lots BV2/FV2 et les tests AC33–AC52. Lire le contrat docs/product/ATELIER_COLLABORATIF.md ; ne pas relire tous les skills sans lien.

## Procédure
1. Identifier la version et la révision source : ne jamais utiliser latest implicitement.
2. Séparer cas humain, revue documentaire, scénario livré par CI et résultat machine.
3. Exiger un profil approuvé et les mêmes entrées/oracle/configuration pour les deux côtés.
4. Produire deux enfants réels et des fixtures séparées ; vérifier quotas partagés, crash et reprises.
5. Calculer improved/unchanged/regressed/mixed/inconclusive sans score de sécurité.
6. Recontrôler droits et retraits sur cas, rapport, fiche, cache et ancienne URL.
7. Rendre les cinq états d'écran et les erreurs lisibles, les résultats mobiles empilés et le focus cohérent.
8. Tester un échec, une régression et une interruption ; montrer les limites au même niveau que le résultat.

## Interdictions
Aucun shell/URL/code/nom de classe utilisateur. Aucun cas converti en test par simple changement de statut admin. Aucun succès rempli à partir d'un attendu, baseline incorrecte distribuée comme recommandation ou donnée démo comptée comme adoption.

## Sortie
Petit diff, tests réellement exécutés, lien vers les règles F/AC et résultat observé. Pour la démo, distinguer données préparées et exécution en direct.

## Déploiement retenu — Systalink + Vercel
ADR-004 prévaut pour l’hébergement : un VPS Systalink pour Laravel/PostgreSQL et un service laboratoire local restreint ; React sur Vercel ; sauvegarde distante. Un seul run actif global ; enfants séquentiels. Aucun second VPS ou staging permanent imposé. Ultimate est déclaré disponible, sans présumer les fonctions Ultimate Plus.
Deux origines de confiance : app.haas.example.com et api.haas.example.com (exemples). SESSION_DOMAIN=.haas.example.com ; baseURL absolue via VITE_API_URL ; CORS précis pour API et auth. Aucun jeton en localStorage ni wildcard vercel.app. B2 est hors du périmètre des cookies. Lire docs/deployment/ARCHITECTURE_DEPLOIEMENT.md et AUTH_CORS_SANCTUM.md depuis la racine.
Déployer seulement après revue et GO_PRODUCTION : API compatible d’abord, frontend ensuite ; artefacts/empreintes liés au commit testé. Aucun achat, partage de compte, contournement de forfait, publication de dépôt ou déploiement autorisé par ce seul pack.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.
