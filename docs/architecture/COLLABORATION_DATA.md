# Schéma de collaboration — B11

Le lot B11 fournit les données des demandes, commentaires, propositions et résolutions. Il ne publie aucun endpoint ; les droits, transitions, réponses HTTP et validations des contenus appartiennent à B14–B21. Les tables utilisent les comptes et technologies de B05.

## Tables et invariants PostgreSQL

| Table | Champs et règles structurantes |
|---|---|
| `help_requests` | UUID, auteur, titre et contenus structurés, état explicite `draft/open/in_progress/resolved/archived`, `lock_version` entier positif initialisé à 1, dates UTC. Index état/date et auteur/date. |
| `request_technologies` | Clé primaire `(request_id, technology_id)`, version texte facultative de 40 caractères, dates. Pas de doublon de technologie pour une demande. |
| `comments` | UUID, demande et auteur, contenu, dates d'édition/masquage facultatives, `lock_version` positif initialisé à 1. Index demande/date. |
| `proposals` | UUID, demande et auteur, diagnostic/correction/vérification/limites, état explicite `proposed/accepted/not_selected`, `lock_version` positif initialisé à 1. Index demande/date. |
| `resolutions` | UUID, demande et proposition, acteur/date d'acceptation, note, date de révocation facultative. Index demande/date. |

Les identifiants sont des UUID PostgreSQL. Les références aux comptes, technologies, demandes et propositions sont des clés étrangères. Les états de demande et proposition sont obligatoires, sans valeur implicite dans la base, et limités par CHECK ; les services devront choisir leur état initial.

`resolutions(request_id, proposal_id)` référence la paire unique `proposals(request_id, id)`. Une proposition d'une autre demande est donc refusée par PostgreSQL, même si ses deux UUID existent.

L'index unique `resolutions_one_active_per_request` porte sur `request_id WHERE revoked_at IS NULL`. Il empêche deux résolutions actives, tout en conservant les résolutions révoquées. Il ne remplace ni la Policy d'auteur, ni la transaction/version, ni le test à deux processus de la commande d'acceptation prévue par B19. AC10 n'est pas reçu par ces seuls tests de schéma.

Une suppression de compte, de technologie référencée, de demande avec contributions ou de proposition référencée est refusée. Seul le pivot de technologies disparaît automatiquement avec une demande supprimée. Aucun endpoint de suppression n'est livré par B11 ; les futurs workflows conservent l'historique.

## Modèles et données de test

Les quatre modèles exposent leurs relations Eloquent, casts enum/dates et factories. Une résolution de test prend par défaut l'auteur réel de sa demande comme acceptant ; une attribution différente doit être demandée explicitement pour un test négatif.

Identifiants, auteurs, états, versions et dates serveur ne sont pas assignables en masse. `accepted_at`, comme `accepted_by`, est fixé explicitement par le futur service. Les FormRequests devront également rejeter ces champs : la protection des modèles seule ne valide pas le contrat HTTP.

Les migrations initiales déjà publiées par `mdev44-code` gardent leur nom et leur contenu. La migration additive `2026_10_03_180500_b11_add_collaboration_versions.php` ajoute les versions des commentaires/propositions et les CHECK de positivité des trois contenus éditables. Elle conserve les données existantes et la version des demandes. Elle s'annule avant les cinq migrations initiales ; ce cycle est testé sur PostgreSQL dédié.

## Limites à traiter dans les lots suivants

- B14/B16 : champs de code inerte, règles de brouillon/publication, validation des longueurs et rejet HTTP des champs serveur.
- BV201 puis BC07 : enum `HelpIntent` unique et règles conditionnelles `ask_question`, notamment nullabilité des champs facultatifs. Aucun deuxième enum n'est introduit ici.
- B17/B18 : incrément transactionnel des versions pour les éditions, sans mise à jour silencieuse d'une contribution acceptée.
- B19/B20 : auteur seul habilité, verrouillage, résolution/réouverture, audit et notifications atomiques ou après commit selon leur contrat.
- BC et BH : projets et offres consenties, encore distincts de ce schéma initial.

Preuves d'exécution : [B11_COLLABORATION.md](../quality/B11_COLLABORATION.md).
