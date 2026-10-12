# BC01 — Schéma des projets et découverte volontaire

## Périmètre livré

Migration additive `2026_10_11_220000_bc01_projects_and_discovery.php`, modèles, enums et factory fictive. Aucun endpoint, publication, annuaire ou rattachement par commande n'est fourni par BC01. Les routes et l'OpenAPI partagés restent à intégrer avec leurs lots métier.

Sources : `HAAS_CODEX_MASTER.md` (BC01), `docs/product/COMMUNAUTE_ET_PROJETS.md`, AC53 et AC60. Les extensions du Coup de main (BH01 et suivants) restent séparées.

## Projets

| Colonne | Contrat SQL et responsabilité |
| --- | --- |
| `id`, `owner_id` | UUID ; propriétaire existant ; FK sans suppression en cascade. Génération et choix du propriétaire par le serveur. |
| `name`, `summary`, `description` | Textes Unicode de 3–80, 30–240 et 80–4000 caractères lorsqu'ils sont connus. `NULL` autorisé dans un brouillon incomplet. |
| `project_stage` | `idea`, `prototype`, `in_development`, `launched` ; déclaration du propriétaire, pas certification. Défaut `idea`. |
| `publication_state` | `draft`, `published`, `archived` ; distinct de la phase et du masquage. Défaut `draft`. |
| `lock_version` | Entier PostgreSQL positif ; défaut 1. Le contrôle des versions et des incréments appartient aux commandes ultérieures. |
| `help_sought` | Texte facultatif, au plus 1500 caractères. |
| `demo_url`, `repository_url` | Données inertes facultatives, au plus 2048 caractères ; SQL refuse HTTP, espaces, contrôles et identifiants dans l'autorité. Aucun téléchargement, shell ni ouverture de lien. |
| `published_at`, `hidden_at`, `moderation_reason` | Champs serveur distincts ; dates avec fuseau. Aucun motif de modération exposé par ce lot. |

Une ligne `published` doit avoir une date de publication et les trois textes connus. Un brouillon n'a pas cette date. Un projet archivé peut rester sans date s'il n'a jamais été publié ; une date connue implique la conservation des textes de publication. Ce contrôle de cohérence ne remplace pas l'autorisation et les transitions des Services BC02–BC05.

`project_technologies` réutilise `technologies`, avec unicité `(project_id, technology_id)` et FKs `RESTRICT`. Les règles de publication (1–5 technologies), de longueur utile après normalisation, de secrets et de validation complète des URL doivent être reçues avec BC02–BC03 sous verrou. La factory `published()` sert seulement de fixture de schéma ; elle n'est pas une preuve de publication reçue.

## Rattachement facultatif

`help_requests.project_id` reste `NULL` pour les demandes préexistantes et indépendantes. La FK composite `(project_id, author_id) → projects(id, owner_id)` interdit de relier une demande au projet d'un autre propriétaire, y compris par SQL direct. Une suppression de projet ne supprime aucun fil.

Le champ est protégé contre l'assignation de masse. BC04 doit encore vérifier le compte courant, le projet publié/non masqué/non archivé, la demande en brouillon sans commentaire/proposition et la stabilité du lien après publication. La visibilité des ancêtres relève de BC05 ; cette migration n'octroie aucun droit de lecture.

## Préférences privées de découverte

| Colonne `profiles` | Défaut et limites |
| --- | --- |
| `directory_visible` | `false`, non nullable ; aucun consentement présumé. |
| `help_availability` | `not_specified` ; autres valeurs `available`, `unavailable`. Aucune valeur « en ligne ». |
| `contribution_interests` | `NULL` ; au plus 300 caractères Unicode. |
| `availability_updated_at` | `NULL` ; date serveur avec fuseau pour une déclaration connue. Ne correspond pas à une connexion. |

Déclarer sa disponibilité n'active pas l'annuaire. Ces champs restent protégés dans le modèle jusqu'aux commandes BC06. Aucune modification d'identité ni donnée privée supplémentaire n'est projetée. BC06 doit encore filtrer avant pagination les comptes actifs, vérifiés et volontaires, puis ne retourner que la liste blanche publique ; aucun courriel, IP, session, statut administratif ou dernière connexion.

## Conservation et retour arrière

La montée ajoute uniquement les tables et colonnes. Elle ne crée aucun projet, date de disponibilité, publication, lien, révision ou notification à partir de données historiques. Les conversations et snapshots B18/B19 restent inchangés.

Le retour arrière est transactionnel et verrouille toutes les tables concernées avant son contrôle. Il refuse toute ligne de projet, tout rattachement et toute préférence connue (`true`, disponibilité déclarée, intérêts non `NULL`, même vides, ou date connue). Il retire uniquement un schéma encore vide et les préférences par défaut, sans déposer les discussions. L'arrêt sur des données connues demande une migration explicite ultérieure ; leur perte n'est pas autorisée.

## Réception locale

Les commandes utilisent le runner dédié `node .worktrees/run-pr-review-20261011.cjs bc01 …` depuis la racine, PHP 8.5.10 et uniquement `haas_bc01_review_test` sur le cluster local `127.0.0.1:55447`. Aucun autre service/base n'est ciblé.

Premières preuves réelles :

- Unit, `--testsuite Unit --filter 'ProjectEnumsTest|ProjectSchemaModelsTest'` : rouge 7 tests / 7 assertions / 7 échecs avant les modèles et enums ; vert 7 tests / 60 assertions après leur ajout.
- SQL, `--filter test_fresh_schema_has_private_defaults_and_separate_states` : rouge 1 test / 1 assertion, table `projects` absente avant la migration.
- Premier ciblage SQL, `--filter 'ProjectsSchemaTest|ProjectsDiscoveryMigrationTest'` : vert 57 tests / 392 assertions ; montée/base neuve, références, défauts privés, conservation legacy et refus du retour arrière.

Les résultats finaux de composition et d'analyse seront ajoutés après exécution sur le code figé. Ces preuves locales ne valent ni CI distante, ni revue humaine, ni `BACKEND_GATE`, ni autorisation frontend/production.

Réception du code figé : 66/469 SQL ciblés et composition, 530/5167 hors SQL, PHPStan niveau8 et Pint complets reçus. La suite SQL complète reste en cours, aucune CI distante reçue pour ce lot. [Bilan détaillé](../quality/BC01_PROJECT_SCHEMA_20261011.md).

## 12 octobre 2026 — Réception complète BC01

Code `f2af47377bfc54e0c4bd35be5d6ae3ebd306d8f2`, [CI 38177740506](https://github.com/haas-projet/haas/actions/runs/38177740506) reçue dans chacun des deux journaux PHP 8.4/8.5 : **530/5167 hors SQL + 691/7493 Integration = 1221 tests / 12660 assertions par PHP**, 66 types et 722 empreintes, trois jobs réussis. PostgreSQL local complet reçu au même code, 691/7493, zéro erreur/échec/skip. Les anciens ciblés ne sont pas additionnés à la suite complète.

Les réceptions initiales ci-dessus sont historiques. Revue humaine et intégration requises ; aucune commande projets/annuaire ou gate validée par ce seul schéma.
