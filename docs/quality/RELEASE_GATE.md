# HAAS — Réception Livraison concours

**Lot :** R06. **État initial :** NON REÇU. **GO_PUBLICATION_AUTORISEE :** NON.

Ce fichier est un formulaire de décision, pas une preuve d’exécution ni une protection technique contre toute désobéissance d’un agent. Les validations sont renseignées uniquement après constat.

| Domaine | Critère | État | Preuve / commit | Relecteur réel |
|---|---|---|---|---|
| Recevabilité | Sujet/dates/modalités clarifiés ; droits/attributions/licences et contribution originale inventoriés. | NON EXÉCUTÉ | — | — |
| Service | Produit Datacloud effectivement utilisé et configuration compatible contrôlée. | NON EXÉCUTÉ | — | — |
| Build | Artefact lié au commit testé, notices, digests et config d’environnement distincte. | NON EXÉCUTÉ | — | — |
| Sécurité | Aucun incident critique/élevé confirmé non résolu ; secrets absents des captures et du dépôt. | NON EXÉCUTÉ | — | — |
| Exploitation | Backup restauré sur autre environnement, rollback de code testé sans migration destructive. | NON EXÉCUTÉ | — | — |
| Disponibilité | Santé, logs expurgés, workers, quotas et maintien pendant évaluation prévus. | NON EXÉCUTÉ | — | — |
| Preuves | AC applicables réellement exécutés, rapport Qodana honnête, CI et tests liés au commit. | NON EXÉCUTÉ | — | — |
| Démonstration | A/B/C fictifs identifiés, B1 recalculé, B2 présenté selon capacités réelles. | NON EXÉCUTÉ | — | — |
| Pilote | Effectifs réels, observations et limites ; absence de faux témoignages ou compteurs. | NON EXÉCUTÉ | — | — |
| Transmission | README, OpenAPI, architecture, scripts, notices, IA, limites et runbook disponibles. | NON EXÉCUTÉ | — | — |
| Autorisation | Déploiement/publication/compte d’évaluation approuvés par l’équipe ; aucune signature inventée. | NON EXÉCUTÉ | — | — |

## Décision humaine à remplir après vérification

- Personne et date : non renseignées.
- Version/commit examiné : non renseigné.
- Résultat : pas de décision à ce stade.
- Réserves externes : à relever ; ne pas les renommer « tests réussis ».
- Risques/limites acceptés et motif : non renseignés.
- Autorisation GO_PUBLICATION_AUTORISEE : NON.

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

- [ ] F01–F18, UX01–23, AC01–90 couverts (AC47 conditionnel).
- [ ] Présentation et sources alignées au mail ; aucune fonction conçue présentée comme imposée par l’organisateur.
- [ ] Cas communautaire pilote séparé des comptes de démonstration ; code externe non intégré sans droits.

## Coup de main — gate complémentaire ADR-006

- [ ] F18, AC69–90, UX21–23 et RH01 : périmètre cohérent avec code, démonstration et limites réellement constatées.
- [ ] Dossier source, résumé public consenti, retraits et journal d’audit conformes aux décisions explicites.
- [ ] Pilote : objectifs atteints ou non documentés ; refus et absences non masqués ; aucun résultat inventé.
