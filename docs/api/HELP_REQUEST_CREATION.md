# B14 — Créer une demande

`POST /api/v1/requests` accepte un objet JSON validé, une session de membre actif et vérifié, le jeton CSRF et une clé `Idempotency-Key` UUID v4 obligatoire. Il retourne 201 et une `HelpRequestResource`, avec `Cache-Control: no-store`. Aucun endpoint de lecture ou d'édition n'est livré ici ; B15/B16 les ajoutent.

## Intention explicite et validation

- `mode=draft` : titre de 1 à 140 caractères ; autres contenus facultatifs, plafonds inchangés, zéro à cinq technologies. Les champs absents restent null, sans faux texte de remplissage. Le brouillon est privé à son auteur, même face à un admin.
- `mode=publish`, intentions techniques : titre 15–140, goal/expected 30–2000, observed 30–4000, attempts 20–3000 ou « aucune » (insensible à la casse), environnement requis jusqu'à 255 caractères, une à cinq technologies existantes et distinctes. État `open` fixé par le service.
- `help_intent=ask_question` et publication : mêmes titre/goal/observed/technologies ; expected, attempts, environnement et code facultatifs et nullables. S'ils sont fournis, les limites correspondantes s'appliquent.
- `help_intent` est obligatoire : `unblock`, `review_solution`, `reproduce_behavior`, `ask_question`. Il ne crée pas un second workflow.
- Code facultatif, texte inerte de 12000 caractères maximum, espaces conservés ; code_language requis si le code est fourni, identifiant de langage jusqu'à 40 caractères. Langue fr par défaut ; code de langue jusqu'à 35 caractères.
- Technologies : liste `{id, version_label?}`, UUID normalisé, version facultative jusqu'à 40 caractères. Aucun champ de pivot supplémentaire.
- Lien de reproduction HTTPS facultatif, 2048 caractères maximum, sans identifiants dans l'URL. Le serveur ne le visite pas.
- Tous les champs inconnus sont refusés, même null : auteur, state, lock_version, dates, hidden_at, etc. `project_id` attend BC04 ; cette route ne simule aucun lien projet.

Les textes contenant un motif usuel de clé privée, jeton ou affectation de secret sont refusés en 422, y compris dans un brouillon. Le message ne recopie jamais la valeur. Détection indicative, pas garantie d'absence de secret ; aucune évaluation du code et aucune promesse de rendu frontend sécurisé avant ses propres tests.

Les trois formes exactes et la réponse sont décrites dans [OpenAPI](openapi/community.yaml). Les messages de validation ajoutés sont en français.

## Transaction, droits et rejeu

Le service réutilise `IdempotencyService` : acteur relu/verrouillé et droits actuels contrôlés, puis technologies relues sous verrou partagé. La demande, le pivot, la révision d'audit et le descripteur de rejeu sont écrits dans la même transaction. Une panne d'audit annule tout ; la même clé peut être retentée après correction.

L'empreinte utilise la projection normalisée du DTO : ordre/casse des UUID de technologies normalisés, valeurs facultatives absentes et null représentées de la même façon, langue fr par défaut. Un autre contenu avec la même clé donne 409 `IDEMPOTENCY_CONFLICT` ; la même intention retourne le même identifiant/version sans nouvelle écriture. La borne de charge canonique passe de 64 à 128 Kio pour accepter simultanément tous les maxima Unicode de B14 ; les limites métier restent inchangées.

Le descripteur contient seulement request_id/version/statut. Avant sa projection, le service relit la demande sous verrou, applique la Policy courante et contrôle la version : contenu masqué ou compte suspendu interdit, version modifiée en 409, ressource disparue en 404. Aucun corps privé mémorisé ni restauration d'un ancien contenu.

L'audit `help_request.created` contient seulement les identifiants, la date serveur, l'état, l'intention, la version et le nombre de technologies. Aucun titre, texte, extrait, URL, courriel ou clé client.

## Coordination et migrations

Le 4 octobre 2026, l'utilisateur confirme que Madina n'a pas commencé B14 ni HelpIntent/BV201 et demande de continuer B14. Le fichier partagé unique est `App\Enums\HelpRequests\HelpIntent`, introduit par B14 : BV201 et BC07 le réutilisent. Cela ne valide ni les cas documentaires de BV201, ni les liens projet/recettes de BC07.

B11 de Madina (PR #23) et ses cinq migrations restent inchangés ; la PR #22 complète B11 et cible désormais #23. B14 part de `5854e04` sur `backend/communaute-entraide-b14` et cible temporairement la branche de #22. Intégrer #23 → #22 → B14, en reciblant vers main et en revérifiant les CI après synchronisation. Conserver les auteurs, petites PR et branches permanentes ; aucune fusion automatique.

La migration additive B14 rend nullables les contenus des brouillons/questions et ajoute l'intention (anciens enregistrements = unblock), le code, la langue, le lien et hidden_at. Elle ne recrée pas les tables. Son retour refuse un schéma NOT NULL incompatible avec des brouillons/questions existants ; rollback testé seulement sur une base dédiée.

Preuves : [B14_CREATION.md](../quality/B14_CREATION.md).
