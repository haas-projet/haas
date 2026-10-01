# HAAS — Atelier collaboratif de mise à l’épreuve

**Mise à jour : 1er octobre 2026.** Décision produit approuvée dans la conversation ; spécification à implémenter. Ce dossier n'atteste pas de code applicatif déjà exécuté.

## 1. Proposition de valeur

**Dans la communauté HAAS, un développeur apporte une solution. Un autre apporte le cas qu’elle ne gère pas. Ensemble, ils l’améliorent et partagent ce qui a été vérifié.**

HAAS est d’abord une communauté où découvrir des développeurs et des projets, poser des questions et partager des connaissances. L’atelier ci-dessous est une branche distinctive, pas le seul usage ni une étape obligatoire. F16/F17 sont détaillés dans COMMUNAUTE_ET_PROJETS.md. La boucle est : proposer → apporter un cas → vérifier → améliorer → comparer → réutiliser. Le laboratoire est au service de la collaboration, pas un catalogue de concours de programmation. L'utilisateur conserve son assistant IA ; HAAS ne prétend ni remplacer les agents de développement, ni inventer les tests, la revue ou Git.

L'hypothèse à éprouver est la facilité à retrouver un cas pertinent, solliciter une contribution et comprendre l'amélioration d'une version. Aucune supériorité universelle ni victoire au concours n'est promise.

## 2. Périmètre et terminologie

F01–F12 et B1/B2 sont conservés. F13 ajoute les cas documentaires ; F14 la comparaison contrôlée ; F15 la fiche de vérification et les contributions reliées. Une demande peut avoir help_intent : ask_question, unblock, review_solution ou reproduce_behavior. Ce champ n'ouvre pas trois workflows indépendants. Les tentatives et extraits existants accueillent une piste issue d'une IA ou d'un humain sans importer toute une conversation.

Un **cas proposé** est un document humain. Un **scénario approuvé** est un test écrit/revu par l'équipe et livré par la CI. Un **rapport** décrit une exécution réelle. Une **comparaison** associe deux exécutions comparables. Une **contribution** est une action attribuée ; elle n'est ni un certificat ni un score d'expertise.

P0 limité : un comparateur B1, quatre scénarios web fixes, deux implémentations éditorialisées approuvées pour cette démonstration. B1-05 reste un vrai test concurrent en CI. Les autres capsules n'ont pas automatiquement de laboratoire. B2 conserve son protocole navigateur, sans second comparateur. La copie de contexte pour un assistant est P1 : aucune dépendance à une API IA.

## 3. F13 — Proposer un cas à vérifier

**Accès.** Membre actif et vérifié, sur une demande accessible ou une version de capsule accessible. Exactement un parent : help_request_id XOR capsule_version_id. Le serveur fixe l'auteur et recontrôle la visibilité à la lecture, à la soumission et après retrait. Une version n'est jamais remplacée silencieusement par « latest ».

**Données.** Titre 15–140 caractères ; contexte 20–2 000 ; étapes 20–4 000 ; résultat attendu 10–2 000 ; résultat observé facultatif, 4 000 maximum ; environnement 500 maximum. Même politique de Markdown/code inerte, de secrets et de liens non récupérés que F02. Un cas peut proposer des étapes sans avoir été exécuté : le libellé doit le dire.

**Cycle.** draft → submitted → reviewed ou declined ; reviewed → integrated uniquement par un manifeste de release approuvé ; retrait motivé possible. Un auteur corrige un brouillon. Modifier un cas soumis crée une nouvelle révision et le ramène à draft ; la revue précédente ne valide pas les nouveaux mots. Une révision intégrée reste figée ; proposer une révision suivante, ne pas écraser la source d'une preuve.

**Revue.** Modérateur/admin habilité distinct de l'auteur : motif obligatoire pour refus ou retrait. reviewed signifie « examiné », pas « test réussi ». Un cas n'est marqué integrated que si la release associe case_revision_id, scenario_key, suite_digest et commit dans un manifeste réellement relu/déployé. Pas de bouton permettant au modérateur de saisir un shell ou de transformer le texte en code.

