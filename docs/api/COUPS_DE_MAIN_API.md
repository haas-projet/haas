# HAAS — Contrat API des coups de main

**Contrat de conception F18**, complément des API existantes. À fusionner dans l’OpenAPI du dépôt réel puis à valider par tests, pas à remplacer par des réponses statiques. JSON, UUID, dates UTC, erreurs HAAS `error.code/message/fields` et `request_id`. Pagination 20/max50. Authentification SPA Sanctum et CORS Systalink/Vercel inchangés.

## 1. Routes proposées

| Route sous /api/v1 | Entrée et accès | Réponse attendue |
|---|---|---|
| GET /help-opportunities | Public ; filtres technology_id, category, type, page, per_page en liste autorisée. Les préférences privées restent au client ou dans une lecture privée distincte. | 200 data/meta ; uniquement besoins publics visibles ; aucune offre pending. |
| PUT /projects/{project}/help-settings | Propriétaire actif vérifié ; help_open, help_categories, help_sought, lock_version. | 200 ProjectResource ; fermeture expire les offres pending et conserve les fils. |
| POST /projects/{project}/help-offers | Membre actif vérifié non propriétaire ; category, public_summary, expected_outcome, allow_public_summary=true ; Idempotency-Key. | 201 HelpOfferResource privée, state=pending ; Location. |
| GET /me/help-offers | Acteur connecté autorisé ; direction=sent ou received, state, page, per_page. | 200 collection privée ; proposer/owner seulement, pas de filtre user_id arbitraire. |
| GET /help-offers/{offer} | L’un des deux acteurs ; modérateur uniquement via dossier signalé autorisé. | 200 ressource privée autorisée ; 404 si autre acteur. |
| POST /help-offers/{offer}/accept | Propriétaire actif vérifié, lock_version, publish_consent=true, destination discriminée ; Idempotency-Key. | 200 data.offer + data.request minimal avec id/URL autorisés. Même identifiant au rejeu valide. |
| POST /help-offers/{offer}/decline | Propriétaire, pending, lock_version, reason facultatif max300. | 200 état terminal ; répétition hors clé de rejeu =409 si décision déjà prise. |
| POST /help-offers/{offer}/withdraw | Proposant, pending, lock_version. | 200 withdrawn ; pas de retrait d’une acceptation historique par cette route. |
| GET /projects/{project}/progress | Public si projet visible ; pagination. | 200 projections autorisées des fils/résolutions/capsules ; pas d’offres non acceptées. |
| PATCH /me/profile (étendue) | Préférences de catégories facultatives ; conserver la route existante. | 200 profil propre ; pas d’effet sur directory_visible. |
| POST /reports (étendue) | Type autorisé help_offer ; visibilité de la cible contrôlée pour le signaleur. | 201 ; traitement de modération via mécanisme existant. |

Les commandes decline/withdraw peuvent accepter la clé commune de rejeu ; sans clé, elles doivent rester sans double effet via lock_version et transitions. Ne pas créer deux mécanismes d’idempotence concurrents.

## 2. Exemple de proposition (données fictives)

```json
{
  "category": "usability_feedback",
  "public_summary": "Je peux essayer le formulaire sur mon téléphone et décrire les étapes difficiles.",
  "expected_outcome": "Une liste d’observations et des étapes reproductibles.",
  "allow_public_summary": true
}
```

owner_id, proposer_id, state, timestamps, accepted_request_id, permissions et champs d’audit sont fixés par le serveur et rejetés en entrée. La proposition ne comporte pas de coordonnées obligatoires, de pièces jointes ou de commande exécutable. Aucune donnée n’est récupérée depuis un lien.

## 3. Deux destinations d’acceptation, jamais implicites

**Créer** : `destination.mode=create`, `destination.request` contient les champs validés de CreateHelpRequestData, `project_id` et `author_id` étant fixés par le serveur. Prévisualiser et confirmer avant requête. Par défaut suggérer l’intention adaptée, sans forcer ask_question pour contourner les champs requis d’une véritable panne.

```json
{
  "lock_version": 1,
  "publish_consent": true,
  "destination": {
    "mode": "create",
    "request": {
      "title": "Un premier retour sur le formulaire de commande",
      "help_intent": "ask_question",
      "goal": "Nous souhaitons savoir quelles étapes du formulaire sont difficiles à comprendre.",
      "observed": "Pouvez-vous décrire les hésitations rencontrées pendant votre essai ?",
      "technologies": [{"technology_id": "9d78ec0c-3e7b-428b-a11a-8dc9a5d62b67"}]
    }
  }
}
```

La forme exacte de technologies est à rapprocher du contrat déjà implémenté ; cet exemple fixe l’intention, pas une seconde représentation contradictoire. Les champs de publication doivent traverser le validateur commun, y compris validation conditionnelle selon help_intent. Aucun `new Request` fabriqué ni contournement d’autorisation pour réutiliser le service.

**Rattacher** : `destination.mode=attach`, `request_id` UUID. Le fil doit appartenir au propriétaire, au même projet, être public, ouvert/en cours et visible. Aucune modification de son contenu initial. Afficher son titre dans la confirmation.

```json
{
  "lock_version": 1,
  "publish_consent": true,
  "destination": {
    "mode": "attach",
    "request_id": "9d78ec0c-3e7b-428b-a11a-8dc9a5d62b68"
  }
}
```

Réponse : `data.offer.id/state/accepted_request_id` et `data.request.id` ; le frontend navigue vers la route interne autorisée existante, pas vers une URL arbitraire fournie par un utilisateur. La projection publique est un événement système explicitement attribué avec le résumé consenti, non un commentaire usurpé. Les champs `private_reason`, status pending des autres offres ou email ne sont jamais inclus dans la projection.

