---
name: haas-atomic-commits
description: "Découper et créer des commits locaux HAAS petits, testés et explicitement nommés ; utiliser avant un commit ou une reprise de session."
---

# Commits HAAS


## Précondition
Lire docs/execution/COMMIT_CONVENTION.md et inspecter le dépôt. La demande autorise des commits locaux cohérents, pas push, publication, achat ou réécriture destructive.

## Procédure
1. Examiner branche, status et diff avant toute modification. Distinguer travail de l’utilisateur et travail du lot.
2. Choisir une intention métier testable avec ses tests, pas un regroupement par extension de fichier.
3. Découper si le diff manuscrit est trop large ; 50–300 lignes est un repère, pas une raison de produire des commits cassés.
4. Exécuter les contrôles réellement configurés. Signaler les indisponibilités et ne pas les compter comme réussites.
5. Inspecter git diff --check et les fichiers ciblés. Stage explicite, jamais git add . aveugle.
6. Vérifier secrets, fixtures, versions et licences. Écrire type(scope): action française précise, avec lot/F/AC dans le corps utile.
7. Créer le commit seulement si identité Git est configurée ; ne pas inventer de nom/email. Ne pas contourner les hooks.
8. Lire le SHA réel et compléter HANDOFF ou le bilan. Le SHA d’un commit ne se met pas dans ce même commit par une boucle d’amendements.

## Sortie
Commit réel ou raison de l’absence de commit, tests exécutés, fichiers restant modifiés et prochain lot. Ne pas simuler la revue humaine de l’autre membre.

## Repère atelier
Pour les cas, comparaisons et preuves, consulter le contrat Atelier et le skill haas-collaborative-verification. Le nouveau positionnement ne permet ni preuve fictive ni dépassement du périmètre autorisé.
