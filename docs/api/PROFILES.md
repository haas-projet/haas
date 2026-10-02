# B10 — Profils et technologies

Contrat OpenAPI 0.8.0. Les profils réutilisent User, Profile et Technology de B05. Aucun annuaire, préférence d'aide, upload de photo ou récupération d'URL externe n'est créé par ce lot. Les contributions réelles restent à raccorder : B10 conserve le statut IN_PROGRESS.

## Routes livrées

| Route | Accès et résultat |
|---|---|
| GET /api/v1/members/{handle} | Public, pseudonyme exact insensible à la casse SQL ; propriétaire actif et vérifié, sinon 404 identique à un profil absent. |
| GET /api/v1/me/profile | Session active, même non vérifiée ; données du profil propre, lock_version et can_update. |
| PATCH /api/v1/me/profile | Propriétaire actif et vérifié, CSRF réel ; données et technologies changées atomiquement. |
| GET /api/v1/technologies | Référentiel public paginé, 20 par défaut, 50 maximum, tri slug puis UUID. |

Les deux lectures de profil refusent tout paramètre supplémentaire. Aucun endpoint ne sélectionne le propriétaire depuis user_id/author_id fourni par le client. La route publique n'est pas une liste de membres ; elle n'active pas la découverte volontaire BC06. Un admin n'a pas de chemin alternatif pour réécrire le profil d'un autre membre.

## Projections et confidentialité

PublicProfileResource contient seulement id, handle, avatar_initials, bio, country, primary_language, github_url, technologies (id/slug/name), is_demo et contributions. Courriel, rôle, statut administratif, date de vérification, IP, sessions, tokens, hash et lock_version restent absents. MeResource B09 demeure séparée. OwnProfileResource ajoute seulement lock_version et can_update aux champs du profil.

L'avatar est constitué d'une ou deux initiales Unicode du pseudonyme, ou « ? » sans lettre/chiffre. Aucune image externe. Les textes sont du texte brut JSON : le futur frontend devra les échapper, sans interprétation HTML. Les données de démonstration conservent leur étiquette is_demo, fixée par le serveur.

Réponses de profil no-store, sans cache de projection à invalider. Un compte suspendu n'est plus lisible par URL directe, y compris par un admin via cette API publique. La lecture ne crée aucun profil en base : un ancien compte sans profil reçoit les valeurs initiales et une version zéro pour sa future modification.

## Modification partielle et concurrence

Entrée : lock_version entier JSON obligatoire, entre 0 et 2147483646, et au moins un champ modifiable. Une valeur omise est conservée.

| Champ | Validation |
|---|---|
| bio | Texte, 500 caractères Unicode maximum ; vide/null efface ; contrôles interdits sauf tabulation et retours de ligne. |
| country | Facultatif, texte 100 caractères maximum ; null efface. Aucun filtre pays ni géolocalisation. |
| primary_language | Code projet de 2–3 lettres, sous-tags alphanumériques de 2–8 caractères facultatifs, total 35 maximum ; exemples fr, wo, pt-BR. Pas une implémentation exhaustive de BCP 47. |
| github_url | Facultatif, 2048 caractères maximum, https://github.com uniquement, sans identifiants, port, paramètres ni fragment ; null efface. Un chemin de dépôt est accepté. Aucun contrôle d'existence, de propriété ou d'expertise. |
| technology_ids | Liste de 0–8 UUID existants distincts, casse normalisée ; remplace toute la sélection, liste vide efface. |

Les autres champs, même nulls, sont rejetés : handle, email, rôle, user_id, avatar_url, contributions, directory_visible, etc. Les préférences de BC06/BH seront ajoutées explicitement au contrat, pas acceptées silencieusement.

UpdateProfileRequest → UpdateProfileData readonly → UpdateProfileService → OwnProfileResource. Le service verrouille le compte puis le profil, recharge actif/vérifié et réapplique la Policy. Les technologies sont relues sous verrous partagés triés ; la version et le pivot changent dans la même transaction. Deux clients partant de la même version ne peuvent pas mélanger leurs textes et sélections : un seul réussit, l'autre reçoit 409 RESOURCE_CONFLICT, sans mutation. Recharger et décider ; aucun rejeu automatique qui écrase la nouvelle version.

Technologie supprimée/inconnue : 422 sur technology_ids ; invité : 401 ; non vérifié/action interdite : 403 ; CSRF absent : 419 ; champ invalide : 422. Une panne SQL provoque rollback complet, erreur 500 neutre et exception sans bindings ni texte de profil. L'audit partagé sera raccordé avec B12, pas simulé par un journal de requêtes.

## Reprise des données existantes

Migration additive B10 : lock_version=0 sur les profils existants et CHECK SQL non négatif. Précontrôle des associations existantes sous verrou : plus de huit technologies arrête la migration sans supprimer de choix ; corriger explicitement après examen. Aucune migration B05/B06 n'est modifiée. Rollback B10 limité à sa colonne, testé uniquement sur PostgreSQL jetable.

Un ancien lien GitHub hors contrat est rendu null, sans le suivre ni le réécrire en base. Le membre pourra le remplacer explicitement. Le référentiel technologies reste constitué de lignes réelles en SQL ; un référentiel vide donne une liste vide, pas des technologies fictives. Son alimentation sera livrée par les données de recette, sans écriture implicite sur une base applicative pendant B10.

## Travail restant et coordination

`contributions: null` signifie **données indisponibles**, jamais zéro contributions ni absence d'activité. Ne pas afficher de compteur ou de badge à partir de cette valeur. Les sources demandes/résolutions, capsules publiées et fiches d'attribution (notamment B19/B25/BV209) ne sont pas encore livrées dans le socle. Leurs propriétaires devront fournir des lectures filtrées par visibilité et état ; ensuite raccorder les liens, rôles et compteurs réels, retirer les validations devenues invisibles et séparer démonstration/usage réel. Aucun modèle/table du domaine d'un collègue n'est inventé pour débloquer artificiellement ces compteurs.

Ce raccordement fait toujours partie de B10 : ne pas passer la carte Systalink à Terminé après la seule livraison de ces endpoints. Ajouter ses preuves avant la réception du lot et avant B39/B44. La PR de cette étape peut être relue séparément ; les PR #10/#11/#13 doivent être intégrées avant fusion. Prochaine partie indépendante du responsable socle : B12, audit et révisions.
