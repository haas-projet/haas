# Cartes Systalink — partie socle de ousseynoufayeisidk-sys

État au 3 octobre 2026. 22 lots attribués, aucun lot des collègues transféré. Étiquette : Backend / Socle. Assigné : Ousseynou FAYE. Priorité normale sauf blocage explicitement priorisé par l'équipe ; échéances à fixer ensemble, aucune date inventée.

Utiliser le titre et le texte ci-dessous comme titre/description de la carte. Si le tableau propose seulement À faire/En cours/Terminé, conserver la précision « prêt pour revue », « partiel » ou « bloqué » dans la description. Une CI verte ne prouve pas une fusion.

| Lot | Titre de la carte | Colonne / précision | Description et reste à faire |
|---|---|---|---|
| B01 | Initialiser Laravel et PostgreSQL | Terminé | Socle intégré dans main. |
| B02 | Configurer formatage et analyse PHP | Terminé | Pint, PHPStan niveau 8, architecture ; intégré. |
| B03 | Configurer la CI backend | Terminé | PHP 8.4/8.5 et PostgreSQL ; intégré. |
| B04 | Normaliser le contrat HTTP | Terminé | Erreurs, corrélation, pagination et OpenAPI ; intégré. |
| B05 | Créer identité et référentiels | Terminé | Utilisateurs UUID, rôles et technologies ; intégré. |
| B06 | Créer l’inscription | Terminé | Validation, conditions versionnées, champs serveur refusés ; intégré. Conditions réelles à configurer avant ouverture. |
| B07 | Sécuriser sessions et CORS | Terminé | PR #10 fusionnée : sessions Sanctum, CSRF et CORS intégrés et testés. |
| B08 | Vérifier le courriel et réinitialiser le mot de passe | Terminé | PR #11 fusionnée : vérification du courriel et reset intégrés ; SMTP réel à recevoir avant ouverture. |
| B09 | Gérer le compte courant et ses permissions | Terminé | PR #13 fusionnée : compte courant et capacités intégrés et testés. |
| B10 | Créer les profils et contributions | En cours — partiel | PR #14 : profils/édition/technologies prêts ; contributions métier restantes. |
| B12 | Tracer audit et révisions | Terminé | PR #15 fusionnée : audit et révisions intégrés ; consommateurs métier à raccorder dans leurs lots. |
| B13 | Empêcher les doublons de commandes | Terminé | PR #16 fusionnée : socle d’idempotence et premier usage profil intégrés et testés. |
| B29 | Livrer les notifications internes | En cours — partiel | PR #18 : boîte privée/outbox/lu/non lu prêts ; abonnements métier restants. |
| B30 | Recueillir les signalements | En cours — partiel | PR #19 : profils, quotas, confidentialité et file admin prêts ; autres cibles restantes. |
| B31 | Modérer et retirer les contenus | En cours — partiel | PR #19 : décisions et masquage de profil prêts ; versions/kits/recherche restants. |
| B32 | Administrer rôles et suspensions | Terminé | PR #17 fusionnée : rôles/suspensions, révocation, audit et dernier admin intégrés et testés. |
| B39 | Vérifier la sécurité de toutes les API | En cours — partiel | Socle testé ; attendre projets/offres/demandes/capsules/lab et BH10. |
| B40 | Compléter le contrat API et générer les types | En cours — partiel | Inventaire du socle et 28 types générés/compilés ; endpoints métier restants. |
| B41 | Exécuter la recette backend complète | En cours — partiel | Parcours modération/notification vérifié ; demande → résolution → capsule → test/réutilisation absent. |
| B42 | Préparer exploitation et restauration | En cours — partiel | Santé console/runbook/restauration chiffrée locale testés ; serveur distant et rollback restants. |
| B43 | Exécuter Qodana backend | À faire — bloqué | Projet/token CI et paramètres réels manquants ; analyse non exécutée. |
| B44 | Recevoir le backend P0 | À faire — bloqué | Domaines incomplets, revue humaine/gate/GO_FRONTEND manquants. |

**Après intégration : 12 lots terminés, 8 partiels, 2 bloqués.** Déplacer maintenant B07, B08, B09, B12, B13 et B32 vers Terminé ; B01–B06 étaient déjà intégrés. Les livraisons partielles B10/B29/B30/B31/B39–B42 sont dans main, mais leurs raccordements et contrôles restants empêchent de clôturer ces cartes. B43/B44 restent bloqués.

Preuve : [fusions du socle](../../../quality/MERGE_SOCLE.md), main `a051e81`, [CI 37146178657](https://github.com/haas-projet/haas/actions/runs/37146178657) verte : 384 tests / 3702 assertions par PHP 8.4/8.5. Neuf branches temporaires supprimées, les trois branches de l'équipe et main conservées. Reprendre sur backend/socle-auth.
