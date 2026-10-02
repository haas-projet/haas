# B05 — Identité et référentiels

Date : 2 octobre 2026. Branche backend/socle-auth-identity, issue de main `438ff5a866e8141fb55edfe1c09fc2869d95952b` après [fusion autorisée de B02–B04](MERGE_B02_B04.md). Les modèles partagés et leur contrat sont décrits dans [IDENTITY_DATA.md](../architecture/IDENTITY_DATA.md).

## Livraison

User/Profile/Technology et leurs relations, enums Role/AccountStatus, defaults member/active/non vérifié/non démo. Courriel normalisé et privé, pseudonyme unique sans caractères de contrôle, vérification via email_verified_at et cast immuable, technologies en table et pivot unique. Champs serveur non assignables ; Eloquent refuse les attributs ignorés. Factories adaptées, non vérifiées par défaut. Aucun changement de dépendance.

Nouvelle migration PostgreSQL, sans modifier les migrations partagées : précontrôle transactionnel des comptes existants, conservation des UUID/mots de passe/anciens noms, arrêt explicite avant modification en cas de doublon ou nom incompatible. Contraintes, FK et rollback testés sur la base dédiée. Aucun endpoint métier ni mécanisme de création de comptes privilégiés ajouté.

OpenAPI 0.3.0 référence les types d'identité du fragment identity.yaml. Les routes restent à livrer en B06–B10. Le test de payload d'inscription privilégié vérifie une erreur client et aucune création ; les routes actuelles répondent 404. Il ne prouve pas encore la validation FormRequest d'une inscription disponible. AC04 reste partiel jusqu'à B06/B09.

## Contrôles locaux

Runtime explicite PHP 8.5.10 (`C:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64\php.exe`), Composer 2.10.3 (`C:\Users\PC\AppData\Local\Temp\haas-b02-composer-2.10.3.phar`). Les commandes n'ont pas utilisé le PHP 8.3 par défaut. Les erreurs initiales PHPStan de nullabilité des relations et d'invocation des migrations dans les tests ont été corrigées, sans baseline ni suppression d'alerte.

| Commande | Résultat |
|---|---|
| composer format, puis composer lint | Style appliqué puis contrôle réussi |
| composer analyse | PHPStan/Larastan niveau 8, aucune erreur |
| composer test | 73 tests / 517 assertions réussis |
| composer test:integration | 25 tests / 222 assertions réussis sur PostgreSQL 17.0 |
| composer validate --strict --no-check-publish | Valide |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |
| node scripts/validate-pack.mjs | 18 contrôles documentaires réussis |
| node scripts/check-deployment-docs.mjs | 7 contrôles documentaires réussis |
| git diff --check | Aucune erreur d'espacement |

Total distinct : **98 tests / 739 assertions**. Les tests SQL utilisent uniquement 127.0.0.1:54681, base haas_quality_test et rôle haas_test, DB_URL vide ; garde-fou vérifié avant les migrations. Cluster temporaire arrêté en finally ; aucune base applicative ciblée. Scénarios : defaults/casts privés, affectation de masse, unicité email/handle, invalidités SQL, FK/profil unique/pivot unique, technologie utilisée non supprimable, ancien compte préservé, doublons de reprise refusés, rollback local.

## Limites et suite

B05 IN_REVIEW après observation de sa CI propre. Inscription, mot de passe 12–128, conditions versionnées, Sanctum/CORS/CSRF et Policies ne sont pas livrés par les seuls modèles ; B06 est le prochain lot. Validation complète du profil et limite de technologies en B10 ; annuaire/préférences dans les lots du pilote communauté. Aucun effet automatique de rôle sur les droits métier. Hébergement, Qodana et collation de production non vérifiés ; aucun gate ou déploiement.

## CI distante observée

[PR #8](https://github.com/haas-projet/haas/pull/8) contre main, commit applicatif `5913f3753ba65c6a42c26a19066bf726da24464d`. [Run 36951975464](https://github.com/haas-projet/haas/actions/runs/36951975464) réussi le 2 octobre 2026 ; logs lus, sans résultat déduit d'un lot précédent.

| Runtime réel | Résultats observés |
|---|---|
| PHP 8.4.26 / PostgreSQL 17 | 73 tests / 517 assertions + 25 tests / 222 assertions SQL ; lint/analyse/audit/Composer et contrôles documentaires réussis |
| PHP 8.5.11 / PostgreSQL 17 | Mêmes résultats : 98 tests / 739 assertions distincts |
| backend-ci | Réussite des deux versions exigée et constatée |

Les 243 empreintes livrées ont été contrôlées localement, aucune différence. La PR B05 reste ouverte et non fusionnée, sans revue humaine présumée. Le complément documentaire reçoit sa propre CI ; son SHA et le run final figurent dans la PR et le bilan, sans autoréférence de commit.
