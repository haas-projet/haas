# B08 — Courriels de compte

Contrat OpenAPI 0.6.0. Aucun frontend ni service SMTP réel activé par ce lot. Réutilise les sessions et la protection CSRF/CORS de [B07](SESSIONS.md).

## Parcours HTTP

| Opération | Authentification / entrée | Résultat |
|---|---|---|
| POST /forgot-password | Session CSRF anonyme ou membre ; email uniquement | 202, même message pour compte actif, absent, suspendu ou demande récente |
| POST /reset-password | CSRF ; email, token, password, password_confirmation | 200 après consommation du token ; 422 générique sur token absent, expiré, remplacé, consommé ou compte inéligible |
| POST /email/verification-notification | Session du membre actif + CSRF ; aucun champ | 202 ; rien à envoyer si déjà vérifié ; 429 après une demande/minute ou six/heure |
| GET /email/verify/{id}/{hash}?expires=…&signature=… | Session du destinataire actif et URL signée | 200 avec id/handle/email_verified ; 403 si signature/expiration/propriétaire/courriel invalide |

Les erreurs suivent B04, les réponses sont no-store et comportent X-Request-ID. Aucun paramètre de redirection ni champ serveur accepté. CSRF est réellement exigé sur les trois POST ; la vérification GET utilise la signature Laravel et la session du destinataire. Cette seule navigation GET accepte un Referer de client mail externe si Origin est absent ; sa signature expirante reste obligatoire. Un Origin fourni doit toujours être HAAS. Un lien ouvert sans connexion renvoie 401 JSON : le futur client devra permettre de se connecter puis de reprendre ce lien, sans changer son hôte ni ses paramètres. Aucun écran livré ici.

L'inscription B06 met désormais en file un premier courriel de vérification dans la transaction compte/profil/conditions. Elle reste sans connexion automatique. Une nouvelle demande de vérification ne modifie pas le courriel ; l'ancien lien garde son échéance d'une heure. Rejouer un lien de vérification valide déjà utilisé est idempotent : même 200, horodatage inchangé, aucun nouvel événement Verified.

Le middleware `verified` refuse les actions protégées d'un compte non vérifié avec 403 JSON, sans redirection HTML. Il est vérifié sur une route de publication de fixture, jamais enregistrée hors tests. Les endpoints métier futurs devront conserver `auth:sanctum`, `verified` et leurs Policies B09/domaines ; ce test n'invente pas de fonctionnalité de publication.

## Réinitialisation et concurrence

Le broker Laravel `users` utilise password_reset_tokens : token aléatoire, hash Argon2id en base, validité 60 minutes, renouvellement après 60 secondes. Le résultat interne du broker n'est jamais retourné par forgot-password. Les deux commandes publiques ont chacune cinq tentatives/minute par courriel normalisé/IP et vingt par IP ; HMAC pour les clés contenant le courriel. Le timebox du broker réduit certaines différences de durée sans garantir l'indistinguabilité temporelle sous toute charge.

Émission et consommation verrouillent la ligne utilisateur dans une transaction. Deux consommations simultanées ne réussissent pas toutes deux. Le remplacement Argon2id du mot de passe, la rotation du remember_token, la suppression des sessions et la consommation du token sont atomiques ; un échec annule tout. Le mot de passe conserve les espaces et les 12–128 caractères Unicode. Un compte bcrypt historique peut ainsi récupérer un secret compatible. Le reset ne vérifie pas implicitement le courriel et ne change aucun rôle/statut.

Après succès, le navigateur courant est déconnecté et son CSRF renouvelé. Les sessions web et API vérifient l'empreinte du mot de passe, enregistrée dès la connexion : une session écrite tardivement après un reset ne peut pas rétablir l'accès. Les événements Verified/PasswordReset sont émis après commit ; les commandes de modération globales restent B32. La signature/expiration est validée à la frontière HTTP, les droits et le courriel courant sont recontrôlés après verrou dans le service.

## Construction des liens et transport

La vérification pointe vers l'APP_URL API exacte et conserve l'hôte signé. Le Host de la requête ne sert pas à choisir la destination. Le reset pointe vers FRONTEND_URL + `/reset-password#token=…&email=…` : le fragment évite l'envoi de ces données dans la requête au serveur frontend ou le Referer. Le futur écran devra le lire en mémoire puis le retirer de la barre d'adresse, sans localStorage, analytique ni journalisation. Aucun `redirect`, `return_url` ou hôte fourni par le client n'est utilisé.

MAIL_MAILER=array par défaut local/test : transport mémoire sans livraison extérieure. `log` est refusé pour ne pas écrire les liens secrets. En production, SMTP est requis ; les origines HTTPS et le domaine des cookies sont validés par B07. Les identifiants SMTP restent hors Git. Aucun test array, rendu de courriel ou mail fake ne valide la délivrabilité réelle.

Les notifications sont chiffrées avec APP_KEY dans la file Laravel SQL `account-mail`, sur la même connexion que les comptes. L'insertion du job a lieu dans la transaction, sa visibilité par un autre processus commence après commit ; un rollback ne laisse pas de job. Le transport mail n'est donc pas appelé pendant l'écriture du compte. Les payloads des jobs/failed_jobs sont chiffrés, le token SQL est haché ; protéger APP_KEY et les sauvegardes.

Worker distinct du laboratoire, à configurer lors du déploiement autorisé :

```sh
php artisan queue:work account-mail --queue=account-mail --tries=3 --timeout=30
php artisan auth:clear-resets users
```

Trois tentatives, pauses 30/120 secondes, réservation 90 secondes. Avant envoi, le worker recontrôle statut, courriel et échéance ; un token reset remplacé/consommé n'est plus envoyé. Un retry ne prolonge pas l'échéance. Un crash après acceptation SMTP peut produire un doublon avec le même lien : aucune garantie « exactement une fois » ; les consommations restent sûres. Les échecs finaux sont inspectés/repris avec les commandes queue Laravel. Nettoyer régulièrement les tokens expirés avec auth:clear-resets ; ne jamais copier un lien ou payload déchiffré dans les logs, tickets ou captures. Les accès HTTP signés nécessiteront une journalisation serveur sans query string en production.

Sources du mécanisme : [vérification Laravel 13](https://laravel.com/framework/docs/13.x/verification), [broker de reset](https://laravel.com/framework/docs/13.x/passwords), [notifications en file](https://laravel.com/framework/docs/13.x/notifications). Versions verrouillées inchangées ; aucune dépendance ajoutée.
