# HAAS — Réception Backend P0

**Lot :** B44. **État initial :** NON REÇU. **GO_FRONTEND :** NON.

Ce fichier est un formulaire de décision, pas une preuve d’exécution ni une protection technique contre toute désobéissance d’un agent. Les validations sont renseignées uniquement après constat.

| Domaine | Critère | État | Preuve / commit | Relecteur réel |
|---|---|---|---|---|
| Périmètre | Tous les cas API P0 développés, y compris support B2 ; aucun endpoint factice. | NON EXÉCUTÉ | — | — |
| Architecture | Requests/DTO/Services/Policies/Queries/Resources séparés et règles expliquées. | NON EXÉCUTÉ | — | — |
| Identité | Session/CSRF effectif, vérification/reset, suspension et non-divulgation testés. | NON EXÉCUTÉ | — | — |
| Droits | IDOR, accès directs, rôles, propriété et absence de bypass admin testés. | NON EXÉCUTÉ | — | — |
| Intégrité | Résolution concurrente, contraintes PostgreSQL, idempotence et versions testées. | NON EXÉCUTÉ | — | — |
| Connaissances | Revue indépendante, publication immuable, retraits et kits contrôlés. | NON EXÉCUTÉ | — | — |
| Lab | Résultats réels, quotas, runner connu, récupération après panne, séparation démontrée localement. | NON EXÉCUTÉ | — | — |
| B2 | API idempotente et contrat prêts ; aucune SPA B2 construite avant passage. | NON EXÉCUTÉ | — | — |
| Contrat | OpenAPI complet, exemples fictifs, erreurs normalisées et types générables indépendamment de la SPA. | NON EXÉCUTÉ | — | — |
| Qualité locale | Tests configurés et réellement exécutés, formatage/analyse et couverture utile. | NON EXÉCUTÉ | — | — |
| CI distante | Exécution GitHub Actions prouvée, ou absence explicitement classée comme réserve externe. | NON EXÉCUTÉ | — | — |
| Qodana | Rapport/commit/offre réels, ou statut non exécuté et décision explicite sur la réserve. | NON EXÉCUTÉ | — | — |
| Installation | Procédure reproductible et environnement requis décrits, aucune base réelle ciblée par tests. | NON EXÉCUTÉ | — | — |
| Revue | Autre personne identifiée, observations traitées et décision de phase réellement donnée. | NON EXÉCUTÉ | — | — |

## Décision humaine à remplir après vérification

- Personne et date : non renseignées.
- Version/commit examiné : non renseigné.
- Résultat : pas de décision à ce stade.
- Réserves externes : à relever ; ne pas les renommer « tests réussis ».
- Risques/limites acceptés et motif : non renseignés.
- Autorisation GO_FRONTEND : NON.

Un défaut de droits, de fuite, de cohérence des données ou de sincérité des rapports ne peut pas être écarté pour gagner du temps. Une réserve externe non critique peut être acceptée pour poursuivre le développement local, mais reste ouverte pour la réception correspondante. Aucune revue humaine n’est produite automatiquement par l’agent.

## Contrôles de l’atelier

| Contrôle | Statut | Preuve |
|---|---|---|
| F13 : cas documentaires, révisions, revue distincte et liaison par manifeste | NON EXÉCUTÉ | — |
| F14 : profils approuvés, entrées comparables et deux observations réelles | NON EXÉCUTÉ | — |
| Quotas partagés, idempotence, pannes et conclusions mitigées/régressives | NON EXÉCUTÉ | — |
| F15 : types de preuves séparés, contributeurs et absence de badge hérité | NON EXÉCUTÉ | — |
| AC33–AC52 selon phase ; AC47 uniquement si P1 copie livré | NON EXÉCUTÉ | — |

Une validation antérieure ne couvre pas automatiquement les nouveaux contrats. Ne jamais remplir une revue à la place du second développeur. Les tests pack/contrastes ne valident pas l’application.


## Contrôles du déploiement
La cible ADR-004 est applicable. Vérifier les contrôles de sa phase dans DEPLOYMENT_GATE ; recette réelle DNS/TLS et connexion finale obligatoire avant ouverture. Un run actif global, CORS explicite et B2 hors cookies HAAS. Les 90 AC gardent leurs identifiants ; les 14 DEP-AC décrivent la livraison. Ultimate déclaré disponible ne vaut pas rapport obtenu.


## Contrôles communautaires ajoutés

- [ ] F16 projets : ownership, états séparés, publication, archivage, idempotence et catalogue.
- [ ] Lien projet/demande : auteur propriétaire, parent public actif et visibilité descendante.
- [ ] F17 annuaire opt-in actif/vérifié : aucun courriel/last_login ; retrait/suspension/cache.
- [ ] ask_question sans code ni champs de panne ; autres intentions sans régression.
- [ ] OpenAPI/dictionnaire/types à jour ; preuves BC01–BC08 et AC53–64, revue réelle.

## Coup de main — gate complémentaire ADR-006

- [ ] F18 : BH01–BH10 exécutés, routes réelles, DTO/Services/Policies séparés et contrats intégrés.
- [ ] AC69–87 serveur : consentements, TTL, quotas, acceptation atomique, nouveau fil/rattachement et confidentialité testés sur PostgreSQL.
- [ ] Aucun droit supplémentaire acquis ; offres pending privées ; double envoi ne crée qu’un fil/événement.
- [ ] Revue humaine additionnelle et GO_FRONTEND pour F18 ; pas d’approbation copiée d’une ancienne livraison.
