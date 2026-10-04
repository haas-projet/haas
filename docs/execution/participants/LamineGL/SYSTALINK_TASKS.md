# Tableau Systalink — partie de Lamine

Assigné : LamineGL. Étiquette : Backend. Priorité normale ; pas d'échéance inventée.

B11 est réalisé et testé au niveau du schéma, mais reste **En cours** dans Systalink tant que sa revue et sa fusion ne sont pas terminées. Ne pas cocher toute la tâche de coordination #2 : elle contient 27 lots. Les autres lots ne sont pas déclarés terminés.

Titre B11 à copier : **Créer le schéma des demandes, commentaires, propositions et résolutions**.

Description B11 : Créer les tables et relations PostgreSQL, protéger les références entre demandes et propositions, limiter chaque demande à une résolution active, versionner les contenus éditables et tester les migrations. Conserver l'historique et protéger les champs serveur. Preuves : `docs/quality/B11_COLLABORATION.md`.

| Tâche | Colonne actuelle |
|---|---|
| B11 — Schéma de collaboration | En cours — prêt pour revue, non fusionné |
| B14 — Créer une demande | En cours — API réalisée, revue et intégration en attente |
| B15 — Lire et rechercher les demandes | En cours — API réalisée, revue et intégration en attente |
| B16 — Modifier une demande | À faire |
| B17 — Commentaires | À faire |
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
