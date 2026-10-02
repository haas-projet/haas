# Décisions à compléter avant livraison
- ARB01 : date limite réelle, sujet complémentaire et modalités de dépôt. Les dates 25/31 octobre du dossier restent non réconciliées.
- ARB02/03/05 : cession, licences/outils, distribution des kits et droits des contributions externes.
- ARB04 : Ultimate est déclaré disponible ; reste projet Qodana/token/contributeurs, forfait GitHub et capacité CI.
- ARB06 : disponibilité des trois membres et budget ; répartition backend confirmée dans `execution/BACKEND_A_TROIS.md`.
- DEP : domaine contrôlé ; offre et panier Datacloud ; région ; remise/taxes ; plan Vercel compatible avec dépôt et auteurs ; SMTP ; sécurité runner ; DNS/TLS/cookies à tester.
Aucune de ces données n’est inventée par Codex. Un compte fournisseur non inspecté reste NON VÉRIFIÉ ; cela ne remet pas en cause la déclaration de l’utilisateur.

## B01 — réserves constatées le 1er octobre 2026

- Socle testé localement sous PHP 8.5.10 et PostgreSQL 17.0. Réserve PHP minimale levée par B03 : CI réellement réussie sur PHP 8.4.26 et 8.5.11 avec PostgreSQL 17 dédié, voir `quality/B03_CI.md`.
- B02 qualifie Composer 2.10.3 en copie temporaire sous PHP 8.5.10, sans dépréciation observée. L'installation globale 2.8.5 reste inchangée ; sélectionner une version adaptée sur chaque poste.
- Les accès/versions de l'hébergement, SMTP et Qodana ne sont pas vérifiés. La CI B03 a été exécutée, sans présumer sa protection obligatoire sur main. S01/S02 restent IN_PROGRESS ; les tests locaux et CI ne sont pas une validation de production.
- B01 est intégré par la PR #4 sur autorisation explicite de fusion donnée par l'utilisateur ; aucune revue GitHub par un autre développeur n'est attestée. L'authentification n'est pas encore livrée. Les PR métier qui dépendent des référentiels B05 ou de l'authentification attendent leur intégration.
