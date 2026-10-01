# Conventions de commit et revue

Une intention testable par commit, pas un fichier par commit ni une feature entière non relisible. Objectif de 50–300 lignes manuscrites ; c’est un repère, pas une obligation de casser la cohérence. Squelette, lockfiles et types générés expliquent les exceptions.

Format : type(scope): description française précise. Types : feat, fix, test, refactor, style, docs, chore, ci. Éviter accents dans les noms de branches ; les messages peuvent contenir le français correct.

## Vérification avant commit
Inspecter status et diff ; tester les comportements changés ; contrôler diff --check ; sélectionner explicitement les fichiers ; vérifier l’absence de secrets ; ajouter les refs F/AC/lot pertinentes. Ne pas déclarer test « passé » sans exécution. Ne pas pousser automatiquement.

## Exemples
`feat(resolutions): refuser une proposition d’une autre demande`
`test(lab): vérifier l’arrêt d’un worker en cours de test`
`fix(http): conserver la session lors d’une indisponibilité API`
`style(forms): renforcer le contraste des champs en erreur`

## Revue croisée
L’auteur identifie les risques et tests. L’autre développeur vérifie les règles, droits, contrat et lisibilité. Un avis d’agent peut aider mais n’est pas une signature humaine. Les contrôles requis de main ne sont pas contournés. Ne pas effacer les petits commits par réécriture d’historique non autorisée.

Les commandes de production, push, merge, tag distant, modification de protections et achats exigent une autorisation adaptée. La demande de code et de commits locaux n’est pas une autorisation financière ou de publication.
