# Backend HAAS — travail à trois

Organisation confirmée par l'utilisateur le 1er octobre 2026. Elle remplace les anciennes mentions d'une équipe de deux pour le développement courant. Le mail source et les livrables historiques restent conservés. Le périmètre, les 122 identifiants et les portes backend/frontend ne changent pas ; les statuts évoluent avec les preuves dans `tasks.json`.

## Responsabilités et branches

| Responsable | Tâche de coordination | Branche de départ | Lots backend pilotés | Relecteur principal |
|---|---|---|---|---|
| `ousseynoufayeisidk-sys` | [#1 — Socle, identité, services communs et intégration](https://github.com/haas-projet/haas/issues/1) | `backend/socle-auth` | B01–B10, B12–B13, B29–B32, B39–B44 (22) | `LamineGL` |
| `LamineGL` | [#2 — Demandes, projets et coups de main](https://github.com/haas-projet/haas/issues/2) | `backend/communaute-entraide` | B11, B14–B21, BC01–BC08, BH01–BH10 (27) | `mdev44-code` |
| `mdev44-code` | [#3 — Capsules, laboratoire et vérifications](https://github.com/haas-projet/haas/issues/3) | `backend/capsules-laboratoire` | B22–B28, B33–B38, BV201–BV210 (23) | `ousseynoufayeisidk-sys` |

Les 72 lots backend ont chacun un pilote. S01/S02 sont coordonnés par `ousseynoufayeisidk-sys`, avec les informations réelles des trois personnes. Chacun écrit les tests de son domaine, tient son contrat API et corrige les régressions qu'il introduit. Les relecteurs indiqués sont des responsabilités proposées, pas des validations déjà obtenues.

Les trois tâches regroupent le travail ; elles ne doivent pas devenir trois grosses PR à fusionner en fin de projet. Un lot cohérent avec ses tests par PR, subdivisé si nécessaire. Ne pas commencer le lot suivant sur une branche dont la PR attend une revue. Les trois branches de départ du tableau et `main` sont conservées, conformément à la préférence confirmée le 2 octobre. Réutiliser sa branche permanente après synchronisation. Les branches temporaires sont supprimées après fusion et vérification de leurs commits dans `main`, une fois les éventuelles PR dépendantes reciblées ; ne pas activer une suppression automatique qui emporterait les branches permanentes.

Si l'utilisateur demande explicitement de continuer pendant une revue, préparer le lot suivant sur une branche dérivée séparée, sans modifier la PR en attente. Cas B03 : `backend/socle-auth-ci` depuis B02, PR ciblant temporairement `backend/socle-auth`. Fusionner les prérequis dans l'ordre, puis recibler vers main et revérifier le dernier commit. Aucune fusion, revue ou validation n'est implicite dans cette préparation.

## Socle intégré — 3 octobre 2026

Les PR #10/#11/#13–#20 sont fusionnées sur demande explicite dans main `a051e81`, CI post-fusion verte. B01–B09/B12/B13/B32 sont DONE ; les profils, notifications, modération et réception gardent leurs réserves détaillées dans [MERGE_SOCLE.md](../quality/MERGE_SOCLE.md). Les neuf branches temporaires sont supprimées ; les trois branches permanentes et main restent présentes. backend/socle-auth est synchronisée avec main. Les deux autres pilotes intègrent origin/main depuis leur propre branche en préservant leurs travaux ; leur contenu n'a pas été écrasé. La PR #12 reste ouverte.

## Démarrage parallèle après B01

La PR #4 est fusionnée dans `main` depuis le 1er octobre 2026, sur demande explicite de l'utilisateur. Cette demande permet de commencer le code indépendant des trois domaines en parallèle dès B01. Les prérequis ci-dessous portent sur la fusion du code dépendant ; ils ne bloquent plus tout démarrage d'un domaine.

Le 2 octobre, les PR #5/#6/#7 ont aussi été fusionnées sur demande explicite : B01–B04 sont disponibles dans main (`438ff5a`, CI verte). B05 est préparé séparément ; ne pas supposer ses modèles déjà intégrés tant que sa PR n'est pas fusionnée. Voir [la preuve d'intégration](../quality/MERGE_B02_B04.md).

| Développeur | Code à commencer sur sa branche | Dépendances à intégrer avant livraison complète |
|---|---|---|
| `ousseynoufayeisidk-sys` | B05 : identité/référentiels, puis authentification | B01–B04 intégrés ; aucun autre domaine requis pour B05 |
| `LamineGL` | Préparation B11 : enums d'état propres aux demandes, DTO, règles indépendantes et tests unitaires ; préparer les migrations et cas PostgreSQL | B05 pour Technology et l'identité ; B06–B09 pour les endpoints authentifiés |
| `mdev44-code` | Préparation B22 : enums propres aux capsules, DTO de contenu/provenance et tests unitaires ; préparer le contrat de versionnement | B05 pour les référentiels ; B11 pour les références aux demandes ; droits/auth avant endpoints |

Utiliser les fichiers du domaine et le socle existant. Ne pas dupliquer User/Profile/Technology, inventer les tables d'un collègue ou désactiver une clé étrangère pour faire passer un test. Un test unitaire indépendant peut réussir avant les prérequis SQL ; les tests d'intégration dépendants restent explicitement à exécuter. Les PR de préparation peuvent être ouvertes en brouillon, sans déclarer B11/B22 terminés.

Chacun récupère sa branche synchronisée, depuis un répertoire propre :

```powershell
git fetch origin
git switch backend/communaute-entraide # Adapter à sa branche ; --track origin/... au premier checkout.
git pull --ff-only
git merge origin/main # Récupérer le socle partagé dans sa branche, sans réécriture.
cd backend
composer install # Avec PHP 8.4 minimum sélectionné ; voir docs/COMMANDS.md.
composer test
```

Si le clone contient déjà des commits locaux supplémentaires, conserver ce travail et intégrer `origin/main` par un merge normal ; aucune réécriture forcée. Chaque poste configure sa propre base de test.

## Tâche 1 — socle et authentification

**Responsable :** `ousseynoufayeisidk-sys`. **Branche :** `backend/socle-auth`.

Livrer le socle Laravel/PostgreSQL reproductible, l'identité et les services partagés ; coordonner l'intégration et la réception backend.

- S01/S02 : inventaire effectif, versions et contraintes, commandes de travail et organisation. Ne pas déclarer S01 terminé avec cette seule répartition.
- B01–B05 : squelette préservant `backend/AGENTS.md`, configuration d'exemple sans secret, qualité PHP, CI PostgreSQL, erreurs HTTP, schémas OpenAPI communs, User/Profile/Technology et enums d'identité.
- B06–B10 : inscription, session Sanctum/CSRF/CORS, courriels de compte, droits et `/me`, profils et technologies.
- B12–B13 : audit transactionnel et idempotence réutilisables, avec tests de concurrence PostgreSQL.
- B29–B32 : notifications dédupliquées, signalements, retrait de contenu, suspension et rôles. Chaque pilote métier fournit les règles de visibilité de son domaine.
- B39–B44 : coordonner sécurité, contrat complet, recette, exploitation, Qodana selon accès réel, puis BACKEND_GATE. Les trois personnes fournissent leurs preuves et corrigent leurs domaines.

**B01–B04 intégrés ; prochaine PR :** B05, puis B06–B10 par PR cohérentes. B05 doit être intégré avant la fusion des PR métier qui consomment les modèles d'identité et référentiels. Leur préparation indépendante peut avancer dès maintenant. La fusion de ce socle n'est pas un BACKEND_GATE.

**Sortie attendue :** installation reproductible ; commandes locales documentées ; résultats de CI réellement observés ; contrats d'identité/audit/idempotence utilisables par les autres. Aucun rôle d'admin ne remplace l'auteur d'une demande. Pas de jeton navigateur dans localStorage.

## Tâche 2 — communauté et entraide

**Responsable :** `LamineGL`. **Branche :** `backend/communaute-entraide`.

Livrer les échanges entre développeurs et le parcours consenti qui ouvre une collaboration.

- B11 : schéma des demandes/commentaires/propositions/résolutions, contraintes et modèles du domaine.
- B14–B21 : création, lecture, recherche, édition, commentaires, propositions, acceptation par auteur, réouverture et archivage.
- BC01–BC08 : projets, publication/catalogue, liens projet-demande, visibilité, annuaire volontaire, `ask_question` sans code obligatoire et recette communauté.
- BH01–BH10 : ouverture volontaire, découverte des occasions, offres privées, consentements, acceptation avec création/rattachement de fil, refus/retrait/expiration, projection publique, contrat et recette transactionnelle.

**Première PR de code :** préparer B11 dès maintenant, puis le finaliser et le fusionner après intégration de B01–B05. Commencer les éléments indépendants décrits plus haut, les contrats, données fictives et cas négatifs ; ne pas créer un second squelette Laravel.

**Dépendances à intégrer avant usage :** B06–B09 pour l'identité et les actions authentifiées ; B12–B13 pour audit/rejeu ; B29 pour BH05 ; B32 pour BH07 ; B30 pour BH08. BC07 attend l'enum HelpIntent de BV201, puis BH05 attend BC07. Conserver aussi toutes les dépendances BH présentes dans `tasks.json`.

**Sortie attendue :** AC53–64 et AC69–87 applicables au serveur couverts avec preuves réelles, non-régression des demandes et résolutions, tests de concurrence et d'absence de fuite. Offre acceptée signifie collaboration commencée, jamais travail terminé. Aucun nouveau chat, équipe ou accès au dépôt.

## Tâche 3 — capsules et laboratoire

**Responsable :** `mdev44-code`. **Branche :** `backend/capsules-laboratoire`.

Livrer la conservation des solutions et leurs vérifications honnêtes, sur des scénarios approuvés.

- B22–B28 : capsules, brouillons, revue indépendante, publication immuable, catalogue, kit contrôlé, favoris et retours de réutilisation.
- B33–B38 : registre approuvé, lancement et quotas, B1, worker restreint, rapports réels, reprises après panne et API B2 isolée. Aucun composant React B2 avant GO_FRONTEND.
- BV201–BV210 : cas documentaires révisés, profils approuvés, comparaison baseline/candidate, deux runs frais, quotas partagés, conclusions mitigées/régressions visibles, fiche de vérification et recette atelier.

**Première PR de code :** préparer B22 dès maintenant ; sa finalisation et sa fusion attendent le socle B01–B05 et le schéma B11 référencé. Commencer les éléments indépendants décrits plus haut, le contrat capsules et les scénarios fictifs B1 dans les fichiers de ce domaine. Les écritures utilisant identité/audit/rejeu attendent aussi les contrats B06–B09/B12–B13 ; les parcours issus d'une résolution attendent B18–B19.

**Point de coordination :** BV201 pilote aussi l'ajout initial de HelpIntent. Faire relire cette partie par `LamineGL`, convenir d'un seul auteur pour les fichiers HelpRequests concernés et fusionner avant BC07. Ne pas créer deux enums ou validateurs concurrents.

**Sortie attendue :** revue par une autre personne habilitée, version publiée immuable, tests PostgreSQL et concurrence réelle, rapports liés à un commit et une exécution. Un seul run actif global ; deux enfants séquentiels par comparaison. Aucun code utilisateur, shell ou URL arbitraire exécuté. Aucun résultat complet après interruption.

## Fichiers réservés et points communs

Ces chemins définissent les responsabilités : B01 installe les fichiers du socle, les modules métier viennent ensuite. Consulter le suivi pour distinguer les conventions du code livré. Un changement de contrat partagé doit être discuté dans la PR avant que les autres branches ne l'utilisent.

| Zone | Responsable d'écriture |
|---|---|
| `backend/composer.json`, `composer.lock`, `bootstrap/`, `config/`, `.env.example`, `phpunit.xml`, configuration analyse/formatage, `.github/workflows/` | `ousseynoufayeisidk-sys` ; les autres demandent leurs dépendances dans une PR, sans modifier le lockfile en parallèle |
| `backend/routes/api.php`, enregistrement des routes/providers, schémas et erreurs HTTP communs | `ousseynoufayeisidk-sys` ; prévoir des fichiers de routes séparés par domaine |
| Identity, Audit, Idempotency, Notifications, Moderation ; `User.php`, `Profile.php`, `Technology.php` et leurs factories de base | `ousseynoufayeisidk-sys` ; coordonner les extensions annuaire/offres avec `LamineGL` |
| HelpRequests, Collaboration, Projects, HelpOffers, modèles/Policies/factories/tests correspondants | `LamineGL` ; exception BV201 coordonnée avant écriture |
| Capsules, Lab, VerificationCases, Comparisons, Evidence, modèles/Policies/factories/tests correspondants et modules B1/API B2 | `mdev44-code` |
| `backend/routes/api/identity.php`, `community.php`, `capsules-lab.php` | Respectivement les responsables 1, 2 et 3 ; le responsable 1 les enregistre une seule fois |
| `docs/OPENAPI.yaml` et composants communs | `ousseynoufayeisidk-sys` ; B04 fixe l'assemblage par références de fragments `docs/api/openapi/identity.yaml`, `community.yaml`, `capsules-lab.yaml`, détenus par leurs pilotes |
| Migrations | Chaque pilote ajoute les migrations de son domaine ; nom unique avec identifiant du lot, ordre des FK contrôlé à l'intégration ; aucune modification d'une migration déjà partagée pour masquer un conflit |
| `docs/execution/PROGRESS.md`, `HANDOFF.md`, `tasks.json`, `SHA256SUMS`, instructions racine et dictionnaire global | `ousseynoufayeisidk-sys` consolide les preuves ; un seul rédacteur à la fois |
| Suivi de branche | Chacun crée et met à jour `docs/execution/participants/<login>/PROGRESS.md` et `HANDOFF.md` après son petit lot ; ces fichiers satisfont l'obligation de suivi sur sa branche |

Chaque pilote possède ses tests Feature/Unit/Integration et sa base PostgreSQL locale de test dédiée. Ne jamais partager une base de tests entre les trois postes, ni pointer les scripts sur une base applicative ou de production. Garder `TestCase.php`, les fixtures globales et les seeders d'assemblage sous coordination du responsable 1.

Contrat partagé préparé en B04 : [HTTP_CONTRACT.md](../api/HTTP_CONTRACT.md). Réutiliser PaginatedRequest, PageData et PaginatedResourceCollection ; le renderer reste central. Chaque pilote écrit son fragment OpenAPI, puis le responsable du socle ajoute les références de chemins dans le point d'entrée lors de l'intégration. Les fichiers de fragments vides ne déclarent aucun endpoint disponible.

## Ordre d'intégration

1. Compléter S01/S02 ; B01 est intégré. Petites PR B02–B05 par le responsable 1 pendant que les autres codent les parties indépendantes de leurs domaines, préparent leurs contrats/tests et relisent le socle.
2. Les trois branches récupèrent ce `main`. Le responsable 1 poursuit identité, audit et idempotence ; le responsable 2 livre B11 ; le responsable 3 prépare les capsules, puis B22 dès que ses références existent. Une absence de `depends_on` dans un ancien lot ne prouve pas son indépendance.
3. Intégrer les services communs avant leurs consommateurs. Fusionner ensuite les lots métier prêts, sans attendre qu'une des trois tâches de coordination soit entièrement terminée. B18–B19 précèdent les parcours de capsule qui en dépendent.
4. Coordonner BV201 → BC07 → BH05, ainsi que B29/B30/B32 → les lots BH concernés. Les dépendances exactes BH de `tasks.json` restent obligatoires.
5. Recettes BC08, BV210 et BH10, puis audits/recette globale B39–B43 et B44 avec revue humaine. Chaque changement après un test impose de retester ce qui est affecté. GO_FRONTEND reste une décision humaine distincte.

Les identifiants et l'ordre de référence des 122 lots sont conservés. Le travail préparatoire peut avancer en parallèle ; une PR dépendante ne fusionne qu'après ses prérequis. Le plan répartit les responsabilités, il ne certifie aucune fonctionnalité livrée.

## Travailler sur sa branche

Chacun utilise son propre clone du dépôt. Exemple pour `LamineGL`, après création des branches distantes :

```powershell
git clone https://github.com/haas-projet/haas.git
cd haas
git fetch origin
git switch --track origin/backend/communaute-entraide
```

Avant un lot et avant la revue, avec un répertoire propre (committer explicitement son travail utile avant de synchroniser) :

```powershell
git status --short
git fetch origin
git merge origin/main
```

Suivre [COMMANDS.md](../COMMANDS.md) après intégration du socle B01 : `composer install`, `composer test`, `composer test:integration`, avec une base PostgreSQL de test dédiée et identifiée. B02 ajoute `composer lint`, `composer analyse` et le contrôle d'architecture ; récupérer ce lot après sa revue et sa fusion. Mettre dans la PR les commandes exactes, résultats, SHA et contrôles non exécutés.

Ajouter uniquement les fichiers du lot, committer selon `COMMIT_CONVENTION.md`, pousser sa branche et ouvrir une PR vers `main` liée à sa tâche. Garder la tâche de coordination ouverte tant que tous ses lots ne sont pas vérifiés ; utiliser `Refs #numéro` pour une livraison partielle, pas `Closes`.

## Fusion et conflits

- L'auteur demande la revue à une autre personne. Les changements de contrat partagé requièrent aussi l'avis du domaine consommateur. Aucune auto-approbation ni revue d'agent présentée comme humaine.
- Synchroniser la branche avec le dernier `origin/main`, régler les conflits ensemble si nécessaire, puis relancer les contrôles concernés et vérifier la CI du dernier SHA.
- Fusionner une PR prête à la fois, après revue humaine et contrôles réels. Utiliser un commit de merge pour préserver les petits commits ; pas de force-push, pas de rebase d'une branche partagée ni de contournement d'un contrôle.
- La PR suivante récupère le nouveau `main` avant sa propre fusion, même si les tests passaient auparavant. Refaire les tests d'intégration PostgreSQL : un diff sans conflit peut néanmoins casser le comportement ou l'ordre des migrations.
- Après fusion, chacun synchronise sa branche avant d'y commencer un autre lot. La personne qui intègre consolide PROGRESS/HANDOFF, statuts et empreintes, sans déclarer terminés les lots des autres sans preuve.

Cette organisation réduit les conflits ; elle ne garantit pas une fusion sans problème. À sa création, `main` n'avait aucune protection de branche et aucune CI applicative. Ce document et le modèle de PR sont des règles d'équipe, pas des protections GitHub activées. B03 doit fournir la CI avant de prétendre qu'elle bloque une régression.
