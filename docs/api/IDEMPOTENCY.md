# B13 — Idempotence des commandes

## Contrat HTTP actif

Premier consommateur réel : `PATCH /api/v1/me/profile`. `Idempotency-Key` y est **facultatif**, pour préserver le contrat B10 : sans clé, `lock_version` conserve son rôle. Les créations et acceptations des futurs domaines exigeront la clé selon leurs contrats ; leur implémentation n'est pas annoncée par B13.

La clé est un UUID v4, une intention = une clé. Casse normalisée, ni espace, ni préfixe, ni seconde valeur concaténée. Une clé présente mais invalide donne 422 `VALIDATION_FAILED`, champ `Idempotency-Key`. CORS autorisait déjà cet en-tête ; aucune origine ajoutée. La clé ne remplace ni session, ni CSRF, ni vérification du compte.

Pour un membre et une cible donnés, pendant 24 heures :

| Situation | Résultat |
|---|---|
| Même clé, même charge validée | Une seule écriture métier et un seul audit ; même identifiant/version de résultat |
| Même clé, autre charge | 409 `IDEMPOTENCY_CONFLICT`, aucune écriture |
| Autre membre ou autre route-cible | Intention indépendante, aucun accès à la réponse de l'autre |
| Droits retirés, compte suspendu/non vérifié | Refus avant lecture du résultat mémorisé |
| Profil modifié depuis le premier succès | 409 `RESOURCE_CONFLICT`, ne pas écraser ni renvoyer l'ancien texte |
| Profil supprimé depuis | 404, aucune recréation au rejeu |
| Échec métier, d'audit ou de stockage de l'intention | Rollback de l'ensemble, clé non consommée ; relance possible selon l'erreur |
| Clé expirée | Ancien résultat inutilisable ; commande réévaluée avec toutes ses validations courantes |

Le corps HTTP n'est pas une copie mémorisée octet pour octet : le serveur relit la projection autorisée du résultat. Pour le profil, sa version doit encore correspondre à celle du premier succès. Les référentiels et capacités sont courants, aucun texte supprimé n'est ressuscité. Le numéro de requête HTTP reste propre à chaque appel.

Après une perte réseau, réutiliser **la même clé et la même intention**. Une modification du formulaire n'est pas la même intention. Une 409 impose de vérifier l'état actuel ; ne pas créer automatiquement une nouvelle clé pour masquer une incertitude. Après 24 heures, la déduplication n'est plus garantie : consulter l'état réel avant une nouvelle commande. Ni payload ni confirmation privée dans localStorage ou les logs.

## Stockage et confidentialité

Table additive `api_idempotency`, indépendante des démonstrations B1/B2. Unicité `(user_id, route_target, key_hash)`, FK utilisateur avec suppression en cascade, index d'expiration. Statut de succès contrôlé, réponse JSON objet limitée à 2048 octets, hashes hexadécimaux et expiration postérieure à la création contraints en SQL.

La cible est produite par l'adaptateur serveur à partir de la méthode et de la ressource identifiée. Pour `/me/profile`, elle devient `PATCH /api/v1/members/<UUID utilisateur>/profile` : namespace canonique interne, pas une nouvelle route HTTP. Aucune query string ou URL libre n'y entre.

La clé UUID n'est conservée que sous SHA-256. La charge n'est pas conservée : son empreinte est un HMAC SHA-256, séparé par domaine, avec la clé applicative serveur. L'empreinte est calculée sur la projection du DTO validé : clés d'objets triées récursivement, listes ordonnées, null/absent/types distincts, UTF-8 valide, profondeur maximale 16 et taille JSON maximale 128 Kio (élargie par B14 pour ses plafonds Unicode, sans changer les limites de chaque champ). Flottants et objets arbitraires sont refusés ; un futur montant doit être représenté par un entier borné ou une chaîne décimale définie par son domaine. L'adaptateur profil normalise et trie sa sélection de technologies, qui est un ensemble ; il préserve la distinction entre champ omis et effacement explicite.

La réponse stockée est uniquement un descripteur typé : statut 200/201/202/204, une à quatre références UUID nommées et éventuellement une version entière. Pour le profil :

```json
{"references":{"profile_id":"fda9a000-3333-4444-8888-123456789abc"},"version":1}
```