## 4. Erreurs et reprise

| Code HTTP | Code métier proposé | Comportement |
|---|---|---|
| 401 | AUTHENTICATION_REQUIRED | Connexion, aucune mutation automatique après retour. |
| 403 | ACCOUNT_NOT_ELIGIBLE | Compte non vérifié/suspendu ou action interdite, selon règle de confidentialité. |
| 404 | RESOURCE_NOT_FOUND | Ressource masquée ou acteur non autorisé, sans révélation de l’existence d’une offre. |
| 409 | PROJECT_NOT_OPEN_FOR_HELP | Projet fermé/archivé ou catégorie retirée ; ne pas perdre la saisie en mémoire. |
| 409 | HELP_OFFER_ALREADY_PENDING | Une offre pending existe déjà pour le couple. |
| 409 | HELP_OFFER_STALE_VERSION | Un autre acte a changé lock_version. |
| 409 | HELP_OFFER_NOT_PENDING | Déjà acceptée/refusée/retirée/expirée ; aucun nouveau fil. |
| 409 | HELP_REQUEST_UNAVAILABLE | Fil cible devenu fermé/masqué ; choisir une destination après relecture. |
| 409 | IDEMPOTENCY_CONFLICT | Même clé avec autre contenu. |
| 422 | VALIDATION_FAILED | Champs invalides ou consentement absent ; errors.fields exploitable. |
| 429 | HELP_OFFER_LIMIT_REACHED | Délai si disponible ; aucune création partielle. |
| 500/503 | INTERNAL_ERROR / SERVICE_UNAVAILABLE | Message neutre, request_id ; pas de succès ou échec final inventé. |

Après perte réseau, vérifier l’état réel et rejouer seulement la même intention couverte par idempotence. Ne pas produire une nouvelle clé pour masquer une incertitude. Une 409 n’est pas une déconnexion. Les offres et payloads de confirmation ne vont ni dans localStorage ni dans les logs de diagnostic.

## 5. Modèle et frontières Laravel

`HelpOffer` : UUID, project_id, proposer_id, category enum, public_summary, expected_outcome, allow_public_summary_at, state enum, lock_version, expires_at, decided_at, accepted_at, accepted_by nullable, accepted_request_id nullable, declined_reason nullable, closed_reason nullable, timestamps. Ne pas persister le consentement comme un simple booléen sans acteur/date. Projet conserve help_open/help_categories et son historique ; profils conservent preferred_help_categories sans catégorie d’expertise.

Contraintes et indexes : unique pending par project/proposer, indexes owner via projects, (proposer_id,state,created_at), (state,expires_at), FK sans cascade sur les fils, validation cohérence accepted/request/decideur. Les FK et services empêchent de rattacher une offre à la demande d’un autre projet. Le snapshot de publication porte la provenance source_offer_id unique et peut être retiré par modération ; il ne crée pas une deuxième messagerie.

Arborescence cible :

```text
app/Enums/HelpOffers/{HelpOfferState,HelpContributionCategory}.php
app/Data/HelpOffers/{CreateHelpOfferData,AcceptHelpOfferData,DeclineHelpOfferData}.php
app/Http/Controllers/Api/V1/HelpOffers/...
app/Http/Requests/HelpOffers/...
app/Http/Resources/HelpOffers/{HelpOfferResource,HelpOpportunityResource}.php
app/Models/HelpOffer.php
app/Policies/HelpOfferPolicy.php
app/Queries/HelpOffers/{ListHelpOpportunitiesQuery,ListMyHelpOffersQuery}.php
app/Queries/Projects/ProjectProgressQuery.php
app/Services/HelpOffers/{CreateHelpOfferService,AcceptHelpOfferService,
  DeclineHelpOfferService,WithdrawHelpOfferService,ExpireHelpOffersService}.php
app/Services/Projects/UpdateProjectHelpSettingsService.php
```

Un controller par commande sensible, services courts par cas d’usage, DTO non HTTP, Policy sans écriture, Resources publiques/privées séparées, événement après commit dédupliqué. Pas de `HelpOfferManager` ou repository universel. Le service d’acceptation garde la transaction englobante ; il utilise le cas de création de demande et l’audit existants sans duplication des règles.

## 6. React, cache et tests

`features/help-offers` contient api/models/schemas/queries/hooks/components/pages/tests. Hooks proposés : useHelpOpportunities, useMyHelpOffers, useCreateHelpOffer, useAcceptHelpOffer, useDeclineHelpOffer, useWithdrawHelpOffer. Un service frontend n’est ajouté que pour une vraie orchestration ; Axios reste dans core/http.

Clés publiques pour catalogue/pagination/filtres ; clés privées avec identité pour offres et permissions. Mutations : invalider projet/catalogue/opportunités, offres envoyées/reçues et notifications ; après acceptation, le fil et la progression. Aucune acceptation optimiste. Annulation et purge à la déconnexion/changement de compte. Guards auth/vérifié/actif pour agir ; catalogue public disponible aux visiteurs. Pas de visibilité publique attribuée par un guard.

Les formulaires de confirmation distinguent deux consentements ; demandes conditionnelles réutilisent les mêmes schémas que la création normale. Tests : propriété, opt-in, compte, publication, limite, TTL, concurrence, idempotence, cache privé, a11y, choix du fil et résultat non simulé. Tous AC69–90 doivent avoir des observations de tests réels avant réception.
