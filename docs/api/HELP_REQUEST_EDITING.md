# B16 — Édition, publication et historique des demandes

## Contrat et décisions

PATCH `/api/v1/requests/{id}` : membre actif vérifié propriétaire, demande visible et non archivée. `lock_version` obligatoire, entier 1–2147483646 ; au moins un champ éditable. Champs partiels : title, goal, expected, observed, attempts, environment, code, code_language, primary_language, reproduction_url, help_intent et technologies. Une liste de technologies fournie remplace la liste ; champ absent conservé, null explicite seulement sur champs nullables. Auteur, état, résolution, dates, project_id et tous champs inconnus sont rejetés, même null.

Les limites de B14 sont réutilisées. Le contenu complet résultant est revalidé sous verrou selon l'état courant et help_intent ; une demande publiée reste publiable, y compris après passage de ask_question à unblock. Modifier le code seul peut conserver son langage existant. Secret suspect, lien dangereux et code sans langage sont rejetés. Les liens/code restent inertes.

Après présence d'un commentaire, d'une proposition ou d'une résolution (même ancien ou masqué), `edit_note` est obligatoire : 20–1000 caractères, sans motif suspect. Avant contribution, la note est facultative et soumise aux mêmes limites si fournie. Les clarifications n'altèrent ni les contributions ni l'état de résolution. Le sens de la clarification reste une responsabilité éditoriale ; aucune IA ne prétend vérifier sa pertinence. Archives en lecture seule dans B16.

POST `/api/v1/requests/{id}/publish` : corps strict `{lock_version}` ; vérifie le brouillon actuel complet puis passe uniquement draft → open. Un brouillon incomplet reste privé (422). Publier une demande déjà publique avec une nouvelle intention est un conflit 409. L'édition d'un brouillon n'entraîne jamais sa publication.

Les deux commandes exigent session Sanctum/CSRF et UUID v4 Idempotency-Key, quota 30/minute. Retour 200 avec la Resource B14 ; version incrémentée une fois, même si les valeurs fournies sont identiques. Verrou dans l'ordre acteur → demande, droit/propriété/version revérifiés. Version périmée 409 sans changement, rollback en cas de panne d'audit ou d'historique. Même acteur/route-cible/clé/charge : même version sans nouvelle révision ; charge différente 409. Après autre modification, rejeu ancien = 409 ; retrait/suspension interdit le rejeu. Auteur/état inaccessibles n'accordent aucun bypass admin.

## Historique lisible sans copie des anciens contenus

GET `/api/v1/requests/{id}/revisions` : visibilité du parent B15 et pagination commune page/per_page (20/max50), ordre version décroissante. Données : id, request_version, action (updated/published), changed_fields, edit_note, occurred_at. L'auteur du parent est l'auteur des modifications ; aucun courriel, snapshot de contenu, audit interne ni clé d'idempotence n'est exposé.

Une révision de brouillon reste privée après publication : seul le propriétaire actif vérifié la retrouve. La publication et les éditions publiques sont visibles tant que leur parent l'est. Un parent masqué/suspendu ou brouillon d'autrui donne 404, sans compteur de notes privées. Réponses no-store. Une note retirée avec son parent ne reste accessible par aucune route d'historique publique.

Table additive `help_request_revisions` : référence demande/acteur, version unique par demande, action/visibilité serveur, champs modifiés, note et horodatage. Pas de copie ancienne du corps/code. Audit commun séparé : uniquement noms de champs, version, action et présence de note, aucune note textuelle dans content_revisions. La note métier suit la visibilité du parent ; aucune nouvelle route de suppression destructive.

## Coordination et limites

B17/B18 et les futures commandes de contributions doivent prendre le même verrou parent avant mutation ; tests B16 utilisent les vrais modèles B11, sans déclarer ces endpoints livrés. Les projets/parent projet seront raccordés par BC04/BC05. B16 ne change pas la résolution, les capsules, leurs preuves ou la modération. Migration additive uniquement, aucun renommage des migrations de Madina. Revue/CI/fusion avant passage Systalink à Terminé.