**Attribution.** L'auteur du cas, la personne qui reproduit, celle qui corrige et celle qui documente peuvent être distincts. Aucun score fabriqué, aucune équivalence entre nombre de cas et niveau de compétence. Une intégration peut être éditoriale ; sa provenance est affichée et ne devient pas une adoption externe.

## 4. F14 — Comparer deux implémentations approuvées

**Registre.** Un comparison_profile déclaré dans la release identifie la famille B1, le manifeste de référence pédagogique, le manifeste candidat rattaché à une version, la suite de scénarios et un schéma d'entrée fixe. La référence incorrecte porte « Exemple pédagogique incorrect », n'est pas proposée en téléchargement et n'est pas publiée comme une capsule recommandée. Les deux implémentations sont approuvées pour l'exécution, pas déclarées toutes deux correctes.

**Entrées API.** Seulement comparison_profile_id et scenario_key d'une liste autorisée, avec Idempotency-Key. Pas de code, URL, commande, chemin, nom de classe, scénario dynamique ou paramètre libre. Le serveur fige les versions, code digests, suite digest, environnement et entrées canoniques au lancement. Dans ce P0, le jury choisit le scénario, pas le programme ni un montant réel.

**Comparabilité.** Les deux côtés utilisent la même charge utile normalisée, le même seed, le même oracle d'attendu, les mêmes identifiants d'assertion et la même configuration d'exécution. Les namespaces de fixtures restent distincts. Une différence de profil/suite/environnement incompatible empêche la comparaison (409), pas une conclusion favorable. Ne pas présenter cette comparaison comme un benchmark de performance.

**Exécution réelle.** Deux enfants lab_run sont créés avec rôles baseline/candidate et digests figés. Le worker exécute chaque côté à partir de fixtures neuves. Les résultats ne sont ni copiés d'une exécution antérieure ni remplis à partir des attendus. Les étapes préparées et versions déjà déployées sont annoncées comme telles ; aucune prétention à écrire un correctif en direct.

**Quotas.** L’enveloppe retenue reste cinq unités d'exécution/heure/membre. Une comparaison réserve atomiquement deux unités dès acceptation ; un run simple en réserve une. Un rejeu de la même clé ne consomme pas deux fois. Une opération active par membre ; les deux enfants sont séquentiels. Un seul run réellement actif au maximum globalement. Les places et le quota sont partagés entre appels simples et comparaisons : aucune seconde voie ne les contourne. File d'attente bornée ; 429 et Retry-After documentés. Aucun débit réservé n'est remboursé automatiquement après un résultat ambigu.

**Délais.** Les bornes de 15 s cible et 20 s maximum par enfant sont conservées. Budget global d'une comparaison démarrée : 50 s proposé, sans compter le temps en file. Au-delà : timed_out et conclusion inconclusive. Le suivi navigateur est asynchrone ; le timeout HTTP ne tue pas ni ne relance aveuglément l'opération.

**Pannes.** Claim atomique, lease/execution_token et finalisation unique. Une reprise détecte les enfants terminés ; elle ne crée pas une seconde paire et ne mélange pas une autre release. Si le worker disparaît, le réconciliateur attribue error/timed_out ; aucune réussite par défaut. Une indisponibilité du lab ne bloque ni les demandes ni la lecture documentaire.

## 5. États et conclusion

ComparisonState : queued, running, completed, error, timed_out. ComparisonOutcome : improved, unchanged, regressed, mixed, inconclusive.

Une comparaison completed peut conclure à une régression. Un HTTP 200 ne signifie pas que la solution est correcte. Les données machine restent exclusivement écrites par le worker restreint.

| Observation sur les mêmes assertions | Conclusion autorisée |
|---|---|
| Au moins un échec devient conforme, aucun conforme ne régresse | improved : amélioration sur ces cas |
| Au moins un conforme devient échec, aucune amélioration | regressed : régression sur ces cas |
| Une amélioration et une régression coexistent | mixed : résultats mitigés |
| Le vecteur de conformité ne change pas | unchanged : conformité inchangée, valeurs brutes conservées |
| Exécution absente, interruption ou comparaison invalide | inconclusive : pas de conclusion exploitable |

Une évolution de conformité n'est pas une certification ni un verdict sur tous les comportements possibles. Un résultat partiel affiche ce qui existe avec la mention « Comparaison incomplète ». Une version retirée ou un incident de secret annule l'accès public au contenu sensible ; le résultat historique n'est pas réécrit en succès.

