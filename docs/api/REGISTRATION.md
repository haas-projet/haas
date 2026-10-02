# B06 — Inscription

`POST /register`, corps JSON. Contrat détaillé dans [identity.yaml](openapi/identity.yaml), référencé par OpenAPI 0.4.0. Route dans le groupe web Laravel avec CSRF et limite de cinq tentatives par minute et adresse IP. B07 doit encore fournir le parcours SPA complet, les cookies Sanctum et CORS ; B08 les courriels de compte.

Champs exacts : `handle`, `email`, `password`, `password_confirmation`, `terms_accepted`, `terms_version`. Tout autre champ est refusé, même s'il vaut null ou vient de la query string. Création publique, sans ressource préexistante ni rôle à accorder par Policy. Le serveur fixe membre/actif/non vérifié/non démo ; aucune connexion automatique, aucun jeton, aucune notification envoyée par ce lot.

Le pseudonyme contient 3–30 caractères Unicode, sans contrôle ; les espaces périphériques sont retirés. Le courriel privé est validé et normalisé en minuscules. Les index PostgreSQL arbitrent les doublons, y compris deux créations simultanées ; erreur 422 générique sur handle/email, sans divulguer le compte existant ni la requête SQL. Cette réponse ne prétend pas empêcher l'énumération d'adresses ; la récupération de mot de passe B08 a son propre contrat neutre.

Le mot de passe contient 12–128 caractères Unicode, confirmation identique. Ses espaces ne sont pas retirés. Le hasher Laravel Argon2id utilise 64 Mio, quatre passes et un thread ; vérification de l'algorithme active. Le texte est toujours haché explicitement, même s'il ressemble à un hash. Aucun repli bcrypt ou préhachage artisanal. Le DTO protège le secret avec `SensitiveParameterValue`. Une panne SQL est transformée en exception diagnostique limitée au SQLSTATE, sans chaîner les bindings privés. Les logs applicatifs formatés sont vérifiés avec une panne réelle.

Ce choix évite la limite de 72 octets de bcrypt documentée par [PHP](https://www.php.net/manual/en/function.password-hash.php) ; le service utilise le [hasher Laravel](https://laravel.com/framework/docs/hashing). PHP doit offrir Argon2id. Aucun hash de compte existant n'est réécrit : avant ouverture de la connexion B07, traiter explicitement d'éventuels anciens hashes bcrypt par migration encadrée ou réinitialisation. Ne pas désactiver globalement la vérification de l'algorithme pour contourner ce point.

## Conditions versionnées

`REGISTRATION_TERMS_VERSION` désigne exactement la version d'un texte publié et validé par l'équipe. Identifiant de 1–64 caractères ASCII : lettre/chiffre initial, puis lettres/chiffres/points/tirets/underscores. La configuration est vide par défaut, faute de texte fourni ; l'inscription répond alors 503. Les versions `fixture-*` des tests sont fictives et ne sont pas des conditions approuvées.

Le client envoie cette version et le booléen JSON `terms_accepted: true` après consultation du texte. Une ancienne version, une case absente ou une valeur telle que `"true"`/`1` est refusée. Le service recontrôle la version actuelle avant écriture. Compte, profil vide et reçu `user_terms_acceptances(user_id, version, accepted_at)` sont créés dans une seule transaction. Acteur et date UTC sont fixés par le serveur ; aucun historique d'acceptation n'est inventé pour les anciens comptes.

La publication du texte et sa version restent à fournir avant ouverture réelle des inscriptions. Ce lot ne fournit pas de validation juridique ni d'écran frontend. Une version doit conserver le même texte ; une modification demande un nouvel identifiant et la conservation de l'ancien texte.

## Réponses

- 201 : `data.id`, `data.handle`, `data.email_verified: false`, `Cache-Control: no-store`, identifiant de requête. Aucun courriel, hash, rôle, session authentifiée ou reçu privé exposé.
- 422 : entrée invalide, champ interdit, conditions refusées/obsolètes ou doublon ; enveloppe B04 avec erreurs de champs en français.
- 419 : contrôle CSRF refusé ; 429 : quota de tentatives atteint, `Retry-After`.
- 503 : version des conditions absente/invalide ; 500 : panne inattendue, transaction annulée.

Les tests HTTP usuels désactivent le contrôle CSRF comme Laravel le fait en environnement testing. Un test réactive effectivement ce middleware et observe 419 sans jeton. Cela ne valide pas encore les échanges entre les deux origines de déploiement : recette B07/DEP-AC02 à suivre.
