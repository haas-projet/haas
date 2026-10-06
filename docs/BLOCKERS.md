# Décisions à compléter avant livraison
- B06 : texte des conditions d'utilisation et version publiée à fournir avant ouverture réelle de l'inscription. `REGISTRATION_TERMS_VERSION` reste vide par défaut (503) ; les versions de test ne valent pas validation. Voir `api/REGISTRATION.md`.
- ARB01 : date limite réelle, sujet complémentaire et modalités de dépôt. Les dates 25/31 octobre du dossier restent non réconciliées.
- ARB02/03/05 : cession, licences/outils, distribution des kits et droits des contributions externes.
- ARB04 : Ultimate est déclaré disponible ; reste projet Qodana/token/contributeurs, forfait GitHub et capacité CI.
- ARB06 : disponibilité des trois membres et budget ; répartition backend confirmée dans `execution/BACKEND_A_TROIS.md`.
- DEP : domaine contrôlé ; offre et panier Datacloud ; région ; remise/taxes ; plan Vercel compatible avec dépôt et auteurs ; SMTP ; sécurité runner ; DNS/TLS/cookies à tester.
Aucune de ces données n’est inventée par Codex. Un compte fournisseur non inspecté reste NON VÉRIFIÉ ; cela ne remet pas en cause la déclaration de l’utilisateur.

## B10 — raccordement métier des contributions

Profils publics, édition et référentiel technologies développés dans la branche backend/socle-auth-profiles. Les compteurs/liens réels ne peuvent pas encore lire les domaines demandes/résolutions, capsules publiées et fiches d'attribution, absents de ce socle (notamment B19/B25/BV209). `contributions: null` indique cette indisponibilité sans fabriquer zéro ni un score. B10 reste IN_PROGRESS même après tests de cette première partie ; compléter les sources avec leurs pilotes et les contrôles de retrait/visibilité/démonstration avant réception. Voir [PROFILES.md](api/PROFILES.md). B12 peut avancer indépendamment.

## B01 — réserves constatées le 1er octobre 2026

- Socle testé localement sous PHP 8.5.10 et PostgreSQL 17.0. Réserve PHP minimale levée par B03 : CI réellement réussie sur PHP 8.4.26 et 8.5.11 avec PostgreSQL 17 dédié, voir `quality/B03_CI.md`.
- B02 qualifie Composer 2.10.3 en copie temporaire sous PHP 8.5.10, sans dépréciation observée. L'installation globale 2.8.5 reste inchangée ; sélectionner une version adaptée sur chaque poste.
- Les accès/versions de l'hébergement, SMTP et Qodana ne sont pas vérifiés. La CI B03 a été exécutée, sans présumer sa protection obligatoire sur main. S01/S02 restent IN_PROGRESS ; les tests locaux et CI ne sont pas une validation de production.
- B01 est intégré par la PR #4 sur autorisation explicite de fusion donnée par l'utilisateur ; aucune revue GitHub par un autre développeur n'est attestée. L'authentification n'est pas encore livrée. Les PR métier qui dépendent des référentiels B05 ou de l'authentification attendent leur intégration.

## Réception du socle — constat du 3 octobre 2026

- B29 : l’outbox/boîte privée et le producteur profil.modéré fonctionnent ; événements proposition/acceptation/réouverture/revue/lab/offres/cas/comparaisons à raccorder après livraison par leurs pilotes.
- B30/B31 : adaptateur profil testé ; signalement/retrait des demandes/projets/offres, versions de capsules, recherche et anciens kits encore absents. Pas d’AC25 global reçu.
- B39/B40/B41 : tests, contrats et parcours du socle disponibles ; sécurité/contrat/recette F01–F18 impossibles à terminer avant les domaines des autres pilotes, BH10 compris.
- B42 : sauvegarde/restauration locale et santé testées ; hébergement et stockage distant non inspectés, supervision/runner/rollback réel non exécutés. Ce contrôle distant est bloqué, aucune topologie installée annoncée.
- B43 : aucun secret GitHub configuré au constat ; projet Qodana demandé, token à fournir uniquement via un environnement CI autorisé. Analyse et témoin de blocage NON EXÉCUTÉS, aucune dérogation humaine acquise.
- B44 : domaines P0 incomplets et revue humaine absente ; BACKEND_GATE NON REÇU, GO_FRONTEND NON. La CI verte du socle ne lève pas ce blocage.

Voir [les preuves](quality/SOCLE_RECEPTION_PARTIELLE.md) et [les cartes individuelles](execution/participants/ousseynoufayeisidk-sys/SYSTALINK_TASKS.md). Les PR #10/#11/#13–#20 ont été intégrées dans cet ordre sur demande explicite ; les lots partiels restent incomplets malgré la fusion de leur périmètre livré. Voir quality/MERGE_SOCLE.md. Aucun compte rendu de revue d’un collègue n’a été fabriqué.
