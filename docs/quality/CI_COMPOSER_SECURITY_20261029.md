# Correction de sécurité Composer — CI #139 à #153

**Date :** 29 octobre 2026. **Statut :** correctif vérifié localement sous PHP 8.5.10, avec réserves SQL/CI/revue humaine. Aucune réception globale du backend.

## Périmètre préservé

Base exacte : PR #54, commit `31c79b09a3f1e1e39f74511b7f5bc8a94a9e2a39`. Préparation dans `.worktrees/ci-security-20261029`, en HEAD détachée : aucune branche existante modifiée ni nouvelle branche créée. Le travail communautaire BC03, les autres worktrees et les conflits préexistants de l'intégration sont préservés. Aucun push, merge, déploiement ou relancement distant.

Les PR #45–54 sont ouvertes en brouillon et empilées : chaque PR suivante dépend de la précédente. Un même lockfile vulnérable est donc repris dans plusieurs runs ; leur nombre ne signifie pas autant de défauts métier distincts.

## Causes observées dans les journaux GitHub

| Runs | Cause réellement observée sur les deux versions PHP |
|---|---|
| [#139](https://github.com/haas-projet/haas/actions/runs/38755322099) | Intégration : 709 tests / 5064 assertions, trois erreurs et un échec dans les tests VerificationCases. Réflexion invoquée sur une autre instance de migration ; attentes de mass assignment incompatibles avec la protection active ; fixture violant plusieurs contraintes SQL. |
| [#140](https://github.com/haas-projet/haas/actions/runs/38756657493) à #144 | Les tests d'intégration passent dès #140 : 709 / 5070. Échec de `composer audit --locked --no-interaction` sur HTTP Foundation et YAML. L'audit ayant été sauté dans #139, on ne peut pas dater l'introduction des alertes à #140. |
| [#145](https://github.com/haas-projet/haas/actions/runs/38836596920) | Architecture : 439 tests / 4589 assertions, un échec. `StartComparisonService` dépend de `ServiceUnavailableHttpException`. Correction déjà présente ensuite dans `8a5490d410f0a0ec96fe3eea3d4a47c11bf1f8f6` ; ne pas assouplir le test d'architecture. |
| [#146](https://github.com/haas-projet/haas/actions/runs/38837713599) | Run annulé pendant l'intégration ; ce n'est pas un résultat de tests en échec. |
| [#147](https://github.com/haas-projet/haas/actions/runs/38837920778) à [#153](https://github.com/haas-projet/haas/actions/runs/39049080706) | Même échec d'audit sur les deux paquets verrouillés en 8.0.15, confirmé sur les deux jobs de chaque tentative initiale. |

Le job final `backend-ci` propage l'échec de la matrice ; il n'est pas une cause supplémentaire.

### Dernière tentative de #153, avant correction

Run `39049080706`, tentative **3**, au SHA de base ci-dessus :

| Job | Unités / HTTP / architecture | Intégration PostgreSQL | Audit |
|---|---|---|---|
| [PHP 8.4.26 — 117543458856](https://github.com/haas-projet/haas/actions/runs/39049080706/job/117543458856) | 501 tests / 5050 assertions, OK | 1083 tests / 6218 assertions, OK | Échec, sortie 1 |
| [PHP 8.5.11 — 117543458658](https://github.com/haas-projet/haas/actions/runs/39049080706/job/117543458658) | 501 tests / 5050 assertions, OK | 1083 tests / 6218 assertions, OK | Échec, sortie 1 |

Ces résultats concernent les **anciennes dépendances**, pas le présent correctif. Le durcissement des routes n'est pas l'étape en échec dans ce run. L'étape documentaire suivante est sautée après l'audit rouge.

## Correctif ciblé

L'audit local du lock initial échoue encore et signale maintenant **trois** paquets. MIME apparaît dans l'audit actuel, contrairement aux journaux historiques analysés.

| Paquet | Avant → après | Avis officiel |
|---|---|---|
| `symfony/http-foundation` | 8.0.15 → **8.1.10** | [CVE-2026-108082](https://symfony.com/cve-2026-108082), `PKSA-xmwy-rmx5-m7h4` ; la cible inclut aussi le correctif de régression whitespace livré en 8.1.9. |
| `symfony/mime` | 8.0.15 → **8.1.10** | [CVE-2026-45070](https://symfony.com/cve-2026-45070) ; retenir le seuil rectifié 8.1.10, pas l'ancien seuil 8.0.12. |
| `symfony/yaml` | 8.0.15 → **8.1.10** | [CVE-2026-109413](https://symfony.com/cve-2026-109413), `PKSA-xz3r-9gp4-4m1z`. |

Les trois composants conservent leur licence **MIT**, vérifiée dans le lock généré. Aucune dépendance ajoutée ou retirée ; Laravel et les autres paquets restent inchangés.

Les correctifs demandent PHP `>=8.4.1`. `require.php` passe donc de `^8.4` à `^8.4.1`, et `config.platform.php` de `8.4.0` à `8.4.1`. Les prérequis des README et de `docs/COMMANDS.md` sont alignés. Ce minimum n'est pas une recommandation de déployer une ancienne révision PHP : utiliser un runtime maintenu et à jour.

Aucune modification du workflow, des routes, des protections de modèles ou des tests existants. Aucun avis ignoré, aucun `continue-on-error`, aucune désactivation de l'audit ou des exigences de plateforme.

## Commandes et résultats locaux

Exécutable PHP : `C:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe`. Composer **2.10.3**, copie isolée dans `.worktrees/ci-tools-20261029/composer.phar`, sans modification de l'installation globale ; SHA-256 vérifié contre le digest de l'asset officiel : `7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6`.

Depuis `backend/`, avec ce PHP et ce PHAR :

```text
composer update symfony/http-foundation:^8.1.9 symfony/mime:^8.1.10 symfony/yaml:^8.1.6 --with-dependencies --minimal-changes --no-install --no-scripts --no-plugins --no-interaction --no-progress
composer install --prefer-dist --no-interaction --no-progress --no-plugins
```

Le premier update a aussi été exécuté en `--dry-run` : trois mises à jour seulement. L'update réel produit le même résultat. Une configuration locale ignorée est issue de `.env.testing.example`, avec clé générée localement ; la clé B2 est éphémère et transmise uniquement au processus de tests, sans affichage.

| Contrôle exécuté | Résultat |
|---|---|
| `composer validate --strict --no-check-publish --no-interaction` | Réussi. |
| `composer check-platform-reqs --no-interaction` | Réussi sur le runtime réel PHP 8.5.10. |
| `composer audit --locked --no-interaction` | Aucun avis de vulnérabilité trouvé. |
| `composer test --no-interaction` | **501 tests / 5050 assertions**, OK, 2 min 02,149 s. |
| `composer lint --no-interaction` | Pint : **710 fichiers**, PASS. |
| `composer analyse --no-interaction` | PHPStan/Larastan niveau 8 : aucune erreur. |
| `php -l scripts/ops/exercise-backup.php` et `php -l scripts/generate-api-types.php`, depuis la racine | Aucune erreur de syntaxe. |
| `php scripts/generate-api-types.php --check` | **105 types API à jour**. |
| `node scripts/validate-pack.mjs` | **18/18**, uniquement documentaire. |
| `node scripts/check-deployment-docs.mjs` | **7/7**, uniquement documentaire. |

Avant régénération du manifeste, son contrôle détecte exactement les cinq fichiers du correctif déjà modifiés, sans autre empreinte périmée. Régénérer `SHA256SUMS` et contrôler sa couverture après indexation explicite du rapport et du suivi, avant le commit.

## Réserves et reprise

- **Non exécutés sur ce correctif :** suite PostgreSQL complète et runtime PHP 8.4 natif. Le cluster local dédié est arrêté ; aucune base applicative n'a été ciblée.
- **Non exécutée :** nouvelle CI distante. Les succès SQL de #153 ne se transfèrent pas à ce nouveau lockfile.
- Transmettre le correctif au responsable du socle, puis obtenir l'autorisation de publication. L'intégrateur doit coordonner sa propagation dans la pile #45–54 sans réécrire les branches des collègues et revérifier chaque nouveau SHA.
- Après publication autorisée : recevoir la matrice PHP 8.4/8.5, PostgreSQL et les contrôles documentaires sur le SHA exact, puis la revue humaine. Ne pas simplement relancer l'ancien SHA vulnérable.
- Aucun statut métier porté à DONE, aucun BACKEND_GATE reçu, aucun GO_FRONTEND ou GO_PRODUCTION.
