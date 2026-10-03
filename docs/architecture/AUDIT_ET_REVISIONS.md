# B12 — Contrat interne d'audit et de révisions

## Décision et périmètre

`App\Services\Audit\AuditWriter` écrit dans `content_revisions`, journal privé commun des changements métier. Une table porte l'événement et sa révision ; aucun double journal à réconcilier. Premier consommateur réel : `UpdateProfileService` (B10). Aucun historique synthétique n'est ajouté aux modifications antérieures à B12.

Le journal décrit les changements ; il ne permet pas de restaurer les textes précédents. Les valeurs nécessaires à ce premier cas sont le numéro de version du profil et le nombre de technologies. Biographie, pays, langue libre, URL, courriel, IP, mots de passe, tokens, cookies, corps HTTP et empreintes de ces contenus ne sont jamais transmis à l'audit. Même un texte contenant involontairement un secret ne sera pas recopié dans une révision.

Les domaines des collègues restent leurs consommateurs futurs, sans création de leurs tables ni de leurs règles. Pour chaque nouvelle commande, ajouter un adaptateur typé et sa liste blanche dans le socle, avec le pilote du domaine. Ne pas créer une entrée générique acceptant `$request->all()`, `Model::toArray()`, `getOriginal()` ou un dictionnaire de métadonnées arbitraire. Les contrôles B12 ne valident pas tous les retraits AC25, qui dépendent de B31 et des parcours métier.

## Schéma

| Colonne | Source et règle |
|---|---|
| id | UUID généré par le serveur |
| actor_id | Identifiant du compte relu côté serveur ; FK users, devient null si ce compte est supprimé |
| resource_type / resource_id | Type constant `profile`, UUID du propriétaire provenant du modèle persisté |
| revision | Entier positif, unique par type/ressource, attribué sous verrou ; indépendant du lock_version du profil |
| action | Constante de l'adaptateur : `profile.updated` ou `history.redacted` |
| metadata | Objet JSON, construit par liste blanche ; jamais une sérialisation du profil |
| occurred_at | Heure UTC du serveur, aucun argument date accepté |
| redacted_at | Null, ou date UTC de purge ; dans ce cas metadata doit être `{}` |

La migration est additive. Son `down()` retire uniquement la table d'audit : réservé aux tests dédiés, pas une procédure de purge en production. L'historique peut survivre à la suppression de sa ressource ; le couple type/UUID n'est donc pas une FK polymorphe fictive. Il reste privé et ne réexpose pas un contenu disparu. L'attribution à un compte supprimé devient indisponible sans inventer d'acteur.

## Écrire depuis le métier

`profileUpdated(User $actor, Profile $profile, ProfileRevisionData $data): void` exige une transaction PostgreSQL déjà ouverte et des modèles persistés sur la même connexion. L'appel hors transaction, sur une autre connexion ou avec un modèle non persisté échoue. Pas de queue, observer, événement après commit ou nouvelle connexion pour cet audit.

`UpdateProfileService` garde la transaction englobante, vérifie ses droits et sa version, écrit le profil et les pivots, puis appelle AuditWriter avant le commit. Les noms des champs effectivement changés sont calculés côté serveur. Le DTO n'accepte que `bio`, `country`, `primary_language`, `github_url`, `technology_ids`, sans doublon, association ou valeur imbriquée. Une édition sans changement de valeur garde le comportement B10 (version avancée) et produit une liste de champs vide.

Le writer relit le compte et le profil sous verrou, recontrôle `UserPolicy::updateProfile` et construit uniquement :

```json
{"changed_fields":["bio","technology_ids"],"profile_version":1,"technology_count":2}
```

Ordre de verrouillage pour ce cas : propriétaire User → Profile → technologies triées pour le changement de sélection ; les verrous déjà acquis restent détenus lors de l'audit. Le verrou de ressource sérialise aussi le premier événement : `max(revision) + 1` n'est calculé qu'après ce verrou. La contrainte unique SQL protège le numéro. Deux vrais processus testent des éditions successives, et le conflit optimiste B10 ne crée aucune révision supplémentaire.

L'échec d'écriture d'une révision remonte `AuditStorageFailed`, sans SQL, bindings ou exception chaînée. Il annule la modification métier entière. Un appelant ne doit jamais absorber cette erreur puis valider son métier. La sous-transaction du writer garantit aussi l'annulation de ses écritures intermédiaires. Le rollback de la transaction englobante annule audit, profil et technologies ensemble. Ce comportement s'appuie sur les [transactions Laravel](https://laravel.com/docs/13.x/database#database-transactions) et les [verrous SQL](https://laravel.com/docs/13.x/queries#pessimistic-locking), puis est vérifié sur PostgreSQL réel.

## Préparer un retrait sans conserver le secret

`redactProfileHistory(User $actor, Profile $profile): int` est une primitive interne, sans endpoint ou commande utilisateur. Elle exige elle aussi la transaction métier. Le compte acteur est relu sous verrou et doit encore avoir la capacité `moderate` (actif, vérifié, modérateur/admin) ; être propriétaire ne suffit pas. Elle ne modifie pas le texte courant du profil et ne certifie pas qu'un retrait complet a eu lieu.

Le futur service de retrait B31 devra retirer/masquer les données courantes et leurs autres projections dans la même transaction, puis purger cet historique. Pour une action concernant plusieurs utilisateurs, verrouiller les comptes acteur/propriétaire par UUID croissant avant la ressource ; ne pas commencer en verrouillant seulement le propriétaire puis demander un compte antérieur dans l'ordre. Le writer suit cet ordre et verrouille ensuite le profil.

La purge remplace en SQL les métadonnées des révisions de cette ressource par `{}`, sans lire ni recopier leurs anciennes valeurs. Elle conserve identité de l'événement, attribution, date et numéro ; marque `redacted_at` et ajoute `history.redacted` avec le seul nombre de révisions purgées. Une seconde purge sans nouvelles révisions retourne 0, sans événement supplémentaire. Une panne d'insertion de l'événement de purge annule toute la purge. Les autres ressources restent intactes. Une future écriture normale prend le prochain numéro, sans remplir à nouveau les anciennes lignes.

La purge en base ne prouve pas l'effacement dans d'anciennes sauvegardes : leur traitement appartient à la procédure d'exploitation/retrait. Aucun texte de motif libre ou copie du secret n'est nécessaire à la primitive.

## Confidentialité et intégration HTTP

Aucune route de lecture ou d'écriture d'audit, aucun Resource public d'historique. Les réponses de profil sont inchangées. `actor_id`, `revision`, `occurred_at`, `metadata` et tous les champs serveur soumis par HTTP sont rejetés par la liste blanche existante. Toute future lecture interne exige sa propre Policy et une projection restreinte ; ne pas exposer directement cette table.

Le schéma permet les prochains domaines mais les méthodes publiques actuelles n'acceptent que le profil et les deux actions contrôlées. Il ne protège pas contre un administrateur SQL qui modifierait directement les lignes ; aucun journal cryptographique inviolable n'est annoncé. Aucun endpoint B31, parcours de modération complet, email ou déploiement n'est livré par B12.
