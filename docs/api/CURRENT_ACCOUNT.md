# B09 — Compte courant et autorisations

Contrat OpenAPI 0.7.0. `GET /api/v1/me` requiert la session Sanctum B07 et un compte actif. Un membre non vérifié peut lire son compte et gérer son courriel ; il ne peut pas publier ni lancer le laboratoire. Lecture uniquement : aucun DTO de commande vide ni Service d'écriture artificiel. Chaîne MeRequest → CurrentAccountQuery → MeResource, Controller limité à l'adaptation HTTP.

## Réponse privée

`data` contient exclusivement `id`, `handle`, `email`, `email_verified`, `role`, `status`, `is_demo` et `can`. Le courriel et le rôle sont privés au membre courant ; ne jamais réutiliser MeResource pour un profil public. La Query choisit l'identifiant de l'acteur authentifié, relit son état et ne sélectionne ni hash ni remember_token. Aucune relation, adresse IP, donnée de session ou date de vérification détaillée exposée. `Cache-Control: no-store` sur succès et erreurs ; l'origine SPA autorisée reçoit CORS avec credentials.

Aucun paramètre ni corps accepté, y compris `user_id`, `author_id`, `role`, `include` et valeurs nulles : 422. Visiteur ou ancien cookie révoqué : 401 ; origine étrangère : 403 ; compte suspendu authentifié : 403 `ACCOUNT_SUSPENDED` et révocation de la session courante. Une panne 500/503 n'est pas une déconnexion et ne détruit pas la session. Aucun bearer token.

## Capacités et propriété

| Capacité `can` | Compte actif non vérifié | Membre vérifié | Modérateur vérifié | Admin vérifié |
|---|---|---|---|---|
| manage_account_mail | Oui, soi uniquement | Oui, soi uniquement | Oui, soi uniquement | Oui, soi uniquement |
| update_profile | Non | Soi uniquement | Soi uniquement | Soi uniquement |
| participate | Non | Oui | Oui | Oui |
| moderate | Non | Non | Oui | Oui |
| administer | Non | Non | Non | Oui |

Ces indicateurs expriment l'éligibilité du compte, pas la disponibilité d'un module ni un droit sur n'importe quelle ressource. Ils viennent de UserPolicy, via Gate ; aucune règle `Gate::before` ou `Policy::before` n'accorde tous les droits à l'admin. `view` et `manageAccountMail` refusent toujours un compte tiers. `updateProfile` exige également actif/vérifié et propriété.

`MemberAccess::verified(?User)` est le prérequis commun de participation. `MemberAccess::owns(?User, string $ownerId)` ajoute la propriété sans privilège de rôle. L'identifiant propriétaire doit venir du modèle relu côté serveur, jamais de la requête. Les Policies métier de LamineGL et mdev44-code ajoutent visibilité, état, approbations et quotas ; les commandes sensibles recontrôlent après verrouillage. Pour accepter une solution, utiliser l'auteur de la demande. Pour une revue indépendante, vérifier au contraire que le relecteur diffère de l'auteur. Aucune Policy métier absente n'est prétendue livrée par cette aide commune.

Les tests B09 couvrent la matrice des trois rôles × vérifié/non vérifié × actif/suspendu, les visiteurs, comptes tiers et capacités inconnues. Une route de fixture exclusivement dans les tests démontre le refus d'un admin non auteur et d'un auteur non vérifié, sans effet après refus. Les scénarios métier complets AC03 et AC09 attendent B11/B16/B19 ; les modèles et tables des collègues ne sont pas créés par B09.

## Information après suspension

`EnsureAccountIsActive` remplace EnsureActiveSession en gardant son rôle de révocation. L'erreur `ACCOUNT_SUSPENDED` n'est exposée qu'après identification du membre (session ou mot de passe correct). Un mauvais mot de passe garde la réponse générique B07.

`GET /api/v1/account-access` donne une information publique identique pour tous, sans rechercher un compte ni divulguer le motif privé d'une suspension. Cette route reste accessible après révocation, y compris si c'est le premier accès d'une session suspendue ; cette session est alors révoquée. Aucun paramètre accepté. Réponse `data.message` et `data.contact_email`, sans cache.

L'adresse de recours est fournie par `ACCOUNT_SUPPORT_EMAIL`, volontairement vide par défaut : `contact_email: null` et message d'indisponibilité explicite. Une configuration non vide invalide donne 503 sans exposer sa valeur. Définir et vérifier une vraie adresse publique avant ouverture du service ; aucun destinataire inventé, aucun envoi de mail effectué ici. Le futur écran peut consulter cette route sans réauthentification. Aucun nouveau module de messagerie ou traitement automatique des recours.

## Limites d'intégration

B07 puis B08 doivent être fusionnés avant B09. Les trois branches permanentes sont conservées ; la branche temporaire B09 sera supprimée seulement après fusion et vérification. B10 livrera les endpoints de profils ; B32 livrera les commandes de suspension/changement de rôle et leur révocation globale atomique. Le recalcul de droits de B09 ne remplace pas cette révocation globale. Aucun frontend, revue humaine, BACKEND_GATE ou déploiement validé par ce lot.
