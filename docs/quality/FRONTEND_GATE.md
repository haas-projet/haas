# HAAS — Réception Frontend et intégration

**Lot :** F26. **État initial :** NON REÇU. **GO_RELEASE :** NON.

Ce fichier est un formulaire de décision, pas une preuve d’exécution ni une protection technique contre toute désobéissance d’un agent. Les validations sont renseignées uniquement après constat.

| Domaine | Critère | État | Preuve / commit | Relecteur réel |
|---|---|---|---|---|
| Contrat | API réelle branchée, types cohérents et mocks confinés aux tests. | NON EXÉCUTÉ | — | — |
| Navigation | Guards corrects, pas de fausse déconnexion sur 503, retour navigateur conservé. | NON EXÉCUTÉ | — | — |
| Composants | Champs/boutons/feedback partagés, aucun appel HTTP dans JSX. | NON EXÉCUTÉ | — | — |
| Fonctionnel | Demander→proposer→accepter→capsule→test→retour réalisé avec plusieurs comptes de test. | NON EXÉCUTÉ | — | — |
| Sécurité navigateur | Cache privé purgé, CSRF et droits réels, contenus rendus sans script. | NON EXÉCUTÉ | — | — |
| Design | Écrans essentiels revus sur mobile/desktop, captures ouvertes et anomalies corrigées. | NON EXÉCUTÉ | — | — |
| Accessibilité | Contrastes réels, clavier, focus, zoom et reflow vérifiés ; limites enregistrées. | NON EXÉCUTÉ | — | — |
| Erreurs | 422/409/419/429/réseau, pending et contenu vide vérifiés sans perte trompeuse. | NON EXÉCUTÉ | — | — |
| B2 | Coupure réelle, fermeture/reprise, IndexedDB indisponible et double envoi testés. | NON EXÉCUTÉ | — | — |
| Performance | Build et mesures dans l’environnement consigné, pas de score inventé. | NON EXÉCUTÉ | — | — |
| Qualité | Tests composants et navigateur exécutés ; CI réellement observée ou réserve distincte. | NON EXÉCUTÉ | — | — |
| Revue | Deuxième personne valide la compréhension et les tâches, pas seulement la beauté de l’accueil. | NON EXÉCUTÉ | — | — |

## Décision humaine à remplir après vérification

- Personne et date : non renseignées.
- Version/commit examiné : non renseigné.
- Résultat : pas de décision à ce stade.
- Réserves externes : à relever ; ne pas les renommer « tests réussis ».
- Risques/limites acceptés et motif : non renseignés.
- Autorisation GO_RELEASE : NON.

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

- [ ] Accueil, navigation et pitch centrés communauté ; projets/développeurs visibles.
- [ ] UX18–20 reliés à API réelle, erreurs/états/mobile/clavier contrôlés.
- [ ] Projet→demande→contribution→capsule et question sans labo testés.
- [ ] Filtres, préférences volontaires et disponibilités déclarées ; caches privés purgés.
- [ ] AC53–68 applicables exécutés et preuves datées ; pas de compteur ou CTA fictif.

## Coup de main — gate complémentaire ADR-006

- [ ] UX21–23 / FH01–05 reliés aux vraies API ; deux consentements compréhensibles, CTA utile sans demande initiale.
- [ ] AC88–90 : courses/erreurs/cache privé, mobile/clavier/zoom et parcours proposition→acceptation→apport observés.
- [ ] Vue du progrès : offre acceptée distincte d’un résultat ; pas de discussion privée ou de faux matching.
