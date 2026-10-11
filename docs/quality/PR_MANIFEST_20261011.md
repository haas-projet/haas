# PR capsules — intégrité et reprise du 11 octobre 2026

## État vérifié avant correction

Les six PR ouvertes #29, #30, #33, #34, #35 et #36 sont fusionnables sans conflit Git. #29 requiert une revue humaine ; #34–#36 sont encore en brouillon. Cette vérification ne constitue pas une approbation humaine.

Les trois premières CI réussissent sur les heads exacts suivants :

| PR | Head contrôlé | CI réussie |
| --- | --- | --- |
| #29 / B22 | 34ac644ea023c968a315f2c0e76ec5b81f21d165 | 37799966394 |
| #30 / B23 | eb685501eb92bee40224685fa27ee8d51c730cf3 | 37826538652 |
| #33 / B24 | 35f6270a3ef668a508cb413ddecac7a16b37c1a8 | 37832027053 |

PHP 8.4 / PostgreSQL 17, PHP 8.5 / PostgreSQL 17 et `backend-ci` sont réellement réussis pour chaque head ci-dessus. Les anciennes étapes documentaires ne contrôlaient pas SHA256SUMS. Une vérification indépendante des blobs Git a détecté des empreintes périmées et des fichiers suivis absents des trois manifestes : ils contenaient toujours 652 entrées.

## Correction

Le manifeste B22 est régénéré à partir de tous les fichiers suivis, hors SHA256SUMS lui-même. `scripts/check-manifest.mjs` compare les octets réels, rejette les doublons et les entrées non suivies, puis impose la couverture complète. Le workflow l’exécute pour les deux versions PHP. Les nouvelles sources doivent être ajoutées explicitement à l’index avant la régénération.

Le nouveau contrôle échoue réellement sur le manifeste initial et réussit après régénération. Contrôles exécutés : `node scripts/check-manifest.mjs`, `node scripts/validate-pack.mjs`, `node scripts/check-deployment-docs.mjs` et `git diff --check`. Résultats : empreintes complètes, pack 18/18 et déploiement documentaire 7/7 ; aucune erreur de diff. Les octets du backend sont inchangés par ce lot. Aucun nouveau test applicatif local n’est revendiqué pour cette correction documentaire ; sa nouvelle CI doit être reçue sur le commit publié.

Le même contrôle et la correction sont propagés vers B23 puis B24 par merges normaux. Chaque branche régénère son propre manifeste après résolution des conflits et vérifie les octets de son backend avant/après. Les résultats réellement observés sont consignés dans la PR correspondante après commit.

## Branches et limites

### Propagation B24

B24 35f6270a reçoit le prérequis documentaire B23 c753017 par merge normal. Le manifeste est régénéré et vérifié pour toute sa couverture ; pack 18/18, déploiement documentaire 7/7 et diff réussis. `git diff --quiet 35f6270a -- backend` confirme que son backend est identique. La nouvelle CI du merge reste à recevoir après publication ; aucun résultat applicatif local supplémentaire n’est revendiqué.

### Propagation B23

B23 eb685501 reçoit le prérequis documentaire B22 6021968 par merge normal. Le manifeste est régénéré pour ses propres fichiers suivis ; le contrôle complet, pack 18/18, déploiement documentaire 7/7 et diff sont réussis. `git diff --quiet eb685501 -- backend` confirme que son backend est identique. Nouvelle CI du merge à recevoir après publication ; aucun nouveau résultat applicatif local n’est inventé.

La PR #28 / B38 a été fusionnée dans main d0d47c5dd62887fcf18361f499a33a800538b2b1 le 8 octobre à 12:52:52 UTC. Le 11 octobre, les trois branches permanentes ont avancé normalement de b76612d vers ce main. La branche temporaire B38 0ad5a992d108b7d840fe19fca99da3a78366012c a été supprimée après vérification de son ascendance et de l’absence de PR ouverte dépendante. Restent 10 branches distantes : main, trois permanentes et six temporaires nécessaires aux PR ouvertes.

B25–B27 font l’objet de corrections métier séparées avec tests de régression. B26 reste partiel sans le contrat `has_lab`/Q16, dépendant de B33. Les PR non fusionnées ne sont pas déclarées DONE. B35 reste partiel ; aucun BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION ou déploiement n’est validé.

Le checkout principal conserve sa modification préexistante de `backend/routes/api/capsules-lab.php`. Le travail B18 déjà commencé reste préservé ; il reprendra après la préparation des PR prioritaires. Aucune réécriture d’historique, identité Git inventée ou approbation simulée.

## CI reçues des prérequis documentaires — 11 octobre 2026

| PR | Head exact | Run CI | Hors SQL | Integration | Types | Empreintes |
|---|---|---|---|---|---|---|
|#29|6021968b60cbc4672a128c47363d483d15b7965c|38150757607|398/3885|435/3586|51|688|
|#30|c7530174b16c997d3e684ceae1edce77cda2f89a|38150757065|406/4010|514/3930|56|723|
|#33|2463e4ba5c6db95d754b951e9b33e932d7a111ee|38150757640|413/4090|564/4269|59|749|

Les six logs PHP8.4/8.5 ont été lus : mêmes totaux par version, PHPStan, documents et manifeste réussis ; chaque backend-ci réussit. Les descriptions GitHub portent déjà le head et la CI exacts. Ces résultats ne sont pas des contrôles B25–B27.
