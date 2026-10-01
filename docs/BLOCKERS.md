# Décisions à compléter avant livraison
- ARB01 : date limite réelle, sujet complémentaire et modalités de dépôt. Les dates 25/31 octobre du dossier restent non réconciliées.
- ARB02/03/05 : cession, licences/outils, distribution des kits et droits des contributions externes.
- ARB04 : Ultimate est déclaré disponible ; reste projet Qodana/token/contributeurs, forfait GitHub et capacité CI.
- ARB06 : disponibilité des trois membres et budget ; répartition backend confirmée dans `execution/BACKEND_A_TROIS.md`.
- DEP : domaine contrôlé ; offre et panier Datacloud ; région ; remise/taxes ; plan Vercel compatible avec dépôt et auteurs ; SMTP ; sécurité runner ; DNS/TLS/cookies à tester.
Aucune de ces données n’est inventée par Codex. Un compte fournisseur non inspecté reste NON VÉRIFIÉ ; cela ne remet pas en cause la déclaration de l’utilisateur.

## B01 — réserves constatées le 1er octobre 2026

- Socle testé sous PHP 8.5.10 et PostgreSQL 17.0, sur une base temporaire dédiée. La version minimale PHP 8.4 reste à exécuter dans la CI B03.
- Composer 2.8.5 fonctionne mais émet des dépréciations sous PHP 8.5 ; qualifier une version compatible en B02.
- Les accès/versions de l'hébergement, SMTP, Qodana et CI ne sont pas vérifiés. S01/S02 restent IN_PROGRESS ; cela ne transforme pas les tests locaux B01 en validation de production.
- La revue humaine et l'intégration de B01 restent attendues. L'authentification n'est pas encore livrée.
