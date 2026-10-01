---
name: haas-accessibility
description: "Vérifier les contrastes, clavier, focus, formulaires, reflow et messages HAAS ; utiliser avant réception d’un composant ou d’un écran."
---

# Accessibilité HAAS


## Cible
WCAG 2.2 AA sur les parcours livrés, sans certification automatique. Les valeurs et limites sont détaillées dans docs/design/DESIGN_SYSTEM.md. L’outil de contraste ne couvre que les paires opaques déclarées.

## Procédure
1. Exécuter node scripts/check-design-contrast.mjs et inspecter les couleurs réelles calculées dans le navigateur. Pas d’arrondi qui transforme un échec en succès.
2. Contrôler labels, aides/erreurs associées, titres, landmarks et liens d’évitement. Les noms visibles et accessibles restent cohérents.
3. Parcourir au clavier toutes les actions, menus et dialogues. Vérifier entrée/sortie et restauration du focus, absence de piège, focus visible non masqué.
4. Tester 200 % de zoom, reflow à 320 CSS px et contenu long. Défilement de code local, jamais toute la page.
5. Ne pas utiliser uniquement la couleur pour états de résolution, résultat ou erreur. Lire les messages sans dépendre d’un hover.
6. Viser 44 px pour les actions tactiles importantes selon la cible HAAS ; distinguer ce choix de confort des seuils normatifs et exceptions.
7. Exécuter l’analyse automatisée disponible, puis tests manuels et technologies d’assistance réellement accessibles. Ne pas annoncer les tests indisponibles comme faits.
8. Documenter défaut, impact, écran/viewport, correctif et retest.

## Sortie
Liste de constats avec preuves, résultats d’outils et limites. La réussite d’axe ou du script de couleurs ne justifie pas seule « accessible à 100 % ».

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.
