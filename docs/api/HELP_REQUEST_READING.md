# B15 — Lire et rechercher les demandes

GET `/api/v1/requests` et GET `/api/v1/requests/{id}`. Réponses JSON sous `data`, listes avec `meta` commun (current_page, per_page, last_page, total, from, to). `Cache-Control: no-store` sur listes et détails. Aucune mutation ni exécution de contenu/URL.

## Paramètres de liste

- `scope=public` par défaut : demandes non brouillon, auteur actif et vérifié, `hidden_at=null`. Même un propriétaire ne retrouve pas ses brouillons dans ce catalogue.
- `scope=mine` : session d'un membre actif et vérifié, seulement ses demandes visibles, y compris brouillons. Visiteur 401, compte non vérifié 403 ; suspension/session révoquée selon middleware commun. Aucun bypass admin/modérateur.
- `q` facultatif, vide = aucun filtre, 200 caractères maximum. Sous-chaîne littérale, casse ignorée, accents conservés ; titre, goal, expected, observed, attempts, environment et nom/slug des technologies. `%`, `_` et `!` sont littéraux. Aucun SQL/regex fourni par le client. Code, URL, pseudonyme/courriel, commentaires, propositions, historique et audit ne sont pas recherchés.
- `technology` : un UUID de technologie ; aucune vérification d'existence séparée, résultat vide si inconnu. `state` : draft/open/in_progress/resolved/archived. `state=draft` avec scope public reste vide. Archives consultables et présentes, filtrables explicitement.
- `sort=newest` (created_at décroissant), `oldest` (croissant), `updated` (updated_at décroissant). Départage systématique par id croissant, sans score de popularité ou pertinence fictif.
- `page` : 1 à 2147483647 ; `per_page` : 1 à 50, défaut 20, conventions communes. Aucun alias page_size ; paramètre inconnu ou invalide = 422 avec error.fields. Les filtres se cumulent (ET), les champs de recherche sont regroupés (OU) après visibilité.

## Visibilité et détail

Le détail expose le contrat HelpRequest de B14, auteur limité à id/handle et technologies préchargées. Le brouillon n'est lisible que par son auteur actif/vérifié. UUID invalide/inexistant, brouillon d'autrui, demande masquée ou auteur inactif/non vérifié : même 404 RESOURCE_NOT_FOUND, sans indication d'existence. Les non-vérifiés et visiteurs lisent les publications publiques. Une suspension ou un masquage prend effet à la requête suivante, sans réponse mise en cache par l'application.

La visibilité SQL précède filtres, pagination et total. La Query de détail réutilise cette visibilité et recontrôle la Policy. Pas de chargement de commentaires/propositions, de leurs compteurs, ni d'accès de traitement modérateur dans cette API. Les futures demandes liées aux projets nécessiteront le contrôle du parent en BC04/BC05 ; leur colonne et leur API n'existent pas encore. B15 ne reçoit ni AC15 capsules/kit ni AC25 global.

## Décisions de périmètre

Recherche P0 par ILIKE paramétré : pas de nouvelle dépendance ou index spéculatif. Tris par date conformes au cahier. Vue publique indépendante du compte ; vue mine explicite, sans filtre author_id fourni par le client. Un filtre inconnu est rejeté, jamais ignoré. Les tests PostgreSQL mesurent la constance des requêtes sur le jeu de référence ; aucune performance de production n'est déduite de ce test local.
