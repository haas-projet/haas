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
| B07 | Sécuriser sessions et CORS | En cours — prêt pour revue | PR #10, CI verte ; fusion attendue. |
| B08 | Vérifier le courriel et réinitialiser le mot de passe | En cours — prêt pour revue | PR #11, CI verte ; SMTP réel réservé à la livraison. |
| B09 | Gérer le compte courant et ses permissions | En cours — prêt pour revue | PR #13, CI verte. |
| B10 | Créer les profils et contributions | En cours — partiel | PR #14 : profils/édition/technologies prêts ; contributions métier restantes. |
| B12 | Tracer audit et révisions | En cours — prêt pour revue | PR #15, CI verte ; consommateurs métier à raccorder dans leurs lots. |
| B13 | Empêcher les doublons de commandes | En cours — prêt pour revue | PR #16, CI verte ; socle d’idempotence et premier usage profil. |
| B29 | Livrer les notifications internes | En cours — partiel | PR #18 : boîte privée/outbox/lu/non lu prêts ; abonnements métier restants. |
| B30 | Recueillir les signalements | En cours — partiel | PR #19 : profils, quotas, confidentialité et file admin prêts ; autres cibles restantes. |
| B31 | Modérer et retirer les contenus | En cours — partiel | PR #19 : décisions et masquage de profil prêts ; versions/kits/recherche restants. |
| B32 | Administrer rôles et suspensions | En cours — prêt pour revue | PR #17 : révocation globale, audit, concurrence et dernier admin testés. |
| B39 | Vérifier la sécurité de toutes les API | En cours — partiel | Socle testé ; attendre projets/offres/demandes/capsules/lab et BH10. |
| B40 | Compléter le contrat API et générer les types | En cours — partiel | Inventaire du socle et 28 types générés/compilés ; endpoints métier restants. |
| B41 | Exécuter la recette backend complète | En cours — partiel | Parcours modération/notification vérifié ; demande → résolution → capsule → test/réutilisation absent. |
| B42 | Préparer exploitation et restauration | En cours — partiel | Santé console/runbook/restauration chiffrée locale testés ; serveur distant et rollback restants. |
| B43 | Exécuter Qodana backend | À faire — bloqué | Projet/token CI et paramètres réels manquants ; analyse non exécutée. |
| B44 | Recevoir le backend P0 | À faire — bloqué | Domaines incomplets, revue humaine/gate/GO_FRONTEND manquants. |

Sous-tâches développées et testées pendant cette session : administration B32 ; infrastructure/boîte privée de notifications ; signalement/modération du profil ; inventaire OpenAPI/types générés ; contrôle de santé console ; exercice local de sauvegarde/restauration. Elles peuvent être cochées comme réalisées dans leurs cartes, en conservant la revue et les raccordements restants visibles. **Aucune nouvelle carte de lot entier ne passe à Terminé avant sa réception/fusion vérifiée.**

Preuves : [réception partielle](../../../quality/SOCLE_RECEPTION_PARTIELLE.md), [progression](PROGRESS.md), [blocages](../../../BLOCKERS.md). B01–B06 étaient déjà intégrés. Prochaine étape : revue des PR dans l'ordre des dépendances et livraison des domaines par les deux autres responsables ; pas de frontend avant BACKEND_GATE + GO_FRONTEND humain.

Dernière livraison : [PR #20](https://github.com/haas-projet/haas/pull/20), code 68b00f8, CI 37141557467 verte (384 tests / 3702 assertions par PHP 8.4/8.5). Les cartes B39–B44 conservent leurs réserves.
