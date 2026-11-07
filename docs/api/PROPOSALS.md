# B18 — Propositions structurées et historisées

## Écritures

`POST /api/v1/requests/{id}/proposals` exige un membre actif vérifié, une session SPA, le CSRF, une clé d'idempotence UUID v4 et le quota existant de 30 requêtes/minute. Le parent doit être public, visible et en `open` ou `in_progress`. Objet JSON non vide uniquement, sans paramètres de requête : `diagnosis`, `fix`, `verification`, `limits` obligatoires, chacun 20–4 000 caractères après trim Unicode. Blancs et liens seuls, contrôles et motifs de secrets connus sont refusés. Champs supplémentaires, auteur, parent, état, version et dates soumis par le client sont refusés.

`code` est facultatif, nullable, limité à 12 000 caractères Unicode et validé comme UTF-8. Sa valeur validée est conservée octet par octet : indentation, espaces, CRLF et HTML source restent du texte inerte. `""` et `null` sont distincts. La Request restaure cette valeur depuis le JSON pour que le middleware de conversion des chaînes vides ne l'altère pas. Aucun code, shell ou URL arbitraire n'est exécuté ni récupéré.

La proposition est créée avec `state=proposed` et `lock_version=1`. Si le parent était ouvert, le service le passe en cours avec une seule incrémentation de sa version et l'audit dédié `help_request.started`, distinct d'une édition du propriétaire. Les propositions suivantes sur une demande en cours et les relances idempotentes ne répètent pas cette transition. Les versions ne dépassent pas l'entier PostgreSQL.

`PATCH /api/v1/proposals/{id}` appartient seulement à l'auteur actif vérifié, sans bypass administrateur. Proposition visible en `proposed`, parent public visible en `open/in_progress` ; `accepted/not_selected`, brouillons, demandes résolues et archives non modifiables. Objet JSON partiel avec au moins un champ de contenu et `lock_version` entier JSON strict 1–2147483646 : chaînes numériques, décimaux et booléens refusés. Les champs absents sont conservés ; `code=null` retire le code et une chaîne vide reste vide. Aucun corps vide ni version seule. Le contenu final est revalidé sous verrou, puis version +1 et date serveur `edited_at`.

## Lectures et réponses

`GET /api/v1/requests/{id}/proposals`, `GET /api/v1/proposals/{id}` et `GET /api/v1/proposals/{id}/revisions` suivent la visibilité actuelle du parent et du contributeur. Masquage, suspension ou non-vérification excluent les ressources avant pagination et total. Les propositions d'un brouillon sont exclues même pour son propriétaire. Archives et demandes résolues restent lisibles. Liste par `created_at/id` croissants ; historique par version décroissante. Pagination 20 par page, maximum 50 ; paramètres inconnus refusés.

Enveloppes : `{data}` pour le détail/création/édition et `{data,meta}` pour les pages. Auteur public `id/handle` seulement ; aucun courriel ni nom privé. Quatre sources `diagnosis/fix/verification/limits` et quatre champs `*_html`, code brut, état, version, dates. Les dates legacy absentes restent nulles. Toutes les réponses portent `X-Request-ID` et `Cache-Control: no-store` ; le transport peut ajouter `private`.

Les quatre rendus utilisent `CommentMarkdown` de B17 : HTML brut échappé, code inerte, liens HTTPS sans identifiants et `rel=nofollow/noopener/noreferrer`, images réduites au libellé sans requête réseau ; profondeur 20, 100 délimiteurs par ligne. Les sources ne sont pas du HTML de confiance ; le code n'a pas de projection HTML exécutable. La réception navigateur attend GO_FRONTEND.

## Historique et limite legacy

Révision métier identifiée par `id/proposal_version/action/occurred_at`, sources et rendus inertes ; actions `created`, `updated`, `before_edit`. Au premier changement d'une proposition B11 sans historique, `before_edit` capture uniquement la version connue, avec la date de capture. Aucun ancien changement ou horodatage inventé. Les textes legacy peuvent être plus courts ou longs que les descriptions modernes et ne sont pas réécrits par la migration.

**Limite explicite :** si ce snapshot contient un motif de secret connu, le service refuse l'édition avec `409 PROPOSAL_HISTORY_REQUIRES_REVIEW`, sans capture ni mutation. Il ne prétend ni expurger le texte ni l'avoir fait revoir. La procédure de revue/expurgation n'est pas livrée par B18 ; la détection de motifs est indicative. L'historique suit la visibilité de la proposition et reste inaccessible après son masquage.

## Transaction, relances et notifications

Comptes acteur/auteur du parent verrouillés par UUID trié, puis socle d'idempotence, parent, proposition. Le socle autorise la cible courante avant de consulter sa ligne d'intention. Droits, visibilité, état et version sont relus sous verrou. La création ou l'édition rend la proposition, ses révisions, l'audit minimal, l'outbox et l'intention atomiques. Une panne ne laisse aucun résultat partiel.

Idempotence 24 h par acteur/route-cible/clé/charge normalisée. Même intention : même proposition/version sans écriture supplémentaire ; droits actuels avant rejeu. Autre charge : `409 IDEMPOTENCY_CONFLICT` ; version périmée ou résultat déjà modifié : `409 RESOURCE_CONFLICT`. Aucune source ni code dans l'intention mémorisée ou l'audit commun. La transition automatique reçoit un audit dédié et ne contourne pas le droit d'édition du propriétaire.

`proposal.created` utilise l'UUID de la proposition comme événement stable et l'auteur courant du parent comme destinataire, sans auto-envoi. Outbox minimale ; livraison séparée après commit par `notifications:deliver`, reprise et déduplication existantes. Liste, compteur et marquage relisent la visibilité actuelle et la propriété du parent. Message générique et chemin interne seulement, sans extrait ni contact. Une édition ou une relance ne renotifie pas.

## Erreurs et périmètre

`401` connexion requise ; `403` compte/action non autorisé ; `404` cible inaccessible ; `409` conflit ou historique à revoir ; `419` CSRF/session ; `422` validation ; `429` quota avec `Retry-After` si disponible ; `500/503` panne sans mutation partielle. Format HAAS commun, aucun corps/code/trace repris dans l'erreur. Reprendre une intention d'écriture avec sa même clé.

B18 ne livre pas l'acceptation, la résolution, la réouverture, la non-sélection ni les contrôles projet futurs BC04/BC05. Ces commandes restent B19–B21 ; aucune ancienne preuve ne vaut réception de ces parcours.
