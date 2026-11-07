# B33 — Registre versionné du laboratoire

## Portée livrée

B33 définit le registre de releases connues, les tables applicatives de contrôle
`lab_definitions`, `lab_runs`, `lab_results`, la sélection interne d'un scénario et
la lecture publique d'une capacité. Il ne lance aucun processus, ne crée aucun
run via HTTP et n'expose aucune écriture publique de résultat.

La production garde `LAB_ENABLED=false` par défaut. Activer une définition et le
switch indique une capacité configurée ; cela ne démontre ni l'isolation d'un
worker, ni le succès d'une exécution. B34 apporte l'opération, la réservation du
quota et le lancement ; B36 apporte le service restreint et les observations
réelles ; B37 apporte la récupération après panne. Aucun résultat de ces lots
n'est remplacé par une constante ou par un test de schéma.

## Release fixe et scénarios

`ApprovedLabRegistry` contient uniquement la clé interne `b1.idempotent.v1`, la
release `b1-source-20261011`, le module `1.0.0` et le commit source
`524067b3eda56b0f6f04098423d707f6ebee4087`. Les empreintes des fichiers B1 et de
leurs trois suites PostgreSQL et du harness/fixtures nécessaires sont fixées
dans le code, y compris connexion et schéma B1. Chaque manifeste de
groupe trie les chemins relatifs puis calcule SHA-256 sur
`chemin + NUL + empreinte_du_fichier + LF`. La lecture revérifie les octets des
fichiers connus et refuse liens symboliques, fichiers absents ou empreintes
différentes. Aucun chemin, classe, shell, code ou URL fourni par un utilisateur
ne choisit un runner.

La liste est fermée : `nominal_event`, `duplicate_event`, `distinct_events`,
`invalid_event`. Le contrôle de concurrence B1-05 appartient aux tests CI et
n'est pas un scénario web. Cette release identifie le code B1 existant ; elle
ne constitue pas un paquet worker déployé, une baseline BV204 ou une réception
humaine de production. B2 garde son origine et ses données fictives séparées.

## Lecture publique

`GET /api/v1/versions/{id}/lab-definition`, UUID de version, sans paramètre ni
corps. Réponse `Cache-Control: no-store` (Symfony peut ajouter `private`).
Le contrat OpenAPI 3.1 est `docs/api/openapi/lab.yaml`.

```json
{
  "data": {
    "version_id": "00000000-0000-4000-8000-000000000001",
    "available": false,
    "definition": null
  }
}
```

Lorsque `available=true`, `definition` contient `id`, `release`,
`module_version`, `code_digest`, `suite_digest`, `version_digest` et les quatre
scénarios `{key,label}`. Aucune classe, chemin de runner, donnée privée de source
ou identité de propriétaire n'est projetée.

| Situation courante | Réponse |
| --- | --- |
| Version publiée, capsule visible, propriétaire actif/vérifié, source publique ou origine éditoriale sans source ; définition active, release et empreintes exactes, switch vrai | 200, capacité disponible |
| Même version lisible, mais définition absente/inactive, switch fermé, release ou empreinte différente, digest historique inconnu | 200, capacité indisponible |
| Version inconnue, brouillon, en revue ou retirée ; capsule masquée ; propriétaire inactif/non vérifié ; source devenue privée, masquée, brouillon ou auteur suspendu/non vérifié | 404 |
| Paramètre de requête ou corps additionnel, y compris code/runner | 422 |

Une sélection interne B34 reçoit uniquement `capsule_version_id` (UUID) et un
`scenario` connu. Tout champ additionnel est refusé. Elle reprend les gardes de
lecture et refuse une capacité indisponible avec 503 ; elle n'écrit aucune
commande. B34 devra recontrôler ces gardes sous ses verrous de lancement.

`has_lab`/Q16 désigne la capacité **actuellement disponible pour la version
sélectionnée**, pas la présence d'une ancienne définition dans l'historique.
Une nouvelle version n'hérite pas du badge d'une autre version. Une capsule
documentaire publiée reste lisible lorsque sa demande source devient privée ;
sa capacité laboratoire est alors fermée. La Query publique ne constitue pas
un snapshot transactionnel entre toutes les lectures concurrentes.

## Liaison interne sans SQL manuel

`php artisan lab:bind-definition <UUID_de_version> b1-source-20261011` accepte
seulement un UUID cible et une release de la liste fermée. Cette commande
interne de release ne publie aucune capsule, n'exécute aucun code, ne crée
aucun run et ne change pas `LAB_ENABLED`. Elle vérifie les empreintes réelles,
la version publiée avec digest connu, la visibilité de la capsule, son
propriétaire actif/vérifié et sa source actuellement publique. Les comptes
sont verrouillés par UUID trié, puis la source, la capsule, la version et la
définition sont relues sous verrou dans la transaction APP.

