# Reprise — communauté et entraide

Branche : `backend/communaute-entraide`. Lot courant : B11, issu du travail conservé de mdev44-code et complété à la demande de l'utilisateur. Lire [PROGRESS.md](PROGRESS.md), [la preuve B11](../../../quality/B11_COLLABORATION.md) et [le contrat des données](../../../architecture/COLLABORATION_DATA.md).

Avant de continuer : vérifier la PR B11, son dernier SHA, sa CI et son éventuelle revue/fusion ; ne pas conclure à partir d'une ancienne capture. Aucun avis humain n'est attesté par ce suivi. Ne pas modifier la PR B11 pour y ajouter B14 pendant sa revue. Réutiliser la branche permanente après fusion et synchronisation ; conserver les branches des collègues.

Prochain lot : B14, création explicite de brouillon/publication avec FormRequest, DTO, Policy, Service transactionnel, idempotence/audit et Resource. Compléter les champs de code inerte dans une migration additive. Coordonner HelpIntent avec BV201 avant BC07 : ne pas créer deux enums. Les contrôles de longueur conditionnels appartiennent au contrat de validation, notamment `attempts="aucune"` et `ask_question` sans code.

B11 ne réalise pas l'API des demandes ni AC10 complet : l'acceptation concurrente par l'auteur devra être testée par B19. B14–B21, BC01–08 et BH01–10 ne sont pas terminés. BACKEND_GATE reste non reçu ; aucun frontend à commencer.

Les tests utilisent une base locale temporaire dédiée avec rôle `haas_test`, sans reprendre les paramètres privés du poste d'un collègue. Le commit réel et les résultats de CI sont à consulter dans le bilan et la PR.
