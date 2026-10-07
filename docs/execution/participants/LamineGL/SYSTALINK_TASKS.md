# Tableau Systalink — partie de Lamine

Assigné : LamineGL. Étiquette : Backend. Priorité normale ; pas d'échéance inventée.

B11 est intégré dans main depuis la fusion de #23 le 7 octobre 2026 ; sa carte peut passer à **Terminé**. Les statuts de ce fichier sont à reporter dans Systalink : aucune carte distante n'a été modifiée par cette livraison. Ne pas cocher toute la tâche de coordination #2 : elle contient 27 lots. Les autres lots ne sont pas déclarés terminés.

Titre B11 à copier : **Créer le schéma des demandes, commentaires, propositions et résolutions**.

Description B11 : Créer les tables et relations PostgreSQL, protéger les références entre demandes et propositions, limiter chaque demande à une résolution active, versionner les contenus éditables et tester les migrations. Conserver l'historique et protéger les champs serveur. Preuves : `docs/quality/B11_COLLABORATION.md`.

| Tâche | Colonne actuelle |
|---|---|
| B11 — Schéma de collaboration | Terminé — fusion #23 vérifiée dans main |
| B14 — Créer une demande | En cours — API réalisée, revue et intégration en attente |
| B15 — Lire et rechercher les demandes | En cours — API réalisée, revue et intégration en attente |
| B16 — Modifier une demande | En cours — édition/publication/historique, revue et intégration en attente |
| B17 — Commentaires | En cours — commentaires/révisions/notifications testés, revue et intégration en attente |
| B18 — Propositions de solution | À faire |
| B19 — Accepter une proposition | À faire |
| B20 — Rouvrir une demande | À faire |
| B21 — Archiver et non-retenir | À faire |
| BC01 — Schéma des projets et découverte volontaire | À faire |
| BC02 — Brouillons et édition de projet | À faire |
| BC03 — Publication et catalogue des projets | À faire |
| BC04 — Demandes liées à un projet | À faire |
| BC05 — Modération des projets et visibilité liée | À faire |
| BC06 — API de découverte des développeurs | À faire |
| BC07 — Questions sans formulaire de panne | À faire |
| BC08 — Recette API de la communauté | À faire |
| BH01 — Schéma et ouverture aux coups de main | À faire |
| BH02 — Occasions de contribuer et préférences explicables | À faire |
| BH03 — Proposer un apport avec consentement et limites | À faire |
| BH04 — Consulter ses offres sans fuite | À faire |
| BH05 — Accepter et créer un seul échange public | À faire |
| BH06 — Rattacher une offre à un échange choisi | À faire |
| BH07 — Refuser retirer expirer et fermer proprement | À faire |
| BH08 — Projections publiques progrès et notifications | À faire |
| BH09 — Contrats et documentation des offres | À faire |
| BH10 — Recette transactionnelle et privée des coups de main | À faire |

Mise à jour du 4 octobre : **B14 — Créer et publier une demande** est également En cours. Description à copier : enregistrer un brouillon privé ou publier une demande/une question ; valider les champs, refuser les champs serveur et secrets suspects, empêcher les doubles créations et tracer l'action. Les tests backend passent selon la preuve B14 ; la carte attend sa revue et sa fusion. B15 est désormais réalisé et testé, en attente de revue et de fusion (voir mise à jour ci-dessous).


Mise à jour B15 du 4 octobre : **Lire et rechercher les demandes** — En cours, prêt pour revue après CI vérifiée dans la PR. Description à copier : consulter une demande publique ou son propre brouillon, rechercher par texte/technologie/état, trier et paginer sans exposer les contenus privés/masqués ni leurs compteurs. Preuves : `docs/quality/B15_READING.md`. B11/B14/B15 restent En cours jusqu'à leur intégration vérifiée ; aucune nouvelle carte Terminé à cette étape. Prochain lot B16, édition versionnée.


Mise à jour B16 du 4 octobre : **Modifier une demande avec contrôle de version et historique** — En cours. Description à copier : permettre à l'auteur de clarifier sa demande sans écraser une édition concurrente ; conserver une note après contribution, publier explicitement un brouillon complet et consulter les révisions autorisées. Preuves : `docs/quality/B16_EDITING.md`. Tests et CI à lire dans la PR avant revue/fusion ; B11/B14/B15/B16 ne sont pas encore des cartes Terminé. Prochain lot B17, commentaires.

Mise à jour B17 du 7 octobre : **Enregistrer des commentaires historisés** — En cours, prêt pour revue. Description à copier : créer et modifier ses commentaires sous version, afficher un Markdown restreint, conserver les révisions et respecter la visibilité du parent ; notifier son auteur une seule fois après commit, sans extrait privé. Preuves : docs/quality/B17_COMMENTS.md, 671 tests / 6805 assertions distincts avec main. B11 est réellement intégré ; B14/B15/B16/B17 attendent leur revue/fusion et 22 lots restent À faire. Aucun statut de carte distante n'a été modifié par ces notes.
