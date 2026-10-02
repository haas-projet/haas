# Identité partagée — B05

Ces modèles sont communs aux trois pilotes. Ils ne constituent pas encore une API d'inscription, de profil ou d'annuaire. Ne pas recréer User, Profile ou Technology dans un domaine.

| Table / champ | Convention implémentée |
|---|---|
| users.id | UUID serveur, non assignable depuis des entrées client |
| users.handle | Pseudonyme de 3–30 caractères Unicode, casse d'affichage conservée, espaces périphériques retirés ; index unique PostgreSQL sur lower(handle), aucun caractère de contrôle |
| users.email | Privé ; trim et minuscules à l'affectation Eloquent ; stockage canonique contrôlé par SQL lower/btrim, index unique existant conservé, aucun espace interne |
| users.role | Enum PHP Role : member / moderator / admin ; CHECK PostgreSQL, défaut member |
| users.status | Enum PHP AccountStatus : active / suspended ; CHECK PostgreSQL, défaut active |
| users.email_verified_at | Champ physique Laravel correspondant au **verified_at conceptuel** du cahier ; nullable, cast immutable_datetime, null par défaut |
| users.is_demo | Booléen serveur NOT NULL, false par défaut ; aucune attribution client |
| profiles.user_id | UUID, clé primaire et FK users : un profil au plus par compte |
| profiles | bio max500, country nullable max100, primary_language non vide max35 (fr par défaut), github_url nullable max2048 ; timestamps |
| technologies | UUID, slug unique en minuscules de 1–64 caractères (lettres ASCII/chiffres/tirets séparateurs), name non vide max80, timestamps |
| user_technologies | Paire user_id/technology_id unique, FK ; index inverse pour les lectures par technologie |

Le rôle ne confère aucun droit d'auteur ni bypass global. Les contrôles actif/vérifié et les Policies arrivent en B09. Les validations complètes du profil, URL GitHub HTTPS et limite de huit technologies arrivent en B10. L'annuaire volontaire et les préférences d'aide relèvent de BC06/BH ; aucun profil n'est rendu visible automatiquement par B05.

## Confidentialité et écritures

User n'autorise en affectation de masse que handle/email/password. Un champ non autorisé provoque désormais une MassAssignmentException, dans tous les environnements, au lieu d'être ignoré silencieusement. Le FormRequest B06 devra rejeter les clés serveur même nulles et utiliser les seules entrées validées ; ce réglage Eloquent ne remplace pas la validation HTTP ni une Policy. Un Service de confiance peut affecter explicitement un attribut serveur après ses contrôles. Les factories restent des outils de fixtures, jamais des entrées HTTP.

La sérialisation brute de User masque email/password/remember_token/role/status/email_verified_at/is_demo et l'ancien name. Les Resources publiques des prochains lots doivent conserver une liste blanche explicite ; MeResource sera distincte. Aucun endpoint de compte n'est annoncé dans OpenAPI à ce stade, seuls les types communs sont référencés.

La vérification utilise le contrat Laravel MustVerifyEmail et email_verified_at, sans seconde colonne verified_at. B05 ne livre ni lien signé, ni envoi réel de courriel, ni session. Les comptes des factories sont non vérifiés par défaut ; employer explicitement verified(), suspended(), moderator(), admin() ou demo() lorsque le scénario le nécessite. Profile::factory()->for($user) et Technology::factory() évitent des dépendances aux fixtures d'un autre domaine. La création du profil réel sera explicite dans le workflow d'inscription, sans observer caché.

Le référentiel technologies est une table évolutive, sans liste de valeurs codée dans une enum. Une technologie utilisée ne peut pas être supprimée ; ses associations doivent être traitées explicitement. Supprimer un utilisateur supprime son profil et son pivot de technologies, sans supprimer les technologies elles-mêmes. Cela ne définit pas la politique de conservation des futures contributions.

## Reprise depuis le socle B01

La migration `2026_10_02_000005_b05_create_identity_and_reference_tables` est nouvelle ; aucune migration déjà partagée n'est réécrite. Elle verrouille users dans sa transaction PostgreSQL, vérifie les longueurs et doublons des noms/courriels normalisés, puis reprend name comme handle et normalise email. Un doublon ou une identité invalide arrête la migration avec un message sans donnée personnelle. Aucun suffixe ni nouveau pseudonyme n'est attribué automatiquement. Les UUID, mots de passe et anciens noms sont conservés.

L'ancien users.name reste nullable, masqué et non assignable, pour préserver les valeurs déjà présentes. Le code neuf utilise handle. Le retour arrière, testé uniquement sur une base jetable, restitue name=handle pour les comptes créés après B05 ; il conserve les anciens name et le courriel déjà normalisé. Les nouvelles tables de B05 sont supprimées par ce rollback : ce n'est pas un mécanisme de sauvegarde et il n'est pas exécuté sur une base applicative.

La casse et l'unicité SQL reposent sur lower() et la collation PostgreSQL configurée ; aucune équivalence Gmail (points/alias), translittération ou équivalence d'homographes Unicode n'est inventée. L'environnement de production et sa collation restent à vérifier avant déploiement.

Références : [casts et mutateurs Laravel 13](https://laravel.com/docs/13.x/eloquent-mutators), [contraintes PostgreSQL 17](https://www.postgresql.org/docs/17/ddl-constraints.html), [index d'expression PostgreSQL](https://www.postgresql.org/docs/17/indexes-expressional.html).
