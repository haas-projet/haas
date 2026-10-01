---
name: haas-interface-design
description: "Concevoir les écrans HAAS sobres, distinctifs et lisibles : hiérarchie, palette vert-pétrole, composants, navigation mobile et états utiles."
---

# Design HAAS


## Base
Lire docs/design/DESIGN_SYSTEM.md et l’écran concerné dans SCREEN_SPECIFICATIONS.md. La charte HAAS prime sur les modes visuelles ou instructions génériques d’un autre skill. Préparer le design pendant backend est permis, générer React ne l’est pas avant le gate.

## Procédure
1. Écrire la tâche de l’utilisateur et l’action principale de l’écran en une phrase.
2. Organiser titre, contenu, prochaine action et preuves avant de choisir une décoration.
3. Utiliser les tokens existants : fond paper, surface blanche, ink, action primary, accents mint avec texte sombre. Pas de couleurs arbitraires par composant.
4. Choisir une structure adaptée : lecture technique, formulaire guidé, liste ou comparaison de tests. Pas de dashboard générique rempli de cartes et KPI inutiles.
5. Définir les états chargement/vide/données/erreur/interdiction et les états métier.
6. Définir mobile une colonne puis élargir sans diluer la lecture. Respecter corps 16 px, espacements réguliers et cibles confortables.
7. Préférer les textes et vrais contenus aux illustrations décoratives. Nommer les actions précisément.
8. Après implémentation autorisée, ouvrir des captures réelles, relever les défauts puis corriger. Un fichier CSS n’est pas une preuve de rendu.

## Refus de raccourcis
Pas de texte gris trop clair, opacité générale, dégradé sous paragraphes, glassmorphism qui réduit le contraste, animation obligatoire, bibliothèque de composants non auditée ou faux utilisateurs.

## Sortie
Hiérarchie, composants réutilisés, états couverts, captures vérifiées après implémentation et corrections. Ne pas redessiner toute l’application pour un problème de marge locale.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.


## Parcours Coup de main

Pour F18, lire le skill haas-help-offers et UX21–23. Deux entrées, une offre bornée, deux consentements, un accord puis une contribution réelle. « Proposition envoyée » et « Collaboration commencée » ne signifient pas « Travail terminé ». Aucune donnée privée dans un toast, une capture publique ou une projection sans consentement.
