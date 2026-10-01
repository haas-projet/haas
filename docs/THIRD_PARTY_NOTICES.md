# HAAS — Provenance et contrôle des dépendances

Le pack initial contient des consignes originales HAAS, des transcriptions/copies de documents fournis dans la conversation, des références publiques et des scripts de contrôle documentaire. Le développement ajoute désormais les dépendances backend recensées ci-dessous. Aucune licence sur le produit HAAS entier n'est octroyée par ces notices.

## Registre à alimenter pendant le développement

| Composant | Version/commit | Origine | Licence constatée | Usage runtime/dev/service | Revue / décision |
|---|---|---|---|---|---|
| Socle Laravel et dépendances Composer B01 | Voir `backend/composer.lock` | Registre détaillé ci-dessous | MIT, BSD-3-Clause, Apache-2.0 | Runtime/dev | Revue du 2026-10-01 |

Auditer les dépendances directes/transitives, code tiers, icônes, thèmes, modèles, skills et scripts ajoutés. Ne pas ajouter une licence ouverte au dépôt entier par défaut. Conserver les notices requises. Le périmètre concours des outils de développement et services externes reste à confirmer.

## Backend B01 — 1er octobre 2026

Le squelette provient de `laravel/laravel` v13.10.1. Laravel Framework est verrouillé en 13.34.0. Les 101 paquets de `composer.lock`, avec versions, sources, références et licences déclarées, figurent dans [B01_DEPENDENCIES.json](quality/B01_DEPENDENCIES.json).

Revue : 69 paquets MIT, 29 BSD-3-Clause, deux paquets Nette proposant notamment BSD-3-Clause (option retenue), un Apache-2.0. Les notices Laravel, Mockery et Nette ont été consultées. Conserver les fichiers de licence livrés avec chaque dépendance lors de toute distribution. La notice [LICENSE-LARAVEL.txt](../backend/LICENSE-LARAVEL.txt) couvre le code tiers Laravel repris dans le socle ; elle ne donne pas une licence MIT au code HAAS. Aucun paquet à licence inconnue n'a été ajouté.

Les dépendances restent installées localement dans `backend/vendor/`, ignoré par Git. `composer audit --locked` ne signale aucun avis de vulnérabilité au moment du contrôle ; ce résultat daté ne dispense pas des contrôles suivants.


## Qualité PHP B02 — 1er octobre 2026

Quatre dépendances de développement ajoutées, toutes MIT : Pint 1.32.1, Larastan 3.12.2, PHPStan 2.2.16 et iamcal/sql-parser 0.7. Notices installées lues ; versions, références et distributions dans [B02_DEPENDENCIES.json](quality/B02_DEPENDENCIES.json). Le total passe à 105 paquets. Les 101 versions précédentes sont conservées. PHP-Parser 5.9.0 (BSD-3-Clause), déjà présent, est déclaré directement pour le test d'architecture. Aucun ajout runtime.

Composer 2.10.3 est utilisé comme outil temporaire, sans distribution dans le dépôt ni remplacement global. Les outils distribués en PHAR peuvent embarquer leurs propres composants ; conserver leurs notices avec les outils. Sources de configuration : [Pint](https://laravel.com/docs/13.x/pint), [Larastan](https://github.com/larastan/larastan), [PHPStan](https://phpstan.org/config-reference), [résolution des noms PHP-Parser](https://github.com/nikic/PHP-Parser/blob/master/doc/component/Name_resolution.markdown).

## Documents de présentation

Le PDF et le PowerPoint utilisent le logo déjà fourni dans la conversation. Les formes et schémas du PowerPoint sont éditables ; le logo reste raster. Les polices de mise en page ont servi au rendu et ne sont pas fournies en fichiers séparés dans ce pack. La palette et les noms techniques viennent des documents HAAS. Les droits sur les dépendances futures et la diffusion des kits restent à vérifier séparément.
