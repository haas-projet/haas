# Suivi à rapprocher du dépôt réel

**Statut :** pack de conception actualisé ; aucun lot applicatif exécuté ici. 122 lots proposés, 106 identifiants antérieurs conservés. Ajouts BH01–10/FH01–05/RH01, F18, AC69–90 et UX21–23. Ne pas écraser les statuts ou preuves du dépôt réel.

À renseigner après commandes réelles : date / lot / responsable / fichiers / commandes / observé / commit ou raison de non-commit / blocage / prochaine action. Les contrôles documentaires de ce pack ne valident pas BACKEND_GATE, FRONTEND_GATE ou RELEASE_GATE.

## 2026-10-01 — Préparation du premier envoi GitHub

- Demande utilisateur : pousser le dossier vers `https://github.com/haas-projet/haas.git` ; intervention Codex, sans revue humaine simulée.
- État initial : aucun dépôt Git local ; dépôt distant privé, vide et accessible en écriture. Identité Git existante conservée.
- Fichiers : import du pack existant, ajout de `.gitignore` et `.gitattributes`, correction du statut attendu dans `scripts/check-deployment-docs.mjs` pour ADR-006, rapport associé et empreintes actualisés.
- Contrôles exécutés : `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7 après correction de l'ancienne attente), `node scripts/check-design-contrast.mjs` (22/22).
- Intégrité initiale : les 109 empreintes de `SHA256SUMS` correspondent aux fichiers reçus. Recherche de formats usuels de secrets sans correspondance ; les configurations livrées sont des exemples sans identifiants secrets.
- Revue de l'import : `git diff --cached --check` signale dix fins de ligne Markdown à deux espaces et deux lignes vides finales déjà présentes dans le pack ; contenu source conservé. Aucun autre problème détecté par le même contrôle avec `core.whitespace=-blank-at-eol,-blank-at-eof`.
- Commit initial : référence réelle à communiquer dans le bilan après création ; branche `main` destinée au dépôt demandé.
- Limites : aucun test applicatif, CI, contrôle Qodana, rendu visuel ou déploiement exécuté pendant cette intervention. Aucun lot applicatif ni gate déclaré terminé.
- Prochaine action de développement : S01 (inventaire complet de l'environnement), puis S02 et backend selon les dépendances.

## 2026-10-01 — Répartition backend entre trois développeurs

- Demande utilisateur : trois tâches backend, une branche par personne et des fusions coordonnées. Attribution explicitement confirmée : `ousseynoufayeisidk-sys`, `LamineGL`, `mdev44-code`.
- Point de départ : `8e1c4b9` synchronisé avec `origin/main`, répertoire propre, aucun backend applicatif. Trois collaborateurs constatés sur GitHub ; aucune tâche ni branche de travail préexistante.
- Livrables : `BACKEND_A_TROIS.md` (responsabilités, 72 lots, frontières, prérequis, petites PR), modèle de PR, mentions d'équipe actualisées dans les consignes/plan/architecture. Sources et livrables historiques conservés.
- Tâches GitHub créées et attribuées : #1 socle/authentification/intégration, #2 communauté/entraide, #3 capsules/laboratoire. Branches prévues depuis le même commit de planification : `backend/socle-auth`, `backend/communaute-entraide`, `backend/capsules-laboratoire`.
- Contrôle de répartition exécuté : 22 + 27 + 23 = 72 lots backend, aucun doublon ni omission. Comparaison avec `HEAD:docs/execution/tasks.json` : les 122 identifiants, statuts, ordres et dépendances sont préservés.
- Vérifications exécutées : `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7), `git diff --check` (sans erreur). Empreintes actualisées avant le commit. Aucun test applicatif, CI ou Qodana exécuté pour cette organisation.
- Limites : aucune promesse de fusion automatique, aucune protection de branche activée, aucun lot applicatif déclaré terminé. La revue humaine de chaque PR reste requise par le processus d'équipe.
- Prochain travail : S01/S02 puis B01–B05 par le responsable du socle ; les autres préparent leurs contrats et relisent, puis synchronisent leur branche avant les lots métier dépendants.

## 2026-10-01 — Arborescence backend commune

- Demande utilisateur : créer d'abord les dossiers de base afin que les trois personnes disposent de la même structure. Périmètre retenu : dossiers versionnés, sans installation Laravel ni fonctionnalité métier.
- Point de départ : `7805723`, répertoire propre ; `main` et les trois branches distantes portent ce même commit, aucune PR ouverte constatée avant intervention.
- Livrables : 26 dossiers repères dans `backend/` avec `.gitkeep`, et `backend/README.md` décrivant les couches, les responsabilités et l'initialisation future. `backend/AGENTS.md` conservé sans modification.
- Inventaire local partiel : PHP CLI 8.3.12 avec pdo_pgsql, Composer 2.8.5, Node 24.19.0, npm 11.2.0 via `npm.cmd`, client PostgreSQL 17.0 et Git 2.45.1.windows.1. Le répertoire Laragon contient aussi une version PHP 8.5.10 ; elle n'a pas été sélectionnée ni testée. Ces observations ne valident pas les versions de l'hébergement ni la connexion à une base.
- Contrôles exécutés : présence des 26 fichiers `.gitkeep` vides, empreinte Git de `backend/AGENTS.md` inchangée, `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7), `git diff --check` sans erreur. Sélection explicite des nouveaux dossiers et actualisation des empreintes avant commit.
- Aucun test applicatif, CI, Qodana, installation de dépendance ou accès à une base exécuté. Tous les lots du plan, y compris B01, gardent leur statut antérieur.
- Prochaine étape : S01/S02 puis B01 sur `backend/socle-auth`. La publication de cette arborescence initiale commune ne remplace pas la revue humaine des futures PR applicatives.
