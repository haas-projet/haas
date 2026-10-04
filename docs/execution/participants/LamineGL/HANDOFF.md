# Reprise — communauté et entraide

Lot courant : **B14**, branche `backend/communaute-entraide-b14`, issue #2. API de création prête pour revue après validations ; consulter [le contrat](../../../api/HELP_REQUEST_CREATION.md), [les preuves](../../../quality/B14_CREATION.md) et [SYSTALINK_TASKS.md](SYSTALINK_TASKS.md). Le dernier commit/CI est indiqué dans la PR et le bilan.

Travail de Madina préservé : PR #23 (B11 original, `1c380c4`) → PR #22 (compléments B11, `5854e04`, base reciblée sur sa branche) → PR B14 (base temporaire `backend/communaute-entraide`). Aucune de ces PR n'est présumée fusionnée. Après chaque fusion, synchroniser et recibler la suivante vers main, puis vérifier le dernier SHA. Ne pas clôturer #23 comme doublon ni écraser sa branche.

Le 4 octobre, l'utilisateur confirme que Madina n'a pas commencé B14 ni HelpIntent/BV201. B14 définit `App\Enums\HelpRequests\HelpIntent` une seule fois ; BV201/BC07 le réutilisent, sans deuxième enum ni migration doublonnée. Les cinq migrations B11 sont inchangées ; l'extension B14 est additive. Les contenus de Madina sous son dossier participant sont inchangés.

Prochain lot : **B15**, lectures/recherche avec visibilité avant filtres/pagination et compteurs ; réutiliser HelpRequestPolicy et HelpRequestResource. Ne pas ajouter B15 à la PR B14 pendant sa revue. B16 ajoutera les éditions/publications de brouillons existants sous version ; POST B14 crée une nouvelle ressource et n'est pas une route d'édition.

B11/B14 restent En cours dans Systalink jusqu'à intégration vérifiée. Les 25 autres lots de la coordination #2 restent à faire ; les points `ask_question`/HelpIntent préparés ne valident pas l'intégralité BC07/BV201. Aucun BACKEND_GATE/GO_FRONTEND/GO_PRODUCTION. Tests PostgreSQL uniquement sur une base dédiée avec rôle haas_test.
