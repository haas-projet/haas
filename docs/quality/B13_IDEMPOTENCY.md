# B13 — Idempotence des commandes

3 octobre 2026. Branche `backend/socle-auth-idempotency`, base B12 `72330199d62938e4cb2772f05a1ceab3b041115f`. PR #10/#11/#13/#14/#15 préservées. Contrat [IDEMPOTENCY.md](../api/IDEMPOTENCY.md), OpenAPI 0.9.0. Aucun package ajouté.

## Livraison

`IdempotencyService` partagé, table `api_idempotency`, unicité acteur/route-cible/clé hashée, empreinte HMAC d'une charge canonique bornée et descripteur de réponse sans texte privé. Transaction englobant métier, audit et intention ; acteur relu/verrouillé, Policy avant consultation de la réponse, résolution courante contrôlée avant retour. Premier adaptateur réel : édition du profil avec Idempotency-Key facultatif ; comportement sans clé conservé.

Même intention : une seule version/écriture/audit ; autre charge : 409 IDEMPOTENCY_CONFLICT. Rejeu après modification : conflit sans ancien texte ; droits retirés : refus ; cible disparue : aucune recréation. Expiration fixe de 24 h, rotation de clé serveur refusant un ancien HMAC sans réexécution, purge bornée avec déclaration scheduler toutes les cinq minutes. Aucune configuration de scheduler de production réalisée.

## Contrôles locaux

PHP 8.5.10, Composer 2.10.3 temporaire déjà vérifié ; nouveau cluster PostgreSQL 17.0 isolé `127.0.0.1:54692`, base `haas_idempotency_test`, rôle `haas_test`, DB_URL vide. Aucune base applicative ciblée.

| Commande | Résultat |
|---|---|
| composer format puis composer lint | Format appliqué, contrôle final réussi |
| composer analyse | Niveau 8, aucune erreur, aucun ignore ni baseline |
| composer test | 249 tests / 2078 assertions réussis |
| composer test:integration | 112 tests / 1010 assertions PostgreSQL réussis |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |

Total distinct : **361 tests / 3088 assertions**.

Première sélection B13 : 37 tests / 153 assertions réussis, avant ajout des contrôles de cible disparue et d'autorisation préalable. Après adaptation de typage de PendingCommand, un test vérifiait la base avant l'exécution différée de la commande ; appel explicite à `run()` ajouté, test de purge relancé avec succès (1 test / 10 assertions), puis suites complètes relancées. Import de test et instruction inaccessible corrigés ; aucun contrôle supprimé ou assoupli. Les exécutions ciblées ne s'ajoutent pas au total distinct des suites complètes.

Scénarios : UUID v4 strict, casse/ordre des objets et ensemble de technologies, distinction types/null/omission/listes, refus objet/flottant/profondeur/taille/UTF-8 invalide ; réponse limitée aux références UUID ; API avec vraie session/CSRF et nouvelle session avant échéance ; aucune copie du corps, courriel ou clé brute ; isolement acteur/cible ; expiration exacte et non-prolongation ; droits périmés refusés avant lecture de l'intention ; projection nouvelle/supprimée ; résultat stocké incompatible/tiers refusé ; échecs métier, stockage et rollback englobant ; reprise de la même clé après échec ; rotation de clé ; purge réelle et déclaration de planification ; contraintes SQL et aller/retour de migration sans perdre le métier.

Concurrence : deux processus PHP indépendants, même base dédiée et même clé serveur temporaire. Fixtures commitées avant le lancement ; le parent observe les **deux workers en attente de verrou dans pg_stat_activity** avant libération. Même charge : deux succès, une seule version/audit/intention. Charges différentes : un succès, un conflit, une seule écriture. Aucun test séquentiel renommé concurrent.

## Suivi et limites

Cluster PostgreSQL B13 arrêté après les tests. `node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` sans erreur. Empreintes actualisées avant commit. CI à observer après publication. B13 en préparation avant publication/CI, puis en revue jusqu'à intégration vérifiée. Le SHA réel est communiqué dans le bilan et la PR, pas inscrit dans son propre commit.

La purge physique dépend du scheduler et de son suivi d'exploitation ; l'échéance interdit déjà le rejeu même si la purge est en retard. La garantie n'est pas prolongée au-delà de 24 h. Les futurs adaptateurs métiers exigent leurs Policies/projections, contraintes et tests de course ; aucune réception AC06/AC10 globale ni lot des collègues déclaré terminé. Aucun Qodana, SMTP réel, frontend, laboratoire, revue humaine ou déploiement validé.

Intégrer #10 → #11 → #13 → première livraison #14 → #15 avant B13, avec reciblage et contrôle du dernier SHA. B10 reste en cours pour ses contributions. Prochain lot du responsable socle : **B29 — Notifications internes**, dont les événements métier se raccordent avec leurs pilotes. Systalink B13 « En cours », puis « En cours — prêt pour revue » après CI ; aucune carte « Terminé » avant fusion vérifiée.
