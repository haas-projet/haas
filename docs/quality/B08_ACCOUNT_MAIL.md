# B08 — Courriels de compte

2 octobre 2026. Branche permanente `backend/socle-auth`, avancée sans réécriture depuis le prérequis B07 `b9b38db643bc496c6c23bf86f291e798a039d544`. PR #10 encore ouverte et verte au démarrage ; B08 ne la modifie pas. Contrat : [ACCOUNT_MAIL.md](../api/ACCOUNT_MAIL.md), OpenAPI 0.6.0. Aucun nouveau package ni migration.

## Livré

Quatre routes : forgot-password, reset-password, renouvellement du courriel de vérification et consommation du lien signé. FormRequests strictes, DTO secrets masqués, Services transactionnels, Policy propriétaire/actif, Resources minimales. Réponses de demande de reset indépendantes du compte et du throttle interne du broker. Liens signés sur APP_URL API ; reset sur un chemin FRONTEND_URL fixe, secrets dans le fragment ; aucune URL fournie par le client.

Inscription et job de vérification atomiques. Notifications Laravel chiffrées dans une file SQL dédiée sur la connexion transactionnelle ; worker après visibilité du commit, retries bornés et échéance conservée. Transport mémoire local, SMTP requis en production, log refusé. Double livraison SMTP possible après crash, explicitement documentée ; consommation sûre et idempotence de vérification sans faux « exactement une fois ».

Tokens reset Laravel hachés, 60 minutes, renouvellement 60 secondes ; verrou utilisateur pour émission/consommation, reset à usage unique, suppression des sessions atomique. Un secret bcrypt historique peut être remplacé par Argon2id. Empreinte de mot de passe dès login et contrôle web/API pour empêcher une connexion tardive de recréer un accès après reset. Vérification ne peut être faite par un autre membre/admin ; middleware verified bloque les actions non vérifiées. Routes métier de test uniquement, aucun endpoint métier inventé.

## Contrôles locaux exécutés

PHP 8.5.10, Composer 2.10.3 temporaire téléchargé depuis getcomposer.org et SHA-256 vérifié `7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6`. PostgreSQL 17.0 sur 127.0.0.1:54682, nouveau cluster `haas-b08-0b5d7bbf741341f3ab541e3281fd47df`, base `haas_mail_test`, rôle `haas_test`, DB_URL vide. Serveur de test arrêté en finally ; service habituel inchangé.

| Commande | Observé |
|---|---|
| composer format puis composer lint | Format appliqué, contrôle final réussi |
| composer analyse | Niveau 8, aucune erreur, sans ignore/baseline |
| composer test | 158 tests / 1453 assertions réussis |
| composer test:integration | 54 tests / 495 assertions réussis, PostgreSQL réel |
| composer validate --strict --no-check-publish | Valide |
| composer check-platform-reqs | Tous satisfaits |
| composer audit --locked --no-interaction | Aucun avis de vulnérabilité |
| node scripts/validate-pack.mjs | 18/18 contrôles documentaires réussis |
| node scripts/check-deployment-docs.mjs | 7/7 contrôles documentaires réussis |
| git diff --check | Aucune erreur |

Total distinct : **212 tests / 1948 assertions**. La suite SQL comprend deux processus concurrents d'inscription et deux processus indépendants consommant un même token reset ; un seul reset aboutit. Session authentifiée réelle, CSRF actif, expiration/signature altérée, token remplacé/consommé, throttle, absence de fuite de compte, refus d'un autre utilisateur/admin, mot de passe Unicode long et bcrypt historique, événements sans duplication, contrôles de courriel/statut relus sous verrou.

Un observateur SQL indépendant ne voit ni compte ni job avant commit ; après commit, un vrai job chiffré est dépilé et produit un message MIME via ArrayTransport, sans Notification::fake pour cette preuve. Le rollback ne laisse pas de job. Une panne SQL réelle lors de la suppression des sessions annule password/token/sessions ; logs formatés sans courriel/token/hash/mot de passe ni bindings. Les autres tests utilisant des fakes ne valent pas cette preuve de transport.

Corrections guidées par contrôles : annotations typées et contrat concret du broker, ponctuation YAML ; protection d'une session écrite après un reset ; navigation depuis un client mail externe permise uniquement en GET signé sans Origin, avec session destinataire. Les essais en échec ne sont pas comptés comme réussis.

## Statut et limites

B08 IN_PROGRESS jusqu'à observation de sa propre CI, puis IN_REVIEW. Cibler la branche B07 tant que #10 attend sa fusion ; intégrer #10 avant B08 et recibler vers main. Conserver les trois branches permanentes et main ; ne supprimer la branche temporaire de #10 qu'après fusion et reciblage des PR dépendantes.

SMTP/délivrabilité réelle, navigateur sur domaines finaux, DNS/TLS, Qodana et déploiement non exécutés. Conditions réelles d'inscription toujours absentes : 503 par défaut. B09 reste à livrer pour /me et les capacités métier ; les Policies de domaines devront utiliser verified. Aucune revue humaine simulée, aucun BACKEND_GATE ou GO_FRONTEND validé. Aucun mail envoyé à une personne ; uniquement transport mémoire et adresses fictives de test.
