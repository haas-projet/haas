# B20 — Réouverture attribuée et historique des acceptations

## Rouvrir sa demande

`POST /api/v1/requests/{id}/reopen` retourne `200 {data}`. Seul l'auteur actuel actif et vérifié décide ; un administrateur tiers ne le remplace pas. Session SPA, CSRF, `Idempotency-Key` UUID v4 et quota existant de 30 commandes/minute requis. JSON objet non vide, sans champs supplémentaires ni paramètres de requête.

| Champ | Contrat |
|---|---|
| `lock_version` | Version actuelle du parent, entier JSON strict de 1 à 2147483646. |
| `reason` | Motif utile de 20–4000 caractères Unicode après trim partagé ; liens seuls refusés. |

La Request restaure le JSON brut avant validation : chaînes numériques, décimaux et booléens ne deviennent pas des versions entières. La forme objet racine reste distincte d'une liste ; les objets imbriqués sont restaurés en tableaux pour être refusés avec `422`, sans erreur de transport `InputBag`. Contrôles bruts et motifs de secrets probables sont refusés avant normalisation, même aux bords. La détection de secrets reste indicative. Les espaces périphériques ne créent pas une limite brute différente du contenu normalisé. Aucun code utilisateur, shell ou URL n'est exécuté.

La demande `resolved` devient `open`, avec version +1. Sa résolution active reçoit une date serveur de révocation. Le journal externe `request_reopenings` garde son UUID, la demande, la résolution, l'auteur, le motif, la date et les versions avant/après. L'acceptation originale conserve note, contexte, version choisie, acceptant et date. La proposition choisie reste `accepted`, avec contenu et version inchangés ; les autres propositions restent inchangées. La non-sélection motivée appartient à B21. B19 peut ensuite accepter une autre proposition encore `proposed`.

Masquage, suspension ou non-vérification de l'ancien contributeur ne bloquent pas le propriétaire. La commande privée lit le rattachement nécessaire sous verrou ; ses détails historiques restent soumis aux droits de lecture.

Les comptes concernés sont verrouillés par UUID trié, puis l'intention, le parent, la proposition et la résolution. Droits, visibilité du parent, appartenance et versions sont relus après attente. Journal, révocation, transition, audits `help_request.reopened` et `resolution.revoked`, intention et outbox appartiennent à la même transaction PostgreSQL. Les audits ne contiennent ni motif, note, contexte ni code. Une panne annule toute la commande.

### Relances et refus

Idempotence 24 h par acteur, route/cible, clé et charge normalisée. La même intention rend le même journal sans doublon si le parent reste `open` à la version produite. Les droits actuels sont recontrôlés. Une autre mutation, notamment une nouvelle acceptation, ferme ce rejeu avec `409`. Nouvelle clé sur demande déjà ouverte ou état incompatible : `409`. Même clé avec une autre charge : `409 IDEMPOTENCY_CONFLICT`.

| Réponse | Sens |
|---|---|
| `401` | Connexion requise. |
| `403` | Compte/action non autorisé, tiers administrateur, ou demande brouillon/archivée visible à son auteur. |
| `404` | Demande actuellement inaccessible, notamment masquée. |
| `409 RESOURCE_CONFLICT` | Version, état, appartenance ou résultat de rejeu devenu incompatible. |
| `409 IDEMPOTENCY_CONFLICT` | Même clé avec une autre charge. |
| `419` | CSRF/session. |
| `422` | JSON, texte, version, clé, champs ou paramètres invalides. |
| `429` | Quota ; `Retry-After` conservé lorsqu'il existe. |
| `500/503` | Panne ; aucune mutation partielle. |

Projection : `id`, `request_id`, `resolution_id`, `reopened_by{id,handle}`, `reason`, `reason_html`, `reopened_at`, `request_version_before`, `request_version_after`. Les deux versions sont **historiques**, distinctes de la version actuelle dans l'historique. Markdown restreint B17, HTML brut échappé et images sans chargement réseau ; aucun courriel ou nom privé. Enveloppe HAAS, `X-Request-ID`, `Cache-Control: no-store` ; le transport peut ajouter `private`.

## Lire l'historique réel