Aucune biographie, URL, langue/pays libre, adresse de courriel, cookie, token, mot de passe, corps HTTP ni header de réponse arbitraire n'est stocké. Le temps de création/expiration provient du serveur et un rejeu ne prolonge pas la durée. Une rotation de la clé applicative change le HMAC : les anciennes intentions encore vivantes donnent un conflit, sans réexécution silencieuse. Ne pas purger leurs lignes pour contourner ce conflit.

## Intégration dans un service métier

`IdempotencyService::execute(actor, IdempotencyData, authorize, operation, resolve)` est un service sans objet HTTP. Ses trois callbacks obligatoires sont :

1. `authorize(User courant): void` : Policy du domaine, visibilité/état de la cible, sous les verrous requis. Exécuté avant toute lecture du résultat mémorisé.
2. `operation(User courant): StoredCommandResult` : modification métier et audit, dans la même transaction/connexion, sans commit externe, appel réseau ou effet non annulable. Notifications/outbox seulement selon leur protocole après commit.
3. `resolve(User courant, StoredCommandResult): résultat métier` : relire, contrôler la propriété/visibilité et la compatibilité des références mémorisées, puis produire la projection courante. Appelé pour un premier succès comme pour un rejeu. Ne jamais faire confiance aux seuls UUID du descripteur.

Le service exige PostgreSQL et un acteur persisté sur la connexion métier ; relit et verrouille l'acteur, puis vérifie actif/vérifié avant la Policy du domaine. Les commandes idempotentes d'un même acteur sont sérialisées, y compris avant la première ligne de clé. Ce choix simple est volontaire pour le P0 ; pas de mutex global entre membres. L'unicité SQL complète le verrouillage. La ligne n'est insérée qu'après la commande et la projection réussies, dans la même transaction : aucune réservation `pending` à abandonner après une panne. Les exceptions de stockage restent neutres, sans SQL/bindings ni exception chaînée.

L'édition de profil réutilise `UpdateProfileService`, son verrou/version et AuditWriter ; pas de seconde implémentation des règles B10. La relecture au rejeu ne réexécute ni ce service d'écriture ni l'audit. Deux processus réellement concurrents testent même charge et charges différentes ; les règles s'appuient sur les [transactions Laravel](https://laravel.com/docs/13.x/database#database-transactions) et les [verrous PostgreSQL en READ COMMITTED](https://www.postgresql.org/docs/17/transaction-iso.html#XACT-READ-COMMITTED).

Ordre du cas profil : acteur User → intention existante → profil/technologies/audit. Pour les futurs domaines touchant plusieurs comptes ou quotas, l'orchestrateur ouvre la transaction englobante et verrouille d'abord les quotas/comptes nécessaires dans l'ordre déterministe du domaine **avant** cet appel ; il garde cet ordre pour les opérations qui invalident leurs droits. Un verrou déjà acquis n'est pas libéré par une sous-transaction réussie. Ne pas imbriquer plusieurs intentions dans une commande ; leurs révisions et notifications forment un seul succès métier. Chaque futur adaptateur doit tester ses courses propres, notamment fermeture/retrait/acceptation.

## Expiration et exploitation

Les résultats expirent 24 heures après succès. À échéance exacte, un nouvel appel réévalue le métier ; si sa validation échoue, le rollback conserve la ligne expirée jusqu'à purge, sans rendre son résultat réutilisable.

`php artisan idempotency:prune` retire au plus 1000 lignes expirées par invocation, avec recontrôle de leur échéance lors du DELETE. Aucun profil, audit ou contenu métier n'est supprimé. La sortie contient seulement un nombre. La tâche est déclarée toutes les cinq minutes, sans chevauchement. **L'exécution du scheduler Laravel doit être configurée sur le VPS lors de l'exploitation** ; elle n'est pas activée sur un serveur par cette livraison. Les lignes déjà expirées restent inutilisables même si le scheduler est arrêté. La suppression physique peut donc survenir après 24 heures (cadence, backlog ou indisponibilité) ; le suivi d'exploitation doit détecter ce retard. Aucun ancien contenu textuel privé n'est conservé pendant ce délai.

La migration `down()` supprime uniquement cette table, réservée aux tests dédiés : l'utiliser en production ferait perdre les garanties des intentions encore vivantes. Ce mécanisme ne promet pas une garantie au-delà du TTL, ne remplace pas les contraintes métier d'unicité et ne couvre pas encore les commandes des collègues.
