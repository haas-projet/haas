# B19 — Accepter une proposition et lire la résolution active

## Commande de résolution

`POST /api/v1/requests/{id}/resolve` retourne `200 {data}`. Session SPA, compte actif vérifié, CSRF et `Idempotency-Key` UUID v4 requis ; quota existant 30 requêtes/minute. Seul l'auteur de la demande décide, même face à un administrateur ou modérateur tiers. Aucun auteur, acceptant, état, horodatage, résultat de laboratoire ou attribut de résolution n'est choisi par le client.

Objet JSON non vide, sans champs supplémentaires ni paramètres de requête :

| Champ | Contrat |
|---|---|
| `proposal_id` | UUID d'une proposition visible appartenant à la demande ciblée. |
| `lock_version` | Version courante de la demande, entier JSON strict de 1 à 2147483646. |
| `proposal_lock_version` | Version du contenu de la proposition, entier JSON strict de 1 à 2147483647. |
| `environment` | Contexte déclaré au moment de l'acceptation, 1–255 caractères Unicode utiles après trim. |
| `validation_note` | Ce qui a fonctionné dans ce contexte, 20–4 000 caractères utiles après trim Unicode ; liens seuls refusés. |

La Request restaure les cinq champs depuis le JSON brut. UTF-8, caractères de contrôle et motifs de secrets connus sont vérifiés avant normalisation, même aux bords ; chaînes numériques, nombres décimaux et booléens ne deviennent pas des versions entières. Les limites de texte portent sur le contenu normalisé ; les schémas ne fabriquent pas une limite brute qui rejetterait des espaces périphériques acceptés. La détection de secrets reste indicative.

Nouvelle acceptation : demande publique visible en `open/in_progress`, proposition visible en `proposed`, contributeur actif vérifié, deux versions attendues exactes et aucune résolution active. Une proposition étrangère à la demande ou inaccessible ne devient pas acceptable par son seul UUID. Les verrous sont acquis dans l'ordre déterministe comptes, idempotence, demande, proposition ; droits, visibilité et états sont relus dans la transaction.

La proposition devient `accepted` sans modification du contenu ni incrément de sa version. La demande devient `resolved`, sa version augmente une fois. La résolution fige la version sélectionnée, le contexte et la note, avec acceptant et date serveur. Les autres propositions gardent leur état et leur version : l'acceptation n'invente pas de décisions « Non retenue ». Une seule résolution active est également garantie par l'index PostgreSQL, avec appartenance par FK composite.

Résolution, attribution, audit minimal, intention idempotente et outbox appartiennent à la même transaction. Une panne annule la commande entière. Aucun code, shell ou URL arbitraire n'est exécuté. L'acceptation signifie « cette proposition a aidé l'auteur dans son contexte », sans preuve de sécurité universelle ni résultat de laboratoire.

## Relances et refus

Idempotence 24 h par acteur, route/cible, clé et charge normalisée. La même intention rend la même résolution et version, y compris après la transition du parent vers `resolved`, sans audit, transition ou notification supplémentaire. Droits et visibilité actuels sont contrôlés avant le rejeu ; une révocation, un masquage ou une modification incompatible ne rend pas une ancienne réponse autorisée.

| Réponse | Sens |
|---|---|
| `401` | Connexion requise. |
| `403` | Compte/action non autorisé ; un tiers administrateur ne remplace pas l'auteur. |
| `404` | Cible inaccessible, proposition étrangère ou aucune résolution active à lire. |
| `409 RESOURCE_CONFLICT` | Version périmée, état incompatible, résolution déjà active avec nouvelle intention, ou résultat mémorisé devenu différent. |
| `409 IDEMPOTENCY_CONFLICT` | Même clé avec une autre charge. |
| `419` | CSRF/session. |
| `422` | JSON, champs, texte, versions ou clé d'idempotence invalides. |
| `429` | Quota ; `Retry-After` conservé lorsqu'il est disponible. |
| `500/503` | Panne ; aucune mutation partielle. |

Toutes les réponses suivent l'enveloppe HAAS, avec `X-Request-ID` et `Cache-Control: no-store` ; le transport peut ajouter `private`. Aucune note, contexte, code ou trace interne n'est recopié dans une erreur ou l'audit commun. Une livraison de notification en panne après commit ne supprime pas une résolution enregistrée.

## Lecture active et auto-résolution

`GET /api/v1/requests/{id}/resolution` est public lorsque la demande, la proposition choisie et leurs auteurs sont actuellement visibles. Brouillon, masquage, suspension ou non-vérification ferment l'accès. La demande doit être `resolved`, la proposition `accepted`, l'acceptant doit rester l'auteur du parent et une version de proposition connue doit encore correspondre au snapshot ; toute incohérence ferme la lecture. Aucune résolution active ou résolution révoquée : `404`. Ce lot ajoute cette lecture active, sans liste de résolutions ni parcours de réouverture.

Projection `{data}` : `id`, `request_id`, `proposal_id`, `proposal_version`, `environment`, `validation_note`, `validation_note_html`, `accepted_by` limité à `id/handle`, `self_resolution`, `accepted_at`, `revoked_at` et `request_lock_version` courant. La note est du texte source ; son HTML utilise le Markdown restreint partagé avec B17, HTML brut échappé, liens HTTPS contrôlés et images sans chargement réseau. Le contexte déclaré reste du texte inerte. Aucun courriel ni nom privé n'est projeté.

`self_resolution=true` signifie que l'acceptant est aussi l'auteur de la proposition. Afficher **« Auto-résolution »**, sans validation indépendante ; cette acceptation n'entraîne pas de notification à soi-même. Une acceptation par le demandeur d'une proposition tierce reste distincte d'une revue de capsule et d'un test machine.

Les résolutions B11 conservent leurs données. `environment` et `proposal_version` sont `null` lorsque ces informations n'ont pas été capturées : aucun ancien contexte ni numéro de version actuel n'est substitué au passé. Une ancienne note peut ne pas respecter les longueurs modernes ; la lecture ne la réécrit pas.

## Notification et périmètre

L'outbox `resolution.accepted` vise l'auteur de la proposition sélectionnée, avec UUID de résolution comme événement stable ; aucun auto-envoi. `notifications:deliver` livre depuis un processus séparé après commit, avec reprise et déduplication. Message générique et chemin interne `/requests/<UUID>` seulement, sans contexte, note, extrait, code ou contact.

Liste, compteur et marquage vérifient la résolution active, la visibilité courante du parent/proposition et le destinataire concerné. Révocation ou retrait de visibilité ferme cette projection. Les contraintes de types de notification composent les types installés des autres lots ; elles ne les remplacent pas par une liste historique réduite.

Le périmètre serveur B19 vise AC09/AC10 ; leur réception dépend des tests réellement exécutés et des preuves associées. B20 réouverture, B21 non-sélection/archivage, rattachements projet, compteurs de profil, réception navigateur, BACKEND_GATE et déploiement restent à recevoir dans leurs lots respectifs. L'acceptation ne déclenche aucun laboratoire ni publication de capsule.