## 6. F15 — Fiche de vérification et mémoire collective

Sur la capsule : version choisie, cas examinés, nature de preuve, résultat, date, contributeurs et limites. Séparer « Accepté par l'auteur », « Observation déclarée », « Test exécuté » et « Comparaison effectuée ». Absence de test = « Aucun test de laboratoire disponible pour cette version ».

Le résumé n'agrège pas les résultats en note de fiabilité. Les digests et traces sont disponibles en détail secondaire. Une nouvelle version ne reprend ni badge ni réussite de l'ancienne. Un rapport accessible mentionne explicitement source CI, laboratoire web ou déclaration humaine.

P1 : « Copier le contexte pour mon assistant ». Prévisualiser une sélection de contenu autorisé (objectif/version/procédure/limites), vérifier la copie réelle, permettre d'annuler. Aucun envoi externe ni extraction de conversation privée. Les instructions contenues dans une capsule sont des données non fiables, pas des ordres système pour l'assistant destinataire. Si le P1 n'est pas livré, aucun bouton inerte n'est affiché.

## 7. API et stockage proposés

Préfixe /api/v1 ; erreurs HAAS et UUID ; droits recontrôlés à chaque appel. Le contrat sera développé et testé par Codex, les chemins suivants sont une cible, pas une API existante.

| Route | Responsabilité |
|---|---|
| GET/POST /verification-cases | Liste filtrée par visibilité / créer son brouillon |
| GET/PATCH /verification-cases/{id} | Lire / réviser ses données autorisées avec lock_version |
| POST /verification-cases/{id}/submit | Figer une révision soumise |
| POST /admin/verification-cases/{id}/review | Revue motivée, acteur distinct |
| POST /verification-cases/{id}/withdraw | Retrait autorisé, historique conservé/expurgé si nécessaire |
| GET /versions/{id}/comparison-profiles | Profils compatibles et approuvés uniquement |
| POST /comparisons | Créer une paire contrôlée ; 202 + suivi |
| GET /comparisons/{id} | État et rapport autorisés, pas de mutation publique |
| GET /versions/{id}/verification-summary | Fiche versionnée, contributions et limites |

Le contexte IA P1 est assemblé côté React à partir des réponses déjà autorisées, sans endpoint d'export massif.

Nouvelles tables : verification_cases, verification_case_revisions, case_scenario_links, comparison_profiles et comparison_runs. Les runs enfants réutilisent lab_runs/lab_results avec comparison_id et comparison_side. Ajouter checks parent exclusif et côtés autorisés, unique(case_id, revision), unique(comparison_id, side) et clés étrangères appropriées. Les manifests, seeds/empreintes et versions sont figés ; les chemins du code ne viennent pas des lignes administrables par l'utilisateur.

## 8. Sécurité et conditions de réception

Les Policies combinent rôle, propriété et visibilité. Les quotas couvrent toutes les voies de lancement. Un cas « reviewed » ne donne aucun droit d'exécution. L'API publique ne reçoit ni passed, ni outcome, ni observed machine, ni digest. Aucune récupération de lien extérieur ; aucune publication de rapports contenant un secret.

AC33–AC52 complètent AC01–AC32. API/concurrence sur PostgreSQL, reprise worker, comparaison compatible/incompatible, accès direct, interface mobile/clavier et sincérité des badges sont à tester. Les tests du pack documentaire ne valent pas exécution de l'application.

## 9. Exécution à deux

Ordre : S → backend complet incluant F13/F14/F15 → BACKEND_GATE + GO_FRONTEND humain → frontend → recette. Les corrections backend nécessaires pendant F restent autorisées en commits séparés. Les 78 lots v1 sont conservés par identifiant ; 16 compléments portent le plan à 94 lots. Ce nombre n'est pas un objectif de commits ni une estimation horaire.

Réestimer après inspection du dépôt. Les 180 h engagées / 216 h de capacité de la planification initiale ne sont pas une estimation de l’extension. Sans marge, retirer les P1 et les raffinements, pas les contrôles d'accès, la traçabilité ni les pannes. Aucun délai ou retour humain n'est inventé.
