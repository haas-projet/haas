# HAAS — Registre d’assistance IA

| Date | Outil / usage | Périmètre | Vérification humaine | Tests |
|---|---|---|---|---|
| 30/09/2026 | ChatGPT — préparation du pack d’instructions | Plan Codex, skills HAAS, spécifications et script de contraste | À relire par l’équipe | Vérification documentaire et script consignés dans le rapport de validation de la version concernée ; aucun test de l’application |

Pour le code futur : outil effectivement utilisé, date, lot, fichiers, relecteur réel, tests réellement exécutés et limites. Ne pas qualifier automatiquement une sortie d’IA de libre de droits ou de sûre.

## Mise à jour documentaire du 1er octobre 2026

| Outil / intervention | Périmètre | Contrôles réalisés | Revue humaine de l’équipe |
|---|---|---|---|
| ChatGPT — préparation de l’atelier | Cahier consolidé, contrat atelier, plan Codex, skill supplémentaire, slides et notes | Contrôles documentaires et rendus décrits dans le rapport de validation de la version concernée ; aucun test applicatif | À réaliser |
| Logo déjà généré dans la conversation | PNG fourni, recadrage et déclinaisons raster/icônes | Formats, tailles et canal alpha contrôlés | À réaliser ; disponibilité de marque non vérifiée |

Ces entrées ne décrivent pas une utilisation effective de Codex pour produire une application ni une approbation humaine qui aurait eu lieu.

## Consolidation du déploiement — 1er octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l’équipe |
|---|---|---|---|
| ChatGPT — consolidation du déploiement | Choix Systalink + Vercel, cahier PDF, brief Codex, modèles non exécutés, présentation | Contrôles documentaires, calculs de contraste et inspection des rendus, consignés dans `docs/quality/PACK_VALIDATION.md` | À réaliser |

Aucun code du produit HAAS n’a été développé ni déployé lors de cette finalisation. Les scripts inclus vérifient des documents et des couleurs, pas le fonctionnement d’une application.


## Mise à jour documentaire selon le mail
Assistant utilisé pour consolider les exigences, instructions Codex, cahier et présentation. Aucun nouveau code applicatif déployé ; les scripts de génération/validation du dossier ne prouvent pas les tests du produit. Consigner séparément chaque usage IA pendant l’implémentation.

## Mise à jour documentaire Coup de main

Outil : assistant conversationnel, 1er octobre 2026. Tâche : intégrer la décision F18 au pack, sources Markdown, contrats de conception, tâches, critères, notes et livrables embarqués. Les scripts documentaires/contraste/rendu exécutés sont consignés dans docs/quality. Aucun développement Laravel/React, commit ou test d’usage réel n’est attesté ; relecture de l’équipe requise avant exécution. Aucune nouvelle recherche web ou validation de tarifs dans ce lot documentaire.

## Lot 1 capsules/laboratoire — 2 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/capsules-laboratoire` | Enums `App\Enums\Capsules\CapsuleVersionState` (avec `canTransitionTo` et `allowedTargets`), `App\Enums\Lab\LabRunState`, `App\Enums\Comparisons\{ComparisonState,ComparisonOutcome}` et leurs tests unitaires ; mise à jour `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 148 tests / 977 assertions OK, `composer test:integration` 35 tests / 296 assertions OK sous PostgreSQL 17, `composer audit --locked` sans alerte, `composer validate --strict --no-check-publish` valide | En attente |

Les transitions `in_review → published` et `published → withdrawn` sont déduites (B25, B31, `docs/product/CAHIER_DES_CHARGES.md:502`, AC15) et signalées « à confirmer par le relecteur » dans le test. Aucun commit poussé, aucune PR ouverte, aucune fusion effectuée.
