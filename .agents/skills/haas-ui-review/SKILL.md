---
name: haas-ui-review
description: "Effectuer une revue visuelle et d’usage HAAS dans le navigateur à partir d’écrans réels, avec captures et corrections vérifiées."
---

# Revue UI HAAS


## Préconditions
Application démarrée dans un environnement autorisé, compte de test fictif et outil de navigateur disponible. Sans rendu accessible, livrer seulement une revue de code explicitement limitée. Ne pas créer de fausses captures.

## Procédure
1. Choisir un parcours et ses données extrêmes : titre long, code, aucun résultat, erreur, version retirée.
2. Ouvrir l’interface aux largeurs 360/390/768/1280 px et vérifier 320 CSS px pour le reflow. Consigner navigateur et commit.
3. Réaliser les actions, pas seulement prendre une capture d’accueil. Vérifier clavier, retour navigateur et formulaires.
4. Capturer sans secrets ; ouvrir chaque image et contrôler coupe, chevauchement, texte, contraste, densité et action principale.
5. Classer les défauts par impact : tâche impossible, compréhension trompeuse, lisibilité, finition.
6. Corriger le plus petit périmètre, relancer les tests concernés puis reprendre la capture dans le même état.
7. Comparer avant/après et mettre à jour la recette. Ne pas accepter une copie de maquette comme preuve d’API fonctionnelle.

## Sortie
Rapport court : écran, état, viewport, observation, preuve, correctif, retest et défaut restant. Les tests utilisateurs sont distincts de l’auto-inspection par l’agent.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.


## Parcours Coup de main

Pour F18, lire le skill haas-help-offers et UX21–23. Deux entrées, une offre bornée, deux consentements, un accord puis une contribution réelle. « Proposition envoyée » et « Collaboration commencée » ne signifient pas « Travail terminé ». Aucune donnée privée dans un toast, une capture publique ou une projection sans consentement.
