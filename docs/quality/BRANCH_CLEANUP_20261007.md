# Nettoyage des branches distantes — 7 octobre 2026

## État final constaté à 19:27:42 UTC

**10 branches distantes : quatre permanentes et six temporaires liées aux PR ouvertes #24/#28/#29/#30/#31/#33.** Les trois permanentes et main restent à `f1f6238`. Les étapes précédentes ci-dessous sont historiques : 13 → 11, ajout B17 → 12, ajout externe B24 → 13, puis suppression des trois intermédiaires fusionnées → 10.

Pendant cette session, les fusions externes #32 dans B16 (`a3fd442`), #26 dans B15 (`f346985`) et #25 dans B14 (`61c4184`) ont été constatées. #25 ayant précédé les autres, B15 `f346985` a ensuite été consolidé dans B14 par merge normal **`d9cba0b5eea334a05fce1acaed6be3936c692ef9`**, sans conflit ni réécriture. Son backend est identique à B17 `8234470`. La [CI 37666083469](https://github.com/haas-projet/haas/actions/runs/37666083469) est verte : PHP 8.4/8.5 et backend-ci. Les 671 tests / 6805 assertions et Pint/PHPStan déjà réussis concernent le même code ; les contrôles documentaires et 49 types ont été relancés.

Les trois références intermédiaires ont ensuite été supprimées après contrôle frais de leurs pointes, de leur ascendance vers **B14 publié `d9cba0b`** et de l'absence de PR ouverte les utilisant comme head ou base :

| Référence supprimée | Pointe conservée dans B14 |
|---|---|
| `backend/communaute-entraide-b15` | `f346985df99b65f022f7248243ac6ba605f46d16` |
| `backend/communaute-entraide-b16` | `a3fd44213182215c28cd4ba9b7710ea389722a94` |
| `backend/communaute-entraide-b17` | `82344704f690cc49d5ae73c67d7cbd5e71cf78b3` |

Ces trois fusions sont intermédiaires : **B14–B17 ne sont pas encore intégrés dans main**. Le code demeure dans la PR #24 et les références locales sont conservées. Les PR #24 et #31 restent ouvertes, approbation humaine obligatoire avant fusion. Les six temporaires restantes sont B14, B22, B23, B24, B38 et la branche documentaire ; B24 externe `e5dabac` et B23 `93fafea` sont préservés sans validation nouvelle de contenu par ce nettoyage. Aucun gate, frontend ni déploiement.

La demande de publication et de réduction des branches a été exécutée après un inventaire des références distantes, une vérification d'ascendance Git et une lecture des PR ouvertes. Le dépôt passe de **13 à 11 branches distantes**. Les branches portant du travail non fusionné sont conservées.

## Référence de l'inventaire

`origin/main` : `f1f6238e9deaa5d4577ed3c728777a98f1f4aa28`.

Les contrôles utilisés sont `git for-each-ref` pour les pointes, `git merge-base --is-ancestor <branche> origin/main` pour l'intégration des commits, `git rev-list --left-right --count origin/main...<branche>` pour les écarts et `gh pr list`/`gh pr view` pour les PR ouvertes ou fusionnées. Aucun test applicatif n'est annoncé par ce nettoyage.

## Deux branches temporaires supprimées

