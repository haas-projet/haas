# Exploitation du socle — B42, préparation locale

## État constaté

Le socle tourne et se teste localement avec PHP 8.5.10, PostgreSQL 17.0 et GitHub Actions PHP 8.4/8.5. Aucun VPS, domaine, stockage distant, SMTP réel ou compte du runner n'a été configuré par cette livraison. Le script de sauvegarde ci-dessous refuse les bases autres que des bases locales de test. Il ne constitue pas un script de production homologué. ADR-004 reste la cible : un VPS Systalink, PostgreSQL local, runner séparé restreint, frontend Vercel après GO_FRONTEND.

## Processus et configuration à recevoir sur le serveur réel

- API/PHP-FPM : compte applicatif, racine web backend/public seulement, APP_DEBUG=false, configuration CORS/session exacte selon AUTH_CORS_SANCTUM.md, conditions d'inscription versionnées, clé applicative et SMTP privés.
- Worker de courriels : même release et configuration métier autorisée, queue account-mail, traitement supervisé avec les délais/essais définis dans ACCOUNT_MAIL.md. Aucun lancement de queue lab depuis ce processus.
- Scheduler : un processus contrôlé invoquant `php artisan schedule:run` chaque minute. B29 livre jusqu'à 100 notifications par minute ; B13 purge jusqu'à 1000 intentions expirées toutes les cinq minutes. Les verrous withoutOverlapping nécessitent un cache partagé et fiable sur le VPS. Après incident, contrôler le backlog avant toute suppression d'un verrou.
- Runner laboratoire : identité/configuration séparées sans APP_KEY, SMTP ou rôle SQL métier ; artefact approuvé et canal local contrôlé. Ce runner n'est pas fourni par le socle ; ses restrictions attendent B33–B38/BV et des tests sur l'OS retenu.
- Sauvegarde distante : clé de chiffrement distincte d'APP_KEY et stockée séparément de l'archive ; accès restreint, rétention et restauration vérifiées. APP_KEY doit être conservée séparément pour lire les motifs/jobs applicatifs déjà chiffrés. Aucun fournisseur ni plan supplémentaire n'est présumé disponible.

Les chemins, unités systemd, rôles non privilégiés, quotas, DNS/TLS et capacités sont à fixer à partir du serveur réellement reçu. Aucune configuration fictive n'est présentée comme installée. La clé, les fichiers d'environnement, le mot de passe SQL et les jetons restent hors dépôt et hors logs.

## Santé et reprise

`GET /up` reste une sonde minimale sans SQL. Depuis une console d'exploitation autorisée, `php artisan ops:check` vérifie SQL et affiche seulement état, nombre de jobs échoués, intentions de notification de plus de cinq minutes et courriels disponibles depuis plus de cinq minutes. Code 0 si ces contrôles passent ; code 1 en cas de panne/backlog. Aucun nom de compte, payload, hôte, courriel ou secret dans sa sortie. Ce contrôle n'atteste pas la délivrabilité SMTP ni le runner.

Si notifications en retard : vérifier le scheduler et la connexion SQL, lancer `php artisan notifications:deliver`, relire ops:check. Une panne de livraison ne supprime pas l'intention. Répéter la livraison conserve une notification par destinataire/événement. Ne pas supprimer les intentions pour vider artificiellement l'alerte. Si courriels échoués : examiner les erreurs depuis une console restreinte, corriger le transport, reprendre selon ACCOUNT_MAIL.md ; une double livraison SMTP reste possible après crash, pas une double consommation du lien.

## Exercice de sauvegarde/restauration fourni

Outil : `scripts/ops/exercise-backup.php`. Il utilise les exécutables d'une installation contrôlée via HAAS_PG_BIN, loopback 127.0.0.1, HAAS_OPS_PORT explicite et le rôle **haas_test**. Aucune connexion distante. La base source doit suivre `haas_*_test`, la cible `haas_*_restore_test`, déjà créée et **vide**. Aucun DROP, --clean ou écrasement d'archive. Dépendances : PHP/OpenSSL, Symfony Process déjà verrouillé, clients PostgreSQL ; aucun nouveau package runtime.

La clé d'archive est un fichier de 32 octets aléatoires, extérieur au dépôt, jamais un argument contenant sa valeur. Exemple d'appel depuis la racine, après configuration de l'environnement local dédié :

```text
php scripts/ops/exercise-backup.php backup haas_backup_source_test CHEMIN_ARCHIVE_NEUVE CHEMIN_CLE_PRIVEE
php scripts/ops/exercise-backup.php restore haas_backup_restore_test CHEMIN_ARCHIVE CHEMIN_CLE_PRIVEE
```

pg_dump au format custom, chiffrement AES-256-GCM avec nonce aléatoire et entête authentifié. Aucun dump en clair sur disque ; taille bornée à 64 Mio, mémoire suffisante requise. La restauration authentifie et vérifie entièrement l'archive avant toute écriture, refuse une base non vide, puis pg_restore utilise --single-transaction --exit-on-error. Ne restaurer que des archives produites et autorisées par l'équipe ; le chiffrement n'est pas une sandbox pour du SQL provenant d'un tiers.

Preuve locale du 3 octobre 2026 : sauvegarde puis restauration dans une nouvelle base ; comptes, profil masqué, versions, motifs déchiffrables, audit, signalement et notification contrôlés. Trois refus exécutés : archive modifiée, mauvaise clé, cible non vide. La cible des essais cryptographiques est restée sans table. Détails et limites dans quality/SOCLE_RECEPTION_PARTIELLE.md depuis docs.

## Publication ultérieure

Une release serveur exige revue et GO_PRODUCTION. L'artefact doit correspondre au commit testé ; API compatible d'abord, frontend ensuite. Vérifier sauvegarde restaurable, migrations additives, santé, workers et domaines réels avant réception. Ce travail ne donne aucun GO_FRONTEND/GO_PRODUCTION et n'effectue aucun déploiement.
