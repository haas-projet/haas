# B29 — Notifications, première livraison

3 octobre 2026, branche backend/socle-auth-notifications depuis B32 fc2785f. Contrat INTERNAL_NOTIFICATIONS.md et OpenAPI 0.11.0. Boîte privée paginée, compteur, marquage lu/non lu, outbox transactionnelle et commande de livraison après commit. Aucune donnée libre ou lien public retiré. Premier type fermé profile.moderated ; producteur B31 à raccorder.

PHP 8.5.10/PostgreSQL 17.0 dédié 127.0.0.1:54693/haas_socle_test/haas_test. Pint et PHPStan niveau 8 réussis. PHPUnit Unit,Feature,Architecture : 249 tests / 2214 assertions. Nouveaux tests SQL ciblés : 4 tests / 47 assertions. Deux processus simultanément bloqués sur l'unicité avant commit confirment une seule intention et une seule notification. Un observateur SQL indépendant ne voit aucune intention avant commit ; rollback vide, panne réelle de livraison conservant le métier et permettant la reprise, tierce boîte inaccessible, CSRF actif, lu/non lu strict.

La suite SQL complète sera exécutée par la CI après publication ; ne pas additionner ces seuls tests ciblés comme une nouvelle recette intégrale. Typage du modèle précisé car les tables créées dans une boucle ne sont pas inférées par Larastan. Aucun ignore/baseline ajouté. Les producteurs demandes/capsules/lab/offres restent absents et non simulés : B29 IN_PROGRESS et PR de première livraison en brouillon, AC08/AC84 non reçus globalement.

CI réelle : run 37135770117 sur 29803cb, PR #18 ; journaux lus, 371 tests / 3356 assertions par PHP 8.4/8.5 avec PostgreSQL 17. Premier producteur profile.moderated ajouté dans B30/B31 ; les autres domaines restent à raccorder.