| Branche | Pointe conservée dans main | Preuve de fusion |
|---|---|---|
| `backend/communaute-entraide-b11-schema` | `2771d3013332e70747316f55e09d87f54281aa36` | Ascendance vers main vérifiée ; [PR #23](https://github.com/haas-projet/haas/pull/23) fusionnée par `17c6daaa605ff36ed6513d57fd1393d8175e6c14`. |
| `backend/capsules-laboratoire-b35-brique-b1` | `108aa7d09850e366ff8d9c95cfd9eefc1fc4ea23` | Ascendance vers main vérifiée ; [PR #27](https://github.com/haas-projet/haas/pull/27) fusionnée par `f1f6238e9deaa5d4577ed3c728777a98f1f4aa28`. |

Aucune PR ouverte ne cible ces deux branches. Leur suppression distante ne supprime pas leurs commits, qui demeurent accessibles dans l'historique de main. L'inventaire après suppression confirme leur absence et les onze références restantes.

## Trois branches permanentes synchronisées

| Branche conservée | Ancienne pointe | Avancement vers main |
|---|---|---|
| `backend/socle-auth` | `7a8c672dee783285626aaddecfde74477e1c2604` | 28 commits, sans commit propre ni réécriture. |
| `backend/communaute-entraide` | `5854e04efa2c154f6fdbbf150bbbf1ff38154199` | 18 commits, sans commit propre ni réécriture. |
| `backend/capsules-laboratoire` | `71daddf959673d42e0afe836618ada757fcfab9c` | 22 commits, sans commit propre ni réécriture. |

Les trois anciennes pointes étaient des ancêtres de main. Les trois branches permanentes distantes portent désormais `f1f6238e9deaa5d4577ed3c728777a98f1f4aa28`, comme main. Les contrôles des PR dont la base est une branche permanente restent à observer après cet avancement.

## Sept branches non fusionnées conservées

| Branche | Pointe au moment du contrôle | PR et dépendance |
|---|---|---|
| `backend/capsules-laboratoire-b22-schema` | `58fe552f787fd68d36312afb63b6f0b06066f01c` | [#29](https://github.com/haas-projet/haas/pull/29), brouillon vers `backend/capsules-laboratoire`. |
| `backend/capsules-laboratoire-b23-brouillons` | `f20f01f7868e09c9b564d00b0e7a60bfe32e6962` | [#30](https://github.com/haas-projet/haas/pull/30), brouillon vers B22. |
| `backend/capsules-laboratoire-b38-api-demo-b2` | `897c70d46f900a2fd064110c1bd603a22d697183` | [#28](https://github.com/haas-projet/haas/pull/28), vers main ; isolation B2 bloquée selon `MERGE_MADINA.md`. |
| `backend/communaute-entraide-b14` | `6a0db1edc1f7565f5a001bb6bd9f63bb5f435fb3` | [#24](https://github.com/haas-projet/haas/pull/24), vers `backend/communaute-entraide`. |
| `backend/communaute-entraide-b15` | `0cfcde12cc523113f48ca1700e9e3abfee6880dc` | [#25](https://github.com/haas-projet/haas/pull/25), vers B14. |
| `backend/communaute-entraide-b16` | `8e9a60e3bc367debdc5c4efe9164084a1b8f0757` | [#26](https://github.com/haas-projet/haas/pull/26), vers B15. |
| `review/maadinaa-20261007` | `a56fae1c90780dc3bff2c0b186e208782fd65073` | [#31](https://github.com/haas-projet/haas/pull/31), vers main ; publication des README et du suivi reprise, approbation humaine requise avant fusion. |

Ces sept pointes ne sont pas des ancêtres de main. Supprimer leurs branches maintenant ferait disparaître les références de livraison et les bases de plusieurs PR dépendantes. Le retour aux quatre branches permanentes exige leur intégration vérifiée ou une autre organisation explicitement décidée en préservant le travail et les PR.

## Reprise

La publication de la branche documentaire a réussi après les erreurs GitHub consignées dans le suivi précédent. Le head distant constaté de #31 est `a56fae1c90780dc3bff2c0b186e208782fd65073`. La PR reste ouverte : aucune approbation humaine ni fusion n'est présumée.

Après chaque intégration supplémentaire, recontrôler les pointes, l'ascendance et les PR dépendantes avant une nouvelle suppression. La suite communautaire B14/B15/B16 et la préparation indépendante de B17 restent à coordonner ; B22/B23 sont en brouillon et B38 reste bloqué. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION n'est reçu par ce nettoyage.

## Addendum après publication B17

Le passage **13 → 11** ci-dessus reste le snapshot du nettoyage. La publication de B17 dans [#32](https://github.com/haas-projet/haas/pull/32) ajoute ensuite une branche : **12 références distantes**, dont les trois branches permanentes et main, toujours à `f1f6238`. Les huit temporaires portent les PR #24/#25/#26/#28/#29/#30/#31/#32 ; leur suppression attend une intégration vérifiée et le contrôle des dépendances.

| Lot | Head publié | CI constatée |
|---|---|---|
| B14, #24 vers main | `230b83b01c1b1c2abe496bd76c6fead7dbbaf708` | [37656537495](https://github.com/haas-projet/haas/actions/runs/37656537495), PHP 8.4/8.5 et backend-ci réussis. |
| B15, #25 vers B14 | `a3eb9b9e5a41b1ce47356ef10593cfd950684279` | [37656739642](https://github.com/haas-projet/haas/actions/runs/37656739642), mêmes contrôles réussis. |
| B16, #26 vers B15 | `d3d84073b6b380b9ced2d8d9f68f6821636341a0` | [37656900154](https://github.com/haas-projet/haas/actions/runs/37656900154), mêmes contrôles réussis. |
| B17, #32 vers B16 | `82344704f690cc49d5ae73c67d7cbd5e71cf78b3` | [37662853388](https://github.com/haas-projet/haas/actions/runs/37662853388), mêmes contrôles réussis ; **671 tests / 6805 assertions par version PHP**. |

B17 métier `238d5e96593cb7dca32844b50d4077eee6f0adca` et synchronisation `6737081bd51e930a01a2001218d74cf418a11f5b` sont conservés. La première CI `37657236028` avait échoué sous PHP 8.4 dans un ancien test d'idempotence après 23 heures, à 419. Le correctif `8234470` modifie seulement la simulation d'expiration des cookies de test et conserve le refus serveur d'un cookie périmé volontairement envoyé ; l'échec est reproduit avant correction. Les deux jobs finaux confirment **328 / 3563 hors SQL** et **343 / 3242 PostgreSQL**. Aucun contrôle absent ni revue humaine simulée.

Lors du dernier inventaire, B23 est passé à `93fafea553f7e74d2eb384a879be7d2a897357c4` par un travail externe à cette intervention. La pointe historique `f20f01f` reste celle observée au nettoyage ; la branche et la PR #30 sont préservées, sans validation de ce nouveau contenu par ce rapport. #24 et #31 restent `REVIEW_REQUIRED`, sans revue enregistrée, avant fusion vers main.

## Contrôles du compte rendu

Le rapport est décodable en UTF-8 strict sans caractère de remplacement. `SHA256SUMS` couvre 517 fichiers versionnés ou nouvellement autorisés, dans l'ordre existant ; le manifeste lui-même et les fichiers ignorés sont exclus.

| Commande | Résultat observé |
|---|---|
| `node scripts/validate-pack.mjs` | 18/18, aucun échec. |
| `node scripts/check-deployment-docs.mjs` | 7/7, aucun échec. |
| `C:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe scripts/generate-api-types.php --check` | 28 types API à jour. |
| `git diff --check` | Sans erreur. |

L'appel initial du générateur avec le `php` du PATH a été refusé parce qu'il exécutait PHP 8.3.12 ; la relance explicite avec le PHP 8.5.10 existant a réussi. Aucun test applicatif, serveur, Qodana ni contrôle de déploiement réel exécuté pour ce compte rendu documentaire.

## Nettoyage après fusion réelle de #24 et #31 — 20:36 UTC

GitHub constate #31 MERGED vers main e3bd34c à20:02:57UTC et #24 MERGED vers main b76612d à20:24:58UTC ; head #24 2addb4d et main passent les CI 37681430317/37681824151, PHP8.4/8.5/backend-ci. Aucun avis absent n’est inventé.

Avant mutation : dix références réellement lues, quatre permanentes et six temporaires ; contrôle des SHA exacts et des PR ouvertes. Les heads 2addb4d et0575552 et l’ancien socle f1f6238 sont tous ancêtres de main b76612d. Aucune PR ouverte ne cible ni ne porte les références B14 ou review/maadinaa.

Push atomique sans réécriture : trois branches permanentes avancées de f1f6238 àb76612d ; seules backend/communaute-entraide-b14 et review/maadinaa-20261007 supprimées. Branches locales et worktrees conservés. Inventaire distant après opération : **8 références** : main, backend/socle-auth, backend/communaute-entraide, backend/capsules-laboratoire (toutes b76612d), plus les quatre branches des PR #28/#29/#30/#33. Ces dernières restent nécessaires jusqu’à leur intégration vérifiée.
