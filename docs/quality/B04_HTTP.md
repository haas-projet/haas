# B04 — Contrat HTTP, erreurs et pagination

Date : 2 octobre 2026. Branche `backend/socle-auth-http`, issue de B03 `a8952696bde1e1111f0edcddc6310367ad264ac7`. PR B02 #5 et B03 #6 encore ouvertes au démarrage ; aucune fusion ni revue humaine présumée. La PR de ce lot cible temporairement `backend/socle-auth-ci`.

## Livré

- ApiExceptionRenderer enregistré dans bootstrap/app.php : enveloppe HAAS, messages publics français, 401/403/404/409/419/422/429/500/503 et erreurs HTTP usuelles. Validation liée aux champs ; objet fields vide conservé. Aucun SQL, chemin ou trace dans les réponses, y compris en mode debug.
- UUID de requête généré côté serveur, en-tête X-Request-ID, même valeur dans l'erreur et le contexte des exceptions journalisées. Aucune confiance accordée à l'en-tête client ; aucune conservation statique entre requêtes.
- Retry-After et en-têtes de transport utiles préservés ; erreurs no-store. L'API inconnue reste JSON sans dépendre d'Accept. Le HTML hors API est conservé.
- PaginatedRequest, PageData readonly et PaginatedResourceCollection : valeurs par défaut 1/20, taille 1–50, page positive bornée à 2147483647, paramètres inconnus refusés ; collection data/meta et Resource de domaine explicite.
- OpenAPI commun, fragments détenus par les trois pilotes et [guide d'utilisation](../api/HTTP_CONTRACT.md). Aucun endpoint métier fictif. Les fragments sont référencés dans un index documentaire ; les Path Items seront assemblés explicitement lors de leur livraison.

## Dépendance revue

Ajout de développement uniquement : symfony/yaml v8.0.15, MIT, référence `38e4b36d74a20dd9124a5b34c3e83e270b9649b8`, PHP >=8.4. Métadonnées officielles et LICENSE installé lus ; inventaire [B04_DEPENDENCIES.json](B04_DEPENDENCIES.json). Utilisé pour analyser les contrats YAML dans PHPUnit. Aucun autre paquet mis à jour ou retiré ; 105 dépendances conservées, 106 au total. Aucune dépendance runtime ajoutée.

Source de licence : [composer.json officiel Symfony YAML](https://github.com/symfony/yaml/blob/38e4b36d74a20dd9124a5b34c3e83e270b9649b8/composer.json). Le premier essai de lecture YAML via Python a constaté PyYAML absent ; aucun contrôle Python n'est déclaré réussi et aucune installation Python effectuée.

## Vérifications locales réelles

PHP 8.5.10 explicite : `C:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64\php.exe` ; Composer 2.10.3 en copie temporaire vérifiée lors de B02 (`C:\Users\PC\AppData\Local\Temp\haas-b02-composer-2.10.3.phar`). Les commandes Composer ci-dessous ont utilisé ces chemins, pas le PHP 8.3 par défaut.

| Commande | Résultat observé |
|---|---|
| composer update symfony/yaml --minimal-changes --no-interaction --no-progress --no-plugins | 1 installation, 0 mise à jour, 0 suppression |
| composer format puis composer lint | Formatage appliqué, contrôle final réussi |
| composer analyse | PHPStan/Larastan niveau 8 : aucune erreur |
| composer test | 60 tests, 459 assertions, réussis |
| composer test:integration | 2 tests, 116 assertions, réussis sur PostgreSQL 17.0 |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous les prérequis réussis |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité trouvé |
| node scripts/validate-pack.mjs | 18 contrôles documentaires réussis |
| node scripts/check-deployment-docs.mjs | 7 contrôles documentaires réussis |
| git diff --check | Aucune erreur d'espacement |

Total distinct : **62 tests / 575 assertions**. Le premier passage PHPStan a signalé une classe de modèle fictive mal typée dans un test ; la fixture utilise maintenant le vrai modèle User, sans suppression d'alerte ni baseline.

Le cluster PostgreSQL temporaire déjà dédié à HAAS a été relancé uniquement sur `127.0.0.1:54681`, rôle `haas_test`, base `haas_quality_test`, DB_URL vide. La cible est contrôlée par PostgresTestCase avant RefreshDatabase. Les tests vérifient aussi la seconde page, l'absence de doublons, les pages vides, le total et les champs de Resource. Serveur arrêté en finally, arrêt confirmé par pg_ctl ; le service existant sur 5432 n'a pas été utilisé. Aucun .env privé livré.

Les routes de fixture n'existent que pendant les tests. Le contrôle OpenAPI analyse le YAML et résout les références locales ; il compare les propriétés d'erreur et les bornes de pagination au contrat. Ce n'est pas un validateur exhaustif de la norme. La projection SQL est une fixture, pas un annuaire public livré.

## Portée et suite

AC03/AC05 : socle de rendu des interdictions et de validation vérifié ; les commandes métier, absence de mutation, interfaces et conservation de saisie restent à tester dans leurs lots. Ces AC ne sont pas déclarés intégralement reçus. Sanctum, vraie session/CSRF, CORS et codes métier spécifiques sont à implémenter dans les lots concernés. Le test 419 vérifie le renderer, pas un parcours navigateur authentifié.

Qodana et hébergement restent non vérifiés. Intégrer les PR dans l'ordre B02 → B03 → B04 ; recibler sur main après intégration des prérequis et revérifier les contrôles. Prochain lot personnel : B05 identité/rôles/états. BACKEND_GATE reste PENDING, aucun GO_FRONTEND ou déploiement.

## CI observée — B04 en revue

[PR #7](https://github.com/haas-projet/haas/pull/7), commit applicatif `1c4c343d11d4bf86e65c928a92e82b29758a04de`. [Run 36946538852](https://github.com/haas-projet/haas/actions/runs/36946538852) terminé avec succès le 2 octobre 2026. Les logs ont été lus :

| Runtime réel | Résultats |
|---|---|
| PHP 8.4.26 / PostgreSQL 17 | 60 tests / 459 assertions + SQL 2 tests / 116 assertions ; lint, analyse, Composer/audit, contrôles documentaires réussis |
| PHP 8.5.11 / PostgreSQL 17 | Mêmes résultats : 62 tests / 575 assertions distincts |
| backend-ci | Réussite des deux jobs exigée et constatée |

B04 est IN_REVIEW ; PR ouverte, non fusionnée, sans revue humaine présumée. Les 230 empreintes des fichiers livrés ont été vérifiées localement, aucune différence. Le complément documentaire reçoit sa propre exécution CI ; le bilan et les contrôles de PR indiquent le dernier SHA/run, sans inscrire le SHA de ce complément dans lui-même.
