# B33 — Registre fermé du laboratoire, 12 octobre 2026

## 12 octobre 2026 — Réception propre du registre sur B25

Lot B33 isolé depuis le candidat historique `04385b6573f3c6d80a71dde6d4e1fce3fbe25449`, sur B25 `365e549b2be2d9f2fedb557e8309213b182bfbaa`. Ordre **B33 → B26 → B27**, aucun cycle de prérequis. Code de registre conservé, harness de test rendu indépendant du wrapper local.

**439 tests /4215 assertions hors SQL +63 /392 SQL ciblés =502 /4607 reçus**, zéro erreur/échec/skip. Vraie liaison à deux processus1/11 incluse, sans double comptage. PHPStan8 sans erreur, Pint global, **64 types** reçus sur cette base. Base locale exclusive `haas_b33p25_review_test`, PostgreSQL17, 127.0.0.1:55447, runnerb33p25 ; composer.lock inchangé. Globale Integration locale et CI exacte à recevoir ; B33 **IN_PROGRESS** avant cette réception et revue humaine. Aucun gate, frontend ou déploiement.

### Commandes et incidents du report

- `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b33-b25-general-final.xml` :439/4215.
- `vendor/bin/phpunit --testsuite Integration --filter 'LabDefinitionHttpTest|LabRegistrySchemaTest|LabRegistryFreshMigrationTest|BindLabDefinitionCommandTest|ConcurrentLabDefinitionBindingTest|IdentityMigrationTest|HelpRequestsMigrationTest' --colors=never --log-junit storage/logs/b33-b25-targeted-received.xml` :63/392.
- `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`, `vendor/bin/pint --test`, types API :reçus.
- Première cible reportée63/384 avec1échec :0connexions observées en Lock. Le harness choisissait le wrapper local au lot hardcodéb33, lequel remplaçait la DB enfant par l'ancienne base. Le test conservait son assertion de deux vrais verrous et échouait correctement. `Process([PHP_BINARY,fixture],env_de_test_gardé)` utilise maintenant le même checkout et la configuration validée ; revalidation1/11 puis63/392. Aucun changement de production ou des quatorze fichiers B1 épinglés pour résoudre ce défaut.
- Pint initial refusait le raccord de route pleinement qualifié ; import explicite corrigé, global passé.
- Actualisation du host pendant copie vendor : autoload absent/incomplet et fichier temporaire0octet. Les wrappers PHP pouvaient finir avec exit0 sans avoir exécuté PHPUnit ; **aucune réussite déduite de ce statut**. Fichier interrompu conservé, fichiers identiques contrôlés et seuls manquants recopiés, puis bootstrap reçu avant les suites ci-dessus. Runner renforcé pour refuser un outil dont le point d'entrée vendor réel manque.

Les sections suivantes conservent les preuves de préparation initiale ; elles ne remplacent pas la réception de la base B25 ci-dessus.


## Report sur le prérequis B25

Lot seul de `04385b6573f3c6d80a71dde6d4e1fce3fbe25449` appliqué normalement, sans remplacer B26/B27 ni leurs historiques, sur B25 `365e549b2be2d9f2fedb557e8309213b182bfbaa`. Les fichiers propres B33 sont identiques au candidat initial ; le raccord de route utilise uniquement le contrôleur B33. Cette composition exige sa réception complète propre, encore non exécutée au report. La branche initiale est conservée comme preuve historique.


## Code préparé et ordre de réception

Préparation locale depuis B27 `524067b3eda56b0f6f04098423d707f6ebee4087`, pendant la revue autorisée des prérequis. Ce point de départ conserve les premières preuves ; la PR du lot doit partir de B25 `365e549b2be2d9f2fedb557e8309213b182bfbaa` pour respecter **B33 → B26 → B27**. Les quatorze fichiers B1 épinglés sont identiques sur ces deux commits. Aucun import ou appel au catalogue B26 ou aux artefacts B27 dans la production B33. Pas de publication de PR sur une base créant un cycle.

Tables APP `lab_definitions`, `lab_runs`, `lab_results`, releases/scénarios fermés, empreintes de fichiers réellement vérifiées ; lecture publique de capacité et commande interne `lab:bind-definition` avec audit système atomique. Aucun champ utilisateur ne choisit du code, un shell, une URL, un chemin ou une classe. [Contrat et limites](../api/LAB_REGISTRY.md).

## Réception locale initiale

PHP8.5.10/PostgreSQL17, base exclusive `haas_b33_review_test`, 127.0.0.1:55447 ; runner `node .worktrees/run-pr-review-20261011.cjs b33`. Vendor indépendant, composer.lock conservé.

| Commande / preuve ignorée locale | Résultat reçu |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b33-final-unit-feature-architecture-20261012.xml` | **480 tests /4437 assertions**, zéro erreur/échec/skip |
| SQL ciblé LabDefinitionHttpTest, LabRegistrySchemaTest, LabRegistryFreshMigrationTest, BindLabDefinitionCommandTest, ConcurrentLabDefinitionBindingTest ; `b33-final-targeted-received-20261012.xml` | **62 /375**, zéro réserve, dont vraie liaison concurrente |
| Concurrence seule après garde explicite READY dans la fixture ; `b33-binding-concurrent-final-20261012.xml` | **1 /11**, inclus dans le ciblé, ne pas additionner |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau8, aucune erreur, sans baseline |
| `vendor/bin/pint --test`, types API et diffcheck | Réussis, **70 types** sur cette composition initiale |

Réception disjointe initiale **542 tests /4812 assertions**. Ces ciblés ne constituent pas une suite Integration complète. Le report sur B25 doit recevoir ses propres tests généraux/ciblés/globaux, ses types et sa CI exacte avant ready. Aucun succès de la nouvelle base déduit de ces seuls chiffres.

## Contre-épreuves conservées

DTO initial18 rouges puis18/20 verts ; migration absente1/1 rouge puis1/6 verte. Quatre incohérences de résultat/état reproduites15/36 puis corrigées. Deux gardes de propriétaire courant donnaient200 au lieu404 :2/2 rouges, refus désormais réel. Commande interne absente1/1 puis liaison/rejeu/conflit/audit reçus. Reprise de résultat incohérente1/1 corrigée. Une attente de Cache-Control ignorait l'ajout `private` de Symfony ; fixture alignée sur le transport réel sans retirer `no-store`. L'ordre de clés d'un JSONB d'audit était mal attendu dans une fixture, corrigé sans changer les données. PHPStan a corrigé des types de production/tests et un refus READY absent, sans ignorer d'erreur. Journaux et JUnit rouges/verts conservés dans storage/logs ignoré ; aucun succès déduit d'un lancement interrompu.

## État du lot et limites

B33 **IN_PROGRESS** avant report et réception de la base B25. `LAB_ENABLED=false` par défaut ; ce registre ne lance aucun processus et ne crée aucun résultat simulé. B34 réservation/quota/file, B35 scénarios, B36 runtime restreint et B37 reprise restent distincts. L'index global interdit deux runs running ; la réservation d'une opération active par membre appartient à B34 pour accueillir les deux enfants séquentiels d'une comparaison. La connexion de fixtures B1 n'est pas modifiée ni présentée comme une isolation de production. Pas de revue humaine, BACKEND_GATE, frontend ou déploiement.
