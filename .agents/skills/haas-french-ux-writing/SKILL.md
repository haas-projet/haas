---
name: haas-french-ux-writing
description: "Écrire ou réviser les libellés et messages français HAAS pour simplifier les tâches et distinguer résultats déclarés, acceptés et testés."
---

# Textes UX HAAS


## Objectif
L’utilisateur comprend la prochaine action et ce qui s’est réellement passé. Lire le contexte de l’écran et le vocabulaire produit, pas seulement une chaîne isolée.

## Procédure
1. Nommer l’objet : demande, proposition, capsule, version, rapport. Expliquer capsule la première fois.
2. Utiliser un verbe précis : Publier la demande, Soumettre à la revue, Accepter cette proposition, Rejouer le scénario.
3. Pour une erreur, décrire situation et action utile sans culpabiliser. Ne pas exposer de SQL, chemin serveur ou secret.
4. Distinguer enregistré sur serveur, conservé localement et envoi incertain. Une reprise attend l’accusé de réception.
5. Distinguer accepté par demandeur, retour humain déclaré et test du laboratoire ; préciser version et date.
6. Garder les limites visibles : une assertion réussie ne certifie pas la sécurité de tout le module.
7. Préparer état vide et attente sans promesse de réponse rapide ou compteur inventé.
8. Relire accents, pluriels, cohérence et longueurs sur mobile. Ne pas employer d’anglais générique comme Submit/Success dans l’interface française.

## Sortie
Table avant/après avec écran et raison si revue ; clés/texte cohérents si implémentation. Ne pas modifier les codes d’erreur API pour améliorer un libellé utilisateur.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.


## Alignement communauté / mail CADEV

Lire ADR-005 et COMMUNAUTE_ET_PROJETS.md si le lot touche la communauté. Les projets/demandes liées et l’annuaire volontaire font partie du P0 ; la question simple ne requiert aucun code. L’accueil privilégie projets, développeurs et échanges, l’atelier reste secondaire à l’entrée. Ne pas ajouter messagerie privée, équipes, score ou import de dépôt.

Appliquer les tests AC53–68 et les écrans UX18–20 ; prévenir liens tiers trompeurs, exposition de champs privés, associations projet non autorisées et cache après masquage/opt-out. Une disponibilité est auto-déclarée, jamais un statut en ligne. Le mail ne prescrit pas nos choix techniques et les tests du pack ne prouvent aucune exécution du produit.


## Parcours Coup de main

Pour F18, lire le skill haas-help-offers et UX21–23. Deux entrées, une offre bornée, deux consentements, un accord puis une contribution réelle. « Proposition envoyée » et « Collaboration commencée » ne signifient pas « Travail terminé ». Aucune donnée privée dans un toast, une capture publique ou une projection sans consentement.
