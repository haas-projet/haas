# Projets — Commandes BC02

## Routes reçues

| Route /api/v1 | Effet |
| --- | --- |
| POST /projects | Créer son brouillon privé, 201, Idempotency-Key UUIDv4 obligatoire. |
| PATCH /projects/{id} | Modifier sa fiche non masquée/non archivée, 200, version entière et Idempotency-Key obligatoires. |

Compte actif/vérifié, session et CSRF ; seul propriétaire, aucun remplacement par l'admin. Champs inconnus/query, identité/publication/modération client refusés. Une fiche publique inaccessible donne404 ; propriétaire sans droit d'édition403 ; version/intention incompatible409. Erreurs HAAS, Cache-Control no-store. Aucun état de publication ne se modifie par simple PATCH.

## Champs autorisés

name3–80, summary30–240, description80–4000 caractères utiles quand connus ; null conserve le caractère incomplet du brouillon. project_stage : idea/prototype/in_development/launched. help_sought facultatif1–1500 ; demo_url et repository_url facultatifsHTTPS, 2048 maximum, sans identifiants ni contrôles. technology_ids liste0–5 UUID du référentiel, doublons insensibles à la casse refusés. Champ omis conservé au patch, null efface un texte facultatif, [] efface le pivot du brouillon. La fiche publiée résultante doit conserver ses textes et1–5 technologies.

Validation du JSON original avant trim, forme liste distincte d'objet, pas d'identité client, entier JSON lock_version1..2147483646. Textes Unicode normalisés aux bords, formats invisibles seuls refusés. Les liens sont seulement analysés comme données ; aucun fetch ni clone. Les champs texte retournés sont à afficher comme texte, pas comme HTML.

## Atomicité et lecture

Acteur relu/verrouillé par le socle, puis projet et références verrouillés. Projet/pivot/audit/intention dans la même transaction ; audit minimal de noms/version/quantité, sans texte ou URL privé. Payload d'idempotence canonicalisé ; même intention recontrôle droits, visibilité, lien propriétaire et version, sans deuxième écriture. Même clé/autre contenu409 ; projet modifié depuis409 ; suspension/masquage/archive refusent les rejeux.

Resource fermée : UUID, propriétaire{id,handle}, textes et liens autorisés, deux états, lock_version, date de publication nullable, technologies{id,slug,name}, created_at/updated_at UTC. Aucun courriel, rôle, statut, motif de modération ou version d'audit privé. La réponse201 n'atteste aucune publication.

[Contrat OpenAPI](openapi/projects.yaml) et [preuves réelles](../quality/BC02_PROJECT_COMMANDS_20261012.md). Catalogue/publication/archivage BC03, rattachement BC04 et modération BC05 encore séparés ; aucun gate reçu.

## 12 octobre 2026 — Réception complète BC02

Code `7d0d569cb0f5f13ac1962949957b096cf1405df7`, [CI 38195772198](https://github.com/haas-projet/haas/actions/runs/38195772198) reçue dans chacun des deux journaux PHP 8.4/8.5 : **558/5276 hors SQL + 733/7848 Integration = 1291 tests / 13124 assertions par PHP**, 69 types et 747 empreintes, trois jobs réussis. PostgreSQL local complet reçu au même code, 733/7848, zéro erreur/échec/skip. Les anciens ciblés ne sont pas additionnés à la suite complète.

Les réceptions initiales ci-dessus sont historiques. BC02 **IN_REVIEW**, PR #40 sur base temporaire BC01 #39, après B18 #37 et B19 #38. Création et édition des projets reçues ; publication, historique public, catalogue, archivage et liens de demandes restent BC03/BC04. Revue humaine et intégration nécessaires.