`GET /api/v1/requests/{id}/resolutions` retourne `{data,meta}`, par date d'acceptation puis UUID décroissant. Paramètres `page` et `per_page` seulement ; 20 éléments par page, maximum 50. Parent actuellement visible. Chaque résolution est filtrée par visibilité courante de sa proposition et de son contributeur, et activité/vérification de son acceptant, **avant total et pagination**.

Chaque élément reprend la projection B19 avec `reopening` : journal B20 ou `null`. Les acceptations révoquées restent présentes si leur projection est autorisée. `request_lock_version` est la version **actuelle** ; `reopening.request_version_before/after` restent historiques. Révocation legacy sans journal : `reopening=null`, sans motif/date/version inventés. Contexte et version legacy inconnus restent `null` ; une note ancienne n'est pas réécrite pour satisfaire les bornes modernes. La route active distincte `GET .../resolution` rend `404` après révocation.

La cohérence d'attribution est également filtrée avant total : proposition `accepted` liée à ce même parent, acceptant égal à l'auteur courant du parent et snapshot connu égal à la version de cette proposition. `proposal_version=null` reste un snapshot inconnu autorisé, sans backfill. Si un journal existe, parent, resolution, acteur et date de révocation doivent correspondre ; les faits incohérents sont exclus de la projection sans effacer leurs lignes. Le parent peut avoir des versions ultérieures : la version historique du journal n'est pas remplacée par celle-ci.

## Notification et protections PostgreSQL

`request.reopened`, avec UUID du journal, vise l'auteur de l'ancienne proposition sauf soi. Le worker séparé `notifications:deliver` livre après commit, avec déduplication et reprise. Message générique et chemin interne `/requests/<UUID>` seulement ; aucun motif, note, contexte, code ou contact. Ce fait historique peut rester visible après une autre acceptation, sous droits actuels ; il ne prétend pas que la demande est encore ouverte. Liste, compteur et marquage filtrent parent, proposition, auteurs, auteur du journal et destinataire concerné. Une intention forgée pour un autre destinataire ne devient pas visible.

Migration additive : FK composite `(request_id,resolution_id)`, FK `RESTRICT`, une réouverture par résolution, versions +1 sans overflow et taille du motif. Journal immuable par trigger ; après sa création, les champs historiques de la résolution liée, y compris révocation, sont protégés. Le downgrade verrouille avant contrôle et refuse tout journal ou événement B20 existant ; aucune purge pour réussir. Le CHECK de notification compose les types installés et ne retire que son type au retour.

Les tests qui rendent leur base dédiée vide nettoient seulement les UUID de leurs fixtures sous `TestDatabaseGuard`, puis restaurent le trigger. Ce helper n'est ni appelé par la migration ni un chemin de production.

## Frontière capsules et réception

Cette branche part de B19 et livre le fait de réouverture, son historique et sa notification, sans fichier capsule ni modification de version publiée. Le signal **« Source rouverte — revue nécessaire »** sera raccordé après intégration réelle de B22–B27 ; versions publiées et digests restent immuables. **AC11 reste partiel tant que ce raccord n'est pas reçu.** Aucun frontend, BACKEND_GATE, déploiement ou revue humaine déclaré ici.

Les contrôles locaux réellement exécutés sont transmis à l'intégrateur, distincts de la future CI. Aucun succès de CI ni SHA auto-référencé n'est inventé dans ce contrat.

## 12 octobre 2026 — Réception complète B20

Code `11bf46a53d7e3faaac1615edc957c0daaaaafbd4`, [CI 38252341878](https://github.com/haas-projet/haas/actions/runs/38252341878) reçue dans chacun des deux journaux PHP 8.4/8.5 : **542/5375 hors SQL + 719/8121 Integration = 1261 tests / 13496 assertions par PHP**, 71 types et 738 empreintes, trois jobs réussis. PostgreSQL local complet reçu au même code, 719/8121, zéro erreur/échec/skip. Les anciens ciblés ne sont pas additionnés à la suite complète.

Les réceptions initiales ci-dessus sont historiques. B20 **IN_PROGRESS** : commandes, historique et notifications reçus sur leurs suites complètes ; PR #41 Draft sur B19 #38, après B18 #37. AC11 reste partiel avant réception du signal externe sur la composition réelle des capsules. La CI verte seule ne clôture pas ce raccord ; revue humaine et intégration requises.
