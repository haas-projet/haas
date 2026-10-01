---
name: haas-release-readiness
description: "Vérifier les gates backend, frontend ou release HAAS à partir de preuves réelles, de la matrice AC et des limites d’environnement."
---

# Réception HAAS


## Objet
Décider ce qui est réellement prêt. Lire le gate concerné, docs/quality/ACCEPTANCE_MATRIX.md et le suivi. Aucun changement de phase ne provient d’une simple estimation de progression.

## Procédure
1. Relier chaque exigence P0 à son comportement et sa preuve. Distinguer test local, CI distante, environnement hébergé et revue humaine.
2. Vérifier droits négatifs, versions figées, idempotence, lab réel, B2 séparé et absence de contenu privé accessible.
3. Vérifier licences, usages IA et intégrations. Rapport Qodana réel ou statut absent avec dérogation autorisée ; pas de faux badge vert.
4. Lire les cas non exécutés et incidents ; un défaut de sécurité/sincérité interdit la réception.
5. Pour backend : contrat complet et tests API avant GO_FRONTEND. Les tests navigateur complets restent à faire après implémentation React.
6. Pour frontend : parcours réels et revue visuelle/clavier, pas uniquement un build compilé.
7. Pour release : artefact identifié, déploiement autorisé, restauration/rollback testés, mêmes version/digests entre logiciel, vidéos et rapports.
8. Présenter recommandation et demandes de revue. Une signature humaine ou accord GO_FRONTEND doit venir d’une personne, pas être inventé.

## Sortie
Prêt / non prêt / prêt localement avec réserves externes, preuves, incidents, décisions humaines manquantes et prochaine étape. Ne promettre ni classement ni gain du concours.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.

## Déploiement retenu — Systalink + Vercel
ADR-004 prévaut pour l’hébergement : un VPS Systalink pour Laravel/PostgreSQL et un service laboratoire local restreint ; React sur Vercel ; sauvegarde distante. Un seul run actif global ; enfants séquentiels. Aucun second VPS ou staging permanent imposé. Ultimate est déclaré disponible, sans présumer les fonctions Ultimate Plus.
Deux origines de confiance : app.haas.example.com et api.haas.example.com (exemples). SESSION_DOMAIN=.haas.example.com ; baseURL absolue via VITE_API_URL ; CORS précis pour API et auth. Aucun jeton en localStorage ni wildcard vercel.app. B2 est hors du périmètre des cookies. Lire docs/deployment/ARCHITECTURE_DEPLOIEMENT.md et AUTH_CORS_SANCTUM.md depuis la racine.
Déployer seulement après revue et GO_PRODUCTION : API compatible d’abord, frontend ensuite ; artefacts/empreintes liés au commit testé. Aucun achat, partage de compte, contournement de forfait, publication de dépôt ou déploiement autorisé par ce seul pack.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.