La première liaison crée la définition active ; le switch global reste fermé
par défaut. Une liaison identique est un rejeu sans nouvelle ligne ni audit ;
elle conserve une désactivation explicite. Une définition différente cause
un refus, aucun snapshot n'est écrasé. Les droits courants sont contrôlés avant
le rejeu. La restauration de ces droits n'entraîne aucun lancement implicite.

L'audit atomique `lab.definition.bound` porte seulement la référence de version
et la release fixe, sur la ressource de définition. L'acteur est `NULL` : une
opération système n'invente ni identité humaine ni validation de revue. Les
sorties n'incluent ni contenu, identifiants de connexion, erreur SQL ou entrée
non reconnue. Le défaut de stockage de l'audit annule aussi la liaison. La
commande ne remplace aucune autorisation de déploiement/GO_PRODUCTION.

## Contrôle APP et fixtures LAB

Les trois modèles de contrôle utilisent explicitement la connexion APP
`pgsql`. Ils ne lisent ni n'écrivent les fixtures du runner. Une définition
porte une seule version et un manifeste immuable ; seul son booléen `enabled`
peut changer. Un run conserve le manifeste et le digest de cette version en
snapshot immuable. La paire membre/clé de requête est unique.

L'index PostgreSQL interdit deux runs `running` globalement. Il n'implémente
pas la réservation d'une opération active par membre : B34 doit partager cette
réservation et les cinq unités/h entre run simple et comparaison BV206, dont
les deux enfants sont séquentiels. Une comparaison ne doit pas payer ou réserver
deux fois au rejeu. La borne de queue appartient aussi à B34.

`LabConnection::NAME` et le code de fixtures B1 existants ne sont pas modifiés
par B33. Leur éventuel fallback applicatif ne représente pas l'isolation LAB
exigée : B36 doit utiliser un runtime local distinct, sous `haas-lab`, sans
bootstrap Laravel APP, `.env` APP ou identifiants APP accessibles, avec rôle SQL
restreint et canal borné selon ADR-004. Les définitions/runs/résultats restent
dans APP ; les seuls événements et commandes fictifs restent dans LAB.

## États et rapport de contrôle

Transitions : `queued` → `running` → `passed`/`failed`/`error`/`timed_out`.
Une tâche `queued` peut être finalisée `error`/`timed_out` sans prétendre avoir
commencé. Un état terminal, un snapshot de run et un résultat sont immuables.
La suppression de ces historiques est refusée par les triggers.

Un résultat complet exige un run `running`, des assertions non vides et un
état `passed` ou `failed`. Le run ne peut prendre cet état terminal sans le
résultat correspondant. `passed` exige toutes les assertions vraies ; `failed`
exige au moins une assertion fausse. `error` et `timed_out` sont incomplets ;
ils peuvent conserver des observations partielles ou aucun rapport, sans
faux résultat complet. Si un rapport existe, son état doit être celui du run
finalisé. Une panne technique n'est pas un échec fonctionnel.

Le rapport à assembler par B36 est un objet JSON UTF-8 comportant :

| Champ | Type / origine |
| --- | --- |
| `run_id`, `version_id`, `definition_id` | UUID du contrôle APP |
| `scenario`, `release`, `module_version`, `source_commit` | snapshot fixe du run |
| `code_digest`, `suite_digest`, `version_digest` | SHA-256 du snapshot |
| `state`, `complete` | état terminal et booléen cohérents |
| `created_at`, `started_at`, `finished_at`, `duration_ms` | dates du contrôle ; début/durée peuvent être inconnus pour une panne avant démarrage |
| `runtime` | objet de versions et contexte technique observés par le worker, sans secret |
| `assertions` | liste d'observations `{key,passed,expected,observed}` |

Chaque clé d'assertion est unique, ASCII `[a-z0-9][a-z0-9._-]{0,63}` ;
`passed` est un booléen et `expected`/`observed` sont des valeurs JSON, null
autorisé. Les quatre champs sont obligatoires et aucun champ additionnel
n'est admis dans une assertion. Le stockage borne la somme JSONB UTF-8 des
assertions et du runtime à 60 000 octets pour réserver la place des métadonnées.
B36 doit borner à **65 536 octets** le rapport complet sérialisé en UTF-8,
calculer les observations et valider le protocole au retour du worker. Les
gardes de structure ne prouvent pas qu'un worker a exécuté ces observations.

## Conservation et réception

Le rollback B33 verrouille les tables et refuse toute définition, tout run ou
tout résultat connu avant de retirer tables ou fonctions. Le helper de tests
vérifie une base dédiée avant de déposer les fixtures ; cette remise à zéro
n'est pas un rollback de données de production. Les fonctions utilisent
`CREATE OR REPLACE` afin de supporter `migrate:fresh` répété, qui laisse les
fonctions après suppression des tables.

La réception B33 distingue tests de validation, visibilité et contraintes SQL
des futurs tests de worker, charge, interruption et quotas. Aucune approbation
humaine, exécution de production, publication de PR ou gate globale ne découle
de cette documentation.
