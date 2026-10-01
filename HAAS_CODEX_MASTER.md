# HAAS — Brief maître pour Codex
## Backend complet, puis frontend · petits commits · interface lisible

**Mise à jour :** 1er octobre 2026  
**Équipe :** deux développeurs Laravel / React  
**Nom de concours :** HAAS — Help as a Service  
**Statut :** instructions et plan d’exécution ; aucun code applicatif ni résultat de test applicatif n’est fourni par ce fichier.

> Mission : construire HAAS comme une communauté de développeurs : découvrir des profils et projets, poser des questions, partager des connaissances et collaborer. L’atelier de cas, comparaison et vérifications rend certaines améliorations visibles ; il n’est pas imposé pour discuter. Les résultats, tests et usages sont toujours honnêtes.

## Décision produit active — le coup de main déclenche la rencontre

Appliquer ADR-006, docs/product/COUPS_DE_MAIN.md et docs/api/COUPS_DE_MAIN_API.md. F18 : le propriétaire ouvre volontairement son projet ; un membre propose un apport borné ; le propriétaire accepte avec aperçu public ou décline ; un fil existant est réutilisé ou une demande est créée par le propriétaire dans une transaction. Ce parcours remplace l’impasse « aucun fil, aucune aide possible », pas les questions autonomes.

L’offre en attente reste privée aux deux acteurs ; le résumé ne devient public qu’après deux consentements explicites. Une acceptation signifie « collaboration commencée », jamais « travail terminé ». Ne pas construire de messagerie privée, équipe, paiement, calendrier ou matching IA. Les suggestions sont des filtres explicables. Aucun gain de droit sur le projet/dépôt après acceptation. L’opt-in annuaire n’est pas requis pour aider.

122 lots : S2/B72/F40/R8. F01–F18, AC01–AC90 (AC47 conditionnel), UX01–UX23, 10 skills. Les 106 lots antérieurs gardent leurs identifiants ; BH/FH/RH s’ajoutent avec critères et dépendances. Une revue backend antérieure ne couvre pas F18. Systalink + Vercel, Ultimate et backend avant frontend restent inchangés.

## 00. Décision active — prioritaire pour le déploiement

Backend Laravel sur un VPS Systalink ; frontend React sur Vercel ; PostgreSQL local ; sauvegardes distantes ; Qodana Ultimate déclaré existant. Aucun second VPS LAB, aucune préproduction permanente ni chatbot ajouté. F13–F15 sont conservés en complément ; F16 projets/F17 annuaire et ask_question complètent le thème communautaire.

ADR-004 remplace les déploiements de même origine et à deux VPS des versions précédentes. Un service standalone haas-lab fonctionne localement sous une autre identité et sans secrets Laravel, via un canal Unix restreint. Un run actif global, comparaisons séquentielles ; quotas inchangés. Les tests doivent démontrer la restriction et la continuité de l’API.

Frontend app.haas.example.com et API api.haas.example.com sont des exemples ; parent .haas.example.com réservé aux hôtes de confiance. baseURL absolue depuis VITE_API_URL, CORS explicite, session/CSRF Sanctum, aucun wildcard de preview. B2 sur demo.example.com / demo-api.example.com reste hors cookies HAAS. Lire AUTH_CORS_SANCTUM.md avant B07 et F05.

Ultimate ne signifie pas Ultimate Plus ; budgets et fonctions doivent refléter les accès réels. Les 106 lots existants gardent leur ID ; BH01–BH10, FH01–FH05 et RH01 portent le total à 122 ; douze sous-lots de livraison et quatorze contrôles DEP-AC les précisent. Les domaines, tarifs de panier, tokens, autorisations et résultats ne sont pas inventés. Les archives des anciennes versions ne sont pas incluses ; suivre uniquement les documents actifs de ce dossier.

## Alignement prioritaire sur le mail CADEV

Lire docs/sources/MAIL_CADRAGE_CADEV.md, docs/product/ALIGNEMENT_MAIL.md, COMMUNAUTE_ET_PROJETS.md et ADR-005. L’organisateur exprime des usages ; les modules précis sont nos choix, pas ses obligations techniques. Conserver découverte de personnes/projets, questions simples et partage avant de mettre en avant le laboratoire.

Implémenter F16 (fiches projet, propriétaire, catalogue et demandes liées), F17 (annuaire opt-in, compte actif vérifié, technologie et disponibilité déclarée), et préciser F02 avec ask_question. Pas de nouveau chat ni fil projet : réutiliser help_requests/comments/proposals. Pas de fetch automatique, import Git, invitation d’équipe, score d’expertise ou faux indicateur en ligne.

Le thème n’impose pas de code ni de test pour toute discussion. L’API, les modèles générés, les contrôles de visibilité parent, les notifications et les caches doivent porter les nouvelles règles. AC53–68 et UX18–20 ne sont pas optionnels dans ce périmètre. Les additions BC/FC sont avant gates ; ne pas réinitialiser des tâches réellement faites.

**Parcours cible :** A présente projet et demande, B découvre et aide, C apprend d’une capsule ; une question autonome peut aussi recevoir une réponse sans laboratoire. Le comparateur B1 demeure le démonstrateur distinctif, pas la seule valeur du produit.

## 01. Comment utiliser ce fichier

Ce fichier peut être transmis seul à Codex comme brief de développement. Le pack joint apporte en plus les documents sources, instructions par répertoire, skills HAAS, références de design, grilles de recette et modèles de suivi. En mode fichier seul, créer les documents nécessaires progressivement ; ne pas prétendre que les fichiers du pack ont été lus s’ils ne sont pas présents.

Lire d’abord ce brief une fois pour comprendre l’ensemble. Ensuite, charger seulement le lot courant, les règles des dossiers affectés et les références utiles. Les consignes permanentes de AGENTS.md restent compactes ; le détail du backlog ne doit pas être copié intégralement dans AGENTS.md. [S01, S02]

Au premier passage, inspecter le dépôt et exécuter le prochain lot compatible. Ne pas livrer uniquement une nouvelle liste de recommandations : produire les fichiers et tests autorisés, puis les petits commits locaux. À la fin de chaque session, laisser une reprise explicite. La création de ce pack n’autorise pas à publier le dépôt, pousser une branche, déployer, acheter un service ou utiliser des données réelles.

## 02. Sources, périmètre et arbitrages

**[M]** : docs/sources/MAIL_CADRAGE_CADEV.md, extrait fourni par l’utilisateur. **[P]** : docs/product/CAHIER_DES_CHARGES.md, source consolidée. **[A]** : docs/architecture/ARCHITECTURE.md. **[U]** : instructions utilisateur, backend avant frontend, petits commits, accessibilité, Systalink + Vercel et Ultimate déclaré.

Lire aussi ALIGNEMENT_MAIL.md, COMMUNAUTE_ET_PROJETS.md et ATELIER_COLLABORATIF.md. ADR-005 tranche le positionnement communautaire ; ADR-001/004 conservent la séquence et l’hébergement. Les usages viennent du mail ; les classes, modules, seuils et tests sont des prescriptions HAAS, non des demandes techniques attribuées à l’organisateur.

Les droits de diffusion, dates contradictoires, budget et accès fournisseur restent à confirmer. Aucun ancien document absent n’est présumé lu. L’estimation historique de charge ne couvre pas automatiquement les nouveaux lots. Suivre les sources actives du pack, pas des archives non incluses.

## 03. Produit à livrer — P0 complet

| Module | Résultat attendu |
|---|---|
| Coup de main F18 | Offre bornée consentie, décision propriétaire, fil lié sans doublon et progrès attribué. |
| Projets | Fiches légères, brouillons/publication/archivage, propriétaire, catalogue et demandes liées. |
| Développeurs | Annuaire volontaire actif/vérifié, filtres technologie et disponibilité déclarée, sans contact privé. |
| Comptes | Inscription, connexion/session, vérification courriel, reset, compte actif/suspendu, profils et technologies. |
| Demandes | Formulaire guidé, brouillon privé, publication, lecture/recherche, édition historisée, archivage. |
| Collaboration | Commentaires et propositions structurées, acceptation par auteur, réouverture et historique. |
| Capsules | Brouillon documenté, revue indépendante, versions publiées immuables, limites/provenance/contributeurs. |
| Réutilisation | Catalogue, favoris privés, kit sous conditions, retours humains liés à une version. |
| Laboratoire | Scénarios approuvés, quotas, worker restreint, rapports calculés et datés, échecs sincères. |
| Modération | Signalements, revue/retrait, gestion de comptes et audit. |
| Notifications | Événements utiles, liste paginée, lu/non lu, absence de doublons. |
| B1 | Module idempotent d’événements fictifs ; 4 scénarios web et 1 scénario concurrent en CI. |
| B2 | Mini-formulaire fictif à reprise après coupure, IndexedDB et protocole réel, hors session HAAS. |
| F13 | Cas proposés par un membre, révisions, revue et lien vers un scénario intégré par release. |
| F14 | Comparaison B1 baseline pédagogique/candidate approuvées, mêmes entrées, deux runs réels. |
| F15 | Fiche versionnée qui distingue acceptation, observation humaine, test et comparaison. |

P1 non engagé : copie de contexte pour son assistant sans API IA, traduction anglaise complète, suggestions de contributeurs, abonnement à discussion, courriels de notification métier, lecture offline de capsules, assistant IA rédactionnel, deuxième laboratoire serveur. Hors scope : messagerie privée, visioconférence, marché de services, paiements réels, application native, éditeur/terminal distant, exécution de code utilisateur, score artificiel de compétence et import autonome de dépôts.

Les deux développeurs restent actifs dans les deux phases : backend partagé entre API/règles et tests/contrats/exploitation, puis frontend partagé entre composants/parcours et intégration/accessibilité. Un propriétaire d’écriture par fichier sensible et une revue croisée par PR.

## 04. Séquence d’exécution et portes de validation

```text
S : Préparation
        ↓
B : Backend P0 complet, tests API et intégration PostgreSQL
        ↓
BACKEND_GATE : preuves + revue humaine + GO_FRONTEND
        ↓
F : Frontend React, API réelle, design, UX et accessibilité
        ↓
FRONTEND_GATE : recette navigateur et revue visuelle
        ↓
R : Déploiement autorisé, restauration, pilote et réception
```

Pendant B : seuls les documents UX, contrats et tokens dans docs/ peuvent être préparés. Pas de frontend/src, de composant React de démonstration B2 ni de maquette codée utilisée comme raccourci. Le backend B2 est réalisé avant sa partie React. Le contrat frontend reste anticipé, pas son développement.

**Un backend complet n’est pas un ensemble de contrôleurs qui retournent des exemples.** Il comporte tous les cas d’usage P0, accès négatifs, validations, transactions, contraintes, lab réel, API B2, tests, erreurs, documentation et mécanismes d’exploitation. Les mock responses restent dans les tests.

BACKEND_GATE distingue : prêt techniquement localement, CI réellement exécutée, accès externes à valider, revue humaine et autorisation de phase. Un blocage externe non critique peut être accepté explicitement pour poursuivre le développement local ; il ne rend pas le contrôle « passé » et bloque toujours la réception concernée. Un défaut de droits, d’idempotence ou de sincérité ne peut pas être dérogé pour gagner du temps.

Les corrections backend découvertes pendant F sont autorisées : isoler le fix backend, ses tests et le contrat, puis reprendre le frontend. « Backend d’abord » ne signifie pas interdiction de corriger une erreur.

## 05. Discipline des petits commits

Une intention cohérente, le minimum de production nécessaire, les tests associés et la mise à jour documentaire dans un commit. Les entrées du plan sont des micro-lots : les subdiviser lorsqu’une revue de quelques minutes ne suffit plus. Viser habituellement 50–300 lignes manuscrites ; au-delà d’environ 500, justifier ou scinder. Ne pas compter les lockfiles, types générés ou squelette initial dans cette cible ; ne pas fragmenter artificiellement les migrations inséparables d’une règle.

Cycle :
1. Identifier le prochain lot et les critères de sortie. Inspecter l’état Git et les fichiers affectés.
2. Écrire/adapter un test exprimant le comportement ; observer l’échec utile lorsque la pratique est adaptée.
3. Implémenter seulement ce lot. Appliquer les frontières et droits côté serveur.
4. Exécuter les contrôles ciblés ; vérifier les chemins d’échec, pas uniquement le cas heureux.
5. Relire `git diff` et `git diff --check`, puis sélectionner les fichiers explicitement.
6. Créer le commit local si les prérequis sont réunis. Ne jamais sauter un hook pour forcer.
7. Rapporter le SHA réel, les tests exécutés et le prochain lot. Mettre le suivi à jour sans falsifier un résultat ni une revue.

Format de message :

```text
feat(resolutions): protéger l’acceptation concurrente

- Vérifier l’auteur et l’appartenance de la proposition.
- Verrouiller la demande et empêcher deux résolutions actives.

Tests: [commande réellement exécutée et résultat]
Refs: F04, AC09, AC10, B19
```

Le contenu entre crochets est un gabarit à remplacer, pas une preuve. Ne jamais committer avec un faux résultat de test. Proscrire `update`, `fix bugs`, `final`, `WIP tout le backend` comme seuls intitulés. Aucun commit atomique ne doit mélanger refonte de couleurs, auth et migrations sans lien.

Branches courtes, par exemple `feat/b19-resolution-concurrente`. Un développeur crée, l’autre relit. Le nombre d’agents ne vaut pas revue humaine. Pas de faux Co-authored-by, pas de signature à la place du collègue. Ne pas effacer ou rebaser le travail préexistant sans demande explicite. Aucune identité Git devinée.

Les tests peuvent être rouges pendant l’implémentation locale, pas dans un commit présenté comme prêt. En cas de limite de session avant état stable, laisser les changements identifiés et NON TERMINÉS ; ne pas prétendre avoir fini. Les sorties de commande trop volumineuses ne sont pas committées avec des secrets.

## 06. Backend : règles d’architecture

Cible du cahier : Laravel 13 / PHP 8.4 et PostgreSQL ; compatibilité exacte à vérifier au démarrage. Monolithe modulaire. Pas d’ajout de microservices, CQRS d’infrastructure, BaseRepository universel ou package de modularité pour l’apparence.

```text
backend/app/
  Data/{Identity,HelpRequests,Collaboration,Capsules,Lab}/
  Enums/{Identity,HelpRequests,Collaboration,Projects,Capsules,Lab,Moderation}/
  Http/Controllers/Auth/
  Http/Controllers/Api/V1/{Identity,HelpRequests,Collaboration,Projects,Capsules,Lab,Moderation}/
  Http/Requests/{Identity,HelpRequests,Collaboration,Capsules,Lab}/
  Http/Resources/{Identity,HelpRequests,Collaboration,Capsules,Lab}/
  Http/Middleware/
  Models/
  Policies/
  Queries/{HelpRequests,Capsules,Moderation}/
  Services/{Identity,HelpRequests,Collaboration,Projects,Capsules,Lab,Moderation}/
  Contracts/Lab/
  Infrastructure/Lab/
  Events/  Listeners/  Jobs/  Notifications/  Exceptions/  Support/
```

FormRequest valide et délègue aux Policies. DTO readonly transporte les valeurs validées, sans HTTP. Controller appelle service/query et retourne Resource, sans Eloquent direct hors binding/typage. Service `VerbeObjetService::handle(...)` exécute la règle transactionnelle. Query applique visibilité avant recherche/pagination. Models portent relations/casts/scopes simples. Resource ne livre jamais automatiquement tous les champs du modèle. Les services ne retournent pas JsonResponse et ne lisent pas Request. Les dépendances externes justifient des interfaces ; les CRUD triviaux non.

Controllers de commande dédiés : ResolveHelpRequestController, ReopenHelpRequestController, PublishCapsuleVersionController, StartLabRunController. Les noms exacts supplémentaires sont des propositions d’implémentation ; conserver une convention commune, pas plusieurs styles par développeur.

### États et intégrité

| Objet | Valeurs contractuelles proposées |
|---|---|
| Demande | draft, open, in_progress, resolved, archived |
| Proposition | proposed, accepted, not_selected |
| Version | draft, in_review, changes_requested, published, withdrawn |
| Run lab | queued, running, passed, failed, error, timed_out |
| Signalement | new, under_review, resolved, dismissed |
| Rôle | member, moderator, admin |

Les labels français restent en présentation. Les transitions sont vérifiées après relecture/verrouillage, pas seulement au moyen d’un enum. Tables pivots uniques, clés étrangères, lock_version et index partiel `resolutions(request_id) WHERE revoked_at IS NULL` complètent la règle applicative.

Le service de résolution ne peut accepter que la proposition de la même demande, par son auteur. Relecture verrouillée → vérification droits/version/état → nouvelle résolution et état → audit dans transaction → notifications après commit. Réouverture conserve l’historique, révoque l’active et signale les capsules. Un admin non auteur ne peut pas simuler une acceptation. Revue éditoriale indépendante même pour un admin.

### Authentification et données

Sanctum mode session pour la SPA de confiance ; `/sanctum/csrf-cookie`, `/login`, `/logout` et routes de compte hors préfixe métier. `/api/v1/me` retourne les données de compte nécessaires et permissions courantes. Cookies de session Secure/HttpOnly en production ; XSRF-TOKEN lisible selon le mécanisme CSRF. Rotation de session après connexion, invalidation après sortie/suspension. [S06]

ID utilisateur, rôle, état de revue et résultats de laboratoire proviennent du serveur. Rejeter les champs protégés. Les liens de reproduction HTTPS sont affichés, pas récupérés automatiquement. Code stocké comme texte inerte, HTML brut désactivé. Éviter le log de payloads complets. Une erreur technique reste 500/503 avec request_id, pas 200 avec success:false.

### Laboratoire et B2

Entrées lab : version approuvée + scénario d’une liste autorisée. Aucun code, shell, commande, URL, nom de classe ou dépendance fourni par utilisateur. Runtime/config/env du worker réellement séparés ; compte SQL restreint, fixtures dans base distincte. Une simple queue nommée lab ne prouve pas l’isolation. La queue doit aussi être isolée au niveau des accès retenus ; des permissions larges sur tous les jobs ne sont pas annoncées comme restriction effective.

Rapport : version, code_digest, suite_digest, scénario, identifiant, dates, durée, assertions attendues/observées, état terminal. Les tests n’héritent pas automatiquement sur une autre version. Les limits du cahier sont des bornes proposées à configurer/tester. Réconciliation des jobs orphelins et déduplication terminale ; afterCommit seul ne prouve pas une livraison fiable après crash.

B1 traite seulement des événements fictifs, pas une intégration de paiement réelle. B2 utilise son origine séparée ; cookies HAAS limités au parent de confiance .haas.example.com et non envoyés au démonstrateur B2 situé hors de ce parent. Ne pas transformer la plateforme en PWA qui met en cache les sessions, profils privés et modération.

### Atelier — règles prioritaires

Lire ATELIER_COLLABORATIF.md. F13 cas humain soumis ≠ scénario exécutable ; intégration par manifeste relu. F14 profil B1 approuvé, mêmes entrées/oracle/environnement, deux enfants frais baseline/candidate et aucun arbitraire. Comparaison = deux crédits du quota partagé, enfants séquentiels, une opération/membre. États opérationnels distincts des conclusions improved/unchanged/regressed/mixed/inconclusive. F15 fiche sans score, attribution de cas/diagnostic/correctif/documentation. Recontrôler les accès après retrait et conserver seulement un historique sûr. Aucun succès anticipé dans React. Les détails JSON/SQL, droits et AC figurent dans le contrat v2 du pack et le cahier consolidé.

## 07. Contrats et définition de « backend complet »

Base : JSON, UUID, UTC ISO 8601, préfixe métier `/api/v1`, enveloppe `data`, pagination `data/meta`, maximum 50. Tris et filtres en liste blanche. Les endpoints de référence du cahier restent compatibles :

```text
GET /me ; GET /technologies
GET|POST /requests ; GET|PATCH /requests/{id}
POST /requests/{id}/comments ; POST /requests/{id}/proposals
POST /requests/{id}/resolve ; POST /requests/{id}/reopen
POST /requests/{id}/archive
GET|POST /capsules ; GET /capsules/{slug}
POST /capsules/{id}/versions ; POST /versions/{id}/submit-review
POST /admin/versions/{id}/publish
GET /versions/{id}/artifact
POST /lab-runs ; GET /lab-runs/{id}
POST /versions/{id}/reuse-reports
PUT|DELETE /capsules/{id}/favorite
POST /reports ; PATCH /admin/reports/{id}
```

Cette liste n’est pas artificiellement traitée comme exhaustive : profil, lecture/édition de commentaires/propositions, demandes de corrections, retrait, notifications et gestion des comptes nécessitent leurs routes documentées/testées. Les préciser dans OpenAPI avec leurs droits, sans inventer de nouvelles fonctions métier. Ne pas créer une route publique pour écrire `passed`, `observed` ou un digest.

```json
{
  "error": {
    "code": "HELP_REQUEST_STALE_VERSION",
    "message": "Cette demande a été modifiée. Rechargez sa dernière version.",
    "fields": {}
  },
  "request_id": "identifiant-de-correlation"
}
```

L’enveloppe est mise en œuvre explicitement, y compris erreurs Laravel natives. Les statuts 401/403/404/409/419/422/429/503 conservent leur signification et leurs headers utiles. Les fixtures de documentation sont fictives.

La création/soumission idempotente associe acteur, route-cible normalisée, clé et empreinte de payload. Même clé/payload = même opération autorisée ; même clé/autre payload = 409. Le rejeu recontrôle les droits courants. B1 idempotence métier et idempotence de l’API HAAS ne sont pas une même table mélangée.

Gate backend : routes P0 toutes implémentées, validation/droits testés, migrations et contraintes testées PostgreSQL, parcours API A/B/C, B1 et API B2, sécurité, erreurs, OpenAPI et test d’installation. Puis état réel des contrôles Qodana/CI et accès externes ; revue de l’autre personne. Tests navigateur complets AC22/24/31/32 restent dans la phase F et ne sont pas déclarés passés avant elle.

## 08. Frontend : architecture obligatoire après le gate

```text
frontend/src/
  app/providers/  app/router/guards/  app/layouts/
  core/config/  core/http/interceptors/
  generated/api-types.ts
  features/{auth,help-requests,collaboration,capsules,lab,moderation,profiles,notifications}/
    api/ models/ schemas/ mappers/ services/ queries/ hooks/ components/ pages/ tests/
  shared/ui/ shared/components/ shared/hooks/ shared/utils/ shared/types/
  styles/
```

React/TypeScript strict/Vite, React Router, Axios, TanStack Query, RHF/Zod selon versions réellement verrouillées. Aucun changement vers Next.js et aucun ajout SWR parce qu’un skill Vercel le suggère. `services/` et `mappers/` existent seulement si une orchestration/conversion est utile. Services frontend sans hooks ; fonctions API sans UI ; composants sans connaissance d’Axios. Permissions reçues et recontrôlées Laravel, jamais inventées depuis localStorage.

Données serveur dans Query, formulaires dans RHF, état UI local dans useState/useReducer, filtres dans URL. Ne pas copier les données serveur dans un store persistant. Générer les types transport depuis OpenAPI et vérifier leur actualité ; ne pas modifier le généré. TypeScript ne remplace pas une validation runtime ni FormRequest.

Interceptors : normaliser les erreurs, injecter callbacks de session, nettoyer avec eject, propager les rejets et annulations. 403 n’est pas logout ; 503 n’est pas absence de session. 419/réseau ne rejouent pas toutes les mutations. Pour une même intention, conserver la clé ; une nouvelle action volontaire utilise une nouvelle clé. Réconcilier le résultat si le réseau a coupé après une possible écriture.

Guards : Auth, Guest, VerifiedEmail, ActiveAccount, Permission ; états distincts chargement/anonyme/connecté/erreur réseau. Les actions vérifiées ne bloquent pas les lectures publiques. Purger caches privés au logout/changement d’utilisateur, annuler lectures en cours, invalider les ressources concernées après mutation. Prévoir ownership/capabilities par ressource.

## 09. Design et expérience d’utilisation

Le produit doit être compris avant inscription. L’utilisateur voit d’abord ce qu’il peut faire : **Explorer**, **Demander de l’aide** et **Mes contributions**. « Capsule » est expliqué lors de sa première apparition : une solution documentée et versionnée. Les termes techniques digests/runner_key ne deviennent pas des labels de navigation.

Conserver la charte du cahier, pas un thème noir fluo ou des cartes décoratives génériques. Fond #F5F7F4, surfaces #FFFFFF, texte #102A2E, action #087F73, accent menthe #BFE8D5. La menthe n’est pas un fond de bouton à texte blanc. Compléments proposés : texte secondaire #506367, contrôle #718782, danger #B42318, focus/action sombre #06695F.

**Objectifs internes :** corps 16 px ou plus, petites métadonnées 14 px ou plus, interligne 1,5–1,65, lignes de lecture proches de 60–75 caractères, spacing base 8 px, actions tactiles 44 px. 44 px est le choix HAAS ; le critère WCAG 2.2 AA 2.5.8 porte sur 24 px avec exceptions. [S10]

Contraste ordinaire ≥4,5:1, grands textes et éléments non textuels concernés ≥3:1. Contrôler valeur brute non arrondie. La bordure #DCE5DF sert aux séparateurs décoratifs ; utiliser #718782 lorsqu’une bordure identifie à elle seule un champ sur fond clair. Le calcul de tokens ne valide pas à lui seul les états réels, opacités, syntaxe de code ou toute la conformité WCAG. [S09, S11]

Chaque écran : chargement, vide, contenu, erreur et accès interdit. Chaque formulaire : labels visibles, aide concise, erreurs reliées, résumé et focus première erreur, reprise sans perte sûre. Les warnings expliquent quoi faire. États de succès uniquement après confirmation correspondant au libellé. Pas de placeholders en guise de labels, de texte tronqué essentiel, de spinner infini, de checkbox présélectionnée engageante ou de toast seul pour une erreur critique.

Signature visuelle propre à HAAS : **progression de la demande vers la solution**, et **panneau de preuve lié à la version**. Les visuels doivent servir ces deux éléments ; ne pas ajouter de faux graphiques ou de compteurs pour remplir l’espace. Les tableaux de lab se lisent en cartes de cas sur mobile. Le code défile dans son bloc, jamais toute la page.

Tests UX : 320/360/390/768/1280 px, clavier, focus, zoom 200 %, reflow à 400 % quand applicable, tailles de texte, contraste réel, réduction du mouvement. Captures du navigateur réellement exécuté. Tester au minimum le parcours central à 4 Mbit/s et 150 ms de latence, selon le cahier. Cible de chargement et budget mesurés, pas prétendus atteints.

### Textes de référence

| Situation | Texte proposé |
|---|---|
| Promesse | Une solution. Des cas concrets. Une amélioration visible. |
| Sous-titre | Apportez votre solution, proposez un cas à vérifier et partagez ce qui a été observé. |
| Bouton principal | Demander de l’aide |
| Sauvegarde serveur | Votre brouillon est enregistré. |
| Sauvegarde B2 locale | Conservée sur cet appareil. Pas encore envoyée. |
| Erreur réseau ambiguë | La confirmation n’a pas été reçue. Vérifiez l’état de la demande avant de réessayer. |
| Conflit | Cette demande a été modifiée. Rechargez la dernière version pour continuer. |
| Acceptation | Cette proposition a aidé l’auteur dans son contexte. |
| Rapport positif | Tests réussis pour cette version. |
| Laboratoire technique en panne | Le test n’a pas pu se terminer. Aucun résultat n’est disponible. |
| Retrait | Cette version a été retirée. Consultez le motif et les autres versions disponibles. |

## 10. Skills : utilisation ciblée, pas installation aveugle

Les skills locaux du pack sont des instructions HAAS rédigées pour cette demande : backend-delivery, react-architecture, interface-design, accessibility, french-ux-writing, ui-review, atomic-commits, release-readiness et collaborative-verification (atelier). Ils utilisent des `SKILL.md` avec name/description sous `.agents/skills/`. Ce format et ce chemin sont documentés par OpenAI. [S01]

Le catalogue externe conserve une sélection de pistes documentées lors de la préparation initiale, à revérifier avant installation, pas « tous les skills existants ». Il ne prétend pas qu’ils sont installés. Options : frontend-design d’Anthropic ; web-design-guidelines, react-best-practices et composition-patterns de Vercel ; playwright, screenshot et options Figma publiées dans openai/skills.

Installer seulement après lecture du contenu et des scripts, provenance/version/licence vérifiées, dépendances listées et permissions approuvées. Consigner commit SHA/digest réellement retenu. Pas de `curl | sh`, téléchargement automatique du catalogue entier, outil global à version flottante ou transfert de code vers un service pour prétendre « activer un skill ». Des conditions spécifiques sont déclarées dans la licence actuelle de figma-implement-design ; ne pas le classer Apache par hypothèse. [S13]

Les guides génériques n’annulent jamais l’ordre backend-first, les contraintes de sécurité, la charte de P1 ni la stack React SPA. Les conseils Next.js/RSC/server actions/SWR ne s’appliquent pas mécaniquement. Un skill n’est pas une preuve de test, ni une source de secrets, ni une garantie de bonne interface. Figma n’est pas un prérequis ; sans maquette accessible, construire à partir des spécifications HAAS.

## 11. Qualité, CI et conditions de sortie

Backend : formatage, analyse statique, tests unitaires/Feature/PostgreSQL/concurrence, contraintes et Policies. Frontend : ESLint/formatage/TypeScript strict/Vitest/Testing Library proposés, build, Playwright contre Laravel et audit a11y. Qodana selon droits d’usage confirmés ; aucune analyse absente marquée verte. [S07]

Mettre à jour docs/COMMANDS.md avec scripts effectivement configurés, par exemple `composer lint`, `composer analyse`, `composer test`, `composer test:integration`, `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build`, `npm run test:e2e`. Ces noms sont une cible de configuration : ne pas affirmer leur existence avant création. Les contrôles d’un lot documentaire n’imposent pas une reconstruction intégrale ; les contrôles d’une modification de sécurité sont renforcés.

CI : lockfiles, actions à référence vérifiée, permissions minimales, jobs isolés, pas de secret sur code non approuvé, pas de `pull_request_target` lançant la PR avec secrets. Les reports sensibles restent privés. Un seuil de qualité n’est pas affaibli pour masquer un défaut. [S08]

Recette : AC01–AC32 gardent leurs identifiants ; AC33–AC52 couvrent l’atelier ; AC53–AC68 complètent la communauté (AC47 conditionnel au P1). La matrice précise phase backend/frontend/exploitation et résultat attendu. Chaque test indique version, environnement, attendu/observé, commande ou procédure, date, preuve. Les tests automatiques d’accessibilité détectent une partie des défauts ; compléter par revue manuelle et tests utilisateurs, sans déclaration de conformité globale non démontrée. [S12]

Déploiement et achat restent soumis aux autorisations utilisateur. Les backups sont testés en restauration sur autre environnement. Le retour au code précédent ne suppose pas une migration inverse destructive. Les packages, types, screenshots ou videos correspondent au SHA présenté.

## 12. Plan de développement en petits commits

122 lots S2/B72/F40/R8, 106 identifiants antérieurs conservés.

### Phase S — Préparation

#### S01 — Inventaire et environnement

**Commit proposé :** `docs(setup): inventorier le dépôt et les contraintes`

**À réaliser.** Inspecter git status, branche, fichiers déjà présents et versions PHP/Composer/Node/npm/PostgreSQL. Lire les instructions existantes. Relever accès hébergement, courriel, Qodana et protections GitHub sans lire ou afficher de secrets. Créer VERSIONS.md et BLOCKERS.md avec valeurs réellement observées. Déploiement retenu : Relever la configuration d’un seul VPS Systalink, le domaine de confiance, le projet Vercel et son forfait. Ultimate est déclaré existant : vérifier le projet Qodana et le token sans l’afficher. Consigner panier/remise/taxes inconnus. Alignement mail : Lire le mail [M] et distinguer usages source/choix de conception ; relever état réel des projets/profils/question dans le dépôt. Coup de main : Lire ADR-006 et inspecter offres/consentements éventuels sans écraser le dépôt.

**Critères de sortie.** Aucune suppression ni réinitialisation ; les commandes indisponibles sont marquées NON_EXÉCUTÉ. Un dépôt existant n’est jamais rebaptisé pour simuler une création neuve. Déploiement retenu : Aucun compte ou achat présumé ; le budget distingue Systalink, Vercel et frais non chiffrés. Alignement mail : Aucune lecture inbox ni nouveau cahier technique de l’organisateur inventé.

**Traçabilité.** N05/N06 ; ARB01–ARB06 ; ADR-004 / DEP ; ADR-005 / [M]

#### S02 — Cadre d’exécution

**Commit proposé :** `docs(plan): fixer la séquence backend puis frontend`

**À réaliser.** Installer les consignes après comparaison avec les fichiers existants, conserver les deux sources du projet, adopter ADR-001 et créer le suivi. Définir branches courtes, propriétaire/relecteur de chaque lot et commandes de travail locales. Ne pas générer encore d’application React. Déploiement retenu : Appliquer ADR-004, qui remplace les deux VPS et la même origine. Répartir les douze sous-lots DEP sans renuméroter le suivi. Alignement mail : Adopter ADR-005 ; intégrer BC01–BC08 et FC01–FC04 sans effacer commits/statuts existants. Coup de main : Intégrer BH01–10, FH01–05 et RH01, sans renuméroter les identifiants existants ni convertir une ancienne revue en GO_FRONTEND.

**Critères de sortie.** Les deux personnes identifient la prochaine tâche, les preuves attendues et le point de passage BACKEND_GATE. Les inconnues juridiques, délais et budget restent explicites. Déploiement retenu : Ordre backend-first inchangé ; revue et autorisations humaines conservées. Alignement mail : 122 lots proposés ; charge réestimée et périmètre communautaire reçu.

**Traçabilité.** F01–F12 ; N05 ; ADR-004 / DEP ; ADR-005 / [M]

### Phase B — Backend complet

#### B01 — Squelette Laravel

**Commit proposé :** `chore(backend): initialiser Laravel et PostgreSQL`

**À réaliser.** Initialiser Laravel dans backend en préservant AGENTS.md ; configurer PostgreSQL, .env.example, identifiants UUID, date UTC et health minimal. Choisir les versions exactes compatibles. Aucune clé ni mot de passe réel dans les fichiers suivis.

**Critères de sortie.** Installation reproductible sur base dédiée vierge ; application démarre ; route health répond sans détail sensible ; composer.lock présent. Pas de changement silencieux de stack.

**Traçabilité.** N04/N05

#### B02 — Qualité PHP locale

**Commit proposé :** `chore(quality): configurer les contrôles PHP locaux`

**À réaliser.** Configurer Pint, PHPStan/Larastan compatibles et PHPUnit ; scripts Composer lint, analyse, test et test:integration. Créer un premier test d’architecture et interdire les dépendances HTTP dans Data/Services. Vérifier les licences des outils.

**Critères de sortie.** Les scripts annoncés existent et réussissent sur le squelette. Une violation témoin est détectée puis retirée. Aucun ignore général d’erreurs.

**Traçabilité.** N05/N06

#### B03 — CI backend initiale

**Commit proposé :** `ci(backend): exécuter les tests sur PostgreSQL`

**À réaliser.** Créer backend-ci avec service PostgreSQL isolé, installation verrouillée, formatage, analyse et tests. Actions épinglées sur références vérifiées, permissions minimales, aucun secret de production.

**Critères de sortie.** Exécution locale documentée ; CI distante constatée seulement si accessible. Un workflow non exécuté ne devient pas vert. Les contrôles requis ne restent pas indéfiniment pending à cause d’un filtre de chemins.

**Traçabilité.** AC28 ; N05

#### B04 — Contrat HTTP et erreurs

**Commit proposé :** `feat(api): normaliser les erreurs et la corrélation`

**À réaliser.** Configurer /api/v1, ApiExceptionRenderer, request_id, pagination et limites. Normaliser validation, auth, interdiction, conflit, CSRF, quotas et pannes ; préserver Retry-After. Commencer OpenAPI avec les schémas communs.

**Critères de sortie.** Tests 401/403/404/409/419/422/429/500/503 ; aucune trace SQL ni chemin interne en réponse. Une route API inconnue n’est pas du HTML de SPA.

**Traçabilité.** AC03/AC05 ; N01

#### B05 — Identité et référentiels

**Commit proposé :** `feat(identity): définir comptes rôles et états`

**À réaliser.** Créer User/Profile/Technology et enums rôle/état. Courriel privé normalisé unique, handle unique, statut actif/suspendu, email_verified_at et is_demo. La correspondance email_verified_at avec le champ conceptuel verified_at du cahier est documentée.

**Critères de sortie.** Contraintes et casts testés sur PostgreSQL ; routes d’inscription ne peuvent recevoir rôle/admin, author_id ou email_verified_at. Technologies évolutives en table, pas enum.

**Traçabilité.** AC04 ; F01/F11

#### B06 — Inscription

**Commit proposé :** `feat(auth): inscrire un membre sans élévation de droits`

**À réaliser.** FormRequest et service d’inscription, acceptation versionnée des conditions, pseudonyme 3–30 caractères, mot de passe 12–128 caractères. Utiliser le hasher Laravel adapté à la longueur Unicode supportée ; ne jamais tronquer silencieusement un mot de passe.

**Critères de sortie.** Compte membre créé, courriel non exposé ; doublon et champs protégés refusés. Mot de passe long vérifié réellement et jamais journalisé.

**Traçabilité.** AC01/AC04

#### B07 — Sessions et CSRF

**Commit proposé :** `feat(auth): sécuriser les sessions Sanctum`

**À réaliser.** Configurer le mode SPA : csrf-cookie, login, logout, session régénérée/invalidation. Limiter les tentatives de connexion. Tester les deux origines de confiance et la configuration locale cohérente. Pas de JWT ni token navigateur persistant. Déploiement retenu : Configurer Sanctum pour SPA Vercel et API Systalink sur sous-domaines de confiance. CORS exact, paths auth et API, XSRF, credentials, redirections et erreurs avec CORS.

**Critères de sortie.** Tests cookies/CSRF avec le middleware effectivement actif, pas seulement des tests Laravel qui le désactivent par défaut. Login valide/invalide, logout et tentative forgée vérifiés. Déploiement retenu : Tester origines autorisées/interdites, prévols, CSRF actif et absence de token localStorage ; garder la recette sur domaines réels pour DEP-AC02.

**Traçabilité.** AC01/AC02 ; N01 ; ADR-004 / DEP

#### B08 — Courriels de compte

**Commit proposé :** `feat(auth): vérifier le courriel et réinitialiser le mot de passe`

**À réaliser.** Configurer liens signés expirants, renouvellement limité, mot de passe oublié et réinitialisation à usage unique via mécanismes Laravel. Réponse de demande de reset indépendante de l’existence du compte. Transport local de test séparé du service réel. Déploiement retenu : Construire les liens depuis APP_URL API et FRONTEND_URL autorisée. Ne pas altérer l’hôte d’une URL signée pour l’envoyer au frontend.

**Critères de sortie.** Expiration, signature altérée, lien déjà consommé et throttling vérifiés ; compte non vérifié ne publie pas. Délivrabilité réelle est un contrôle d’environnement, pas une assertion tirée d’un mail fake. Déploiement retenu : Aucune redirection ouverte, lien valide vers le bon environnement ; aucune donnée de compte révélée.

**Traçabilité.** AC01/AC02 ; ADR-004 / DEP

#### B09 — Autorisations et compte courant

**Commit proposé :** `feat(identity): exposer la session et contrôler les capacités`

**À réaliser.** Créer /me, MeResource, EnsureAccountIsActive et Policies de départ. Séparer lecture publique et actions vérifiées. Définir capacités par ressource sans Gate::before universel. Prévoir le canal d’information pour compte suspendu.

**Critères de sortie.** Visiteur, non vérifié, membre, modérateur et admin ont des tests négatifs. Un admin ne devient pas auteur d’une demande par son rôle.

**Traçabilité.** AC02/AC03/AC04/AC09

#### B10 — Profils et technologies

**Commit proposé :** `feat(profiles): publier des profils sans données privées`

**À réaliser.** API de profil public et modification de son propre profil : bio 500 caractères, 8 technologies maximum, langue, pays facultatif, lien GitHub HTTPS. Avatars initiales ; compteurs issus des contributions réelles. Alignement mail : Garder des profils réutilisables par l’annuaire volontaire ; préférences détaillées dans BC06.

**Critères de sortie.** Adresse de courriel, IP, hash, tokens et état privé exclus du profil public. Nombre de contributions non contrôlable depuis le client ; limites testées. Alignement mail : Compte courant et Resource publique séparés.

**Traçabilité.** F11 ; AC27 ; ADR-005 / [M]

#### B11 — Schéma de collaboration

**Commit proposé :** `feat(requests): créer les tables de demandes et résolutions`

**À réaliser.** Migrations help_requests, technologies associées, comments, proposals et resolutions. UUID, foreign keys, lock_version, états et index utiles. Ajouter l’unicité partielle d’une résolution active et documenter la contrainte d’appartenance proposition/demande.

**Critères de sortie.** Migration sur base neuve ; index partiel testé ; référence croisée interdite par les contrôles/contraintes retenus. Rollback de test uniquement sur base dédiée.

**Traçabilité.** AC10 ; F02/F03/F04

#### B12 — Audit et révisions

**Commit proposé :** `feat(audit): tracer les modifications métier autorisées`

**À réaliser.** Créer AuditWriter et content_revisions avec liste blanche de métadonnées. Les services écrivent l’événement métier dans leur transaction. Prévoir le retrait de données secrètes sans les recopier dans l’historique.

**Critères de sortie.** Rollback annule données et audit ; aucun mot de passe, token, cookie ni corps complet de requête journalisé. Acteur et date proviennent du serveur.

**Traçabilité.** N07 ; AC25

#### B13 — Idempotence des commandes

**Commit proposé :** `feat(api): dédupliquer les commandes par intention`

**À réaliser.** IdempotencyService et stockage utilisateur/route-cible/clé/empreinte/réponse. Contrôle concurrent par unicité et transaction, expiration 24 h proposée. Autorisation courante recontrôlée avant le rejeu. Ne pas conserver de réponse privée au-delà du besoin.

**Critères de sortie.** Même clé et même payload : une écriture ; payload différent : 409 ; deux utilisateurs n’accèdent pas à la réponse de l’autre ; cas réellement concurrent testé.

**Traçabilité.** AC06/AC10 ; N01

#### B14 — Créer une demande

**Commit proposé :** `feat(requests): enregistrer un brouillon ou une demande`

**À réaliser.** StoreHelpRequestRequest, CreateHelpRequestData/Service et Resources. Appliquer les limites du cahier, code inerte et contrôle indicatif de secrets. Distinguer explicitement l’intention brouillon/publication dans OpenAPI ; aucun state libre en PATCH. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA.

**Critères de sortie.** AC05/AC06 : validations et double envoi ; brouillon privé et publication visible selon Policy. Champs protégés rejetés. Les règles allégées du brouillon sont écrites dans le contrat, pas implicites.

**Traçabilité.** AC03–AC07 ; F02

#### B15 — Lire et rechercher les demandes

**Commit proposé :** `feat(requests): filtrer les lectures selon la visibilité`

**À réaliser.** ListHelpRequestsQuery et FindVisibleHelpRequestQuery, recherche titre/contenu autorisé/technologies, filtre état, tri autorisé et pagination 20 max 50. Eager loading des relations nécessaires.

**Critères de sortie.** Brouillons d’autrui et contenus masqués absents du détail, listes, résultats et compteurs. Requête avec tri arbitraire ou page_size excessif contrôlée ; pas de N+1 sur le jeu de référence.

**Traçabilité.** AC03/AC15/AC25 ; F12

#### B16 — Modifier une demande

**Commit proposé :** `feat(requests): protéger l’édition par version`

**À réaliser.** UpdateHelpRequestRequest/Data/Service. Comparaison lock_version, version incrémentée et révision. Clarifications après contributions avec note de modification. La publication d’un brouillon a une commande documentée distincte si nécessaire. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA.

**Critères de sortie.** Édition par non auteur refusée ; conflit 409 ne change rien ; les champs d’état/résolution/auteur ne sont pas modifiables. Historique cohérent.

**Traçabilité.** AC03/AC05 ; F02

#### B17 — Commentaires

**Commit proposé :** `feat(collaboration): enregistrer des commentaires historisés`

**À réaliser.** API création et édition de ses commentaires ; Markdown restreint 1–4 000 caractères, code traité comme texte, idempotence et version d’édition. Notifications préparées par événement. Un commentaire seul ne change pas l’état de la demande.

**Critères de sortie.** Double requête ne duplique pas ; auteur tiers refusé ; HTML reste inerte ; commentaire sur contenu non accessible interdit.

**Traçabilité.** AC06/AC07 ; F03

#### B18 — Propositions de solution

**Commit proposé :** `feat(collaboration): structurer les propositions de résolution`

**À réaliser.** Proposals : diagnostic/correctif/vérification/limites chacun 20–4 000 caractères, code facultatif 12 000. Première proposition d’une demande ouverte la fait passer en cours. Interdire l’édition silencieuse d’une proposition acceptée.

**Critères de sortie.** Une simple URL ne remplace pas les champs ; une proposition valide modifie l’état une seule fois ; tests de propriété et d’idempotence.

**Traçabilité.** AC08 ; F03/F04

#### B19 — Accepter une proposition

**Commit proposé :** `feat(resolutions): accepter une solution sous verrou`

**À réaliser.** ResolveHelpRequestService transactionnel : relecture verrouillée, Policy, lock_version, appartenance proposition, une résolution active, attribution/audit. Self-resolution admise et étiquetée ; notifications après commit.

**Critères de sortie.** Auteur seulement y compris face à admin ; proposition étrangère rejetée ; deux acceptations simultanées ne créent pas deux résolutions ; rollback complet.

**Traçabilité.** AC09/AC10 ; RM01/RM02

#### B20 — Rouvrir une demande

**Commit proposé :** `feat(resolutions): conserver l’historique des réouvertures`

**À réaliser.** Commande reopen avec motif et version. Révoquer la résolution active, remettre l’état selon contrat, conserver les preuves datées et marquer les capsules liées à revoir. Partager l’ordre de verrous avec résolution/édition.

**Critères de sortie.** Ancienne résolution conservée ; capsule signalée sans faux retrait automatique ; course reopen/resolve testée ; non auteur refusé.

**Traçabilité.** AC11 ; F04

#### B21 — Archiver et non-retenir

**Commit proposé :** `feat(collaboration): motiver l’archivage et le rejet`

**À réaliser.** Commandes explicites d’archivage et de proposition non retenue avec motif. Distinguer clôture administrative et résolution par l’auteur. Autorisations propres à chaque action.

**Critères de sortie.** Archivage ne crée ni résolution ni preuve. Décision conservée avec acteur/motif ; refus d’un tiers ; états incompatibles donnent conflit.

**Traçabilité.** F03/F04/F09 ; N07

#### B22 — Schéma des capsules

**Commit proposé :** `feat(capsules): versionner les capsules et contributions`

**À réaliser.** Créer capsules, capsule_versions, capsule_contributors et artefacts. Source demande résolue ou origine éditoriale explicite, slug, version unique, provenance, limites et technologies. Enums distincts de ceux des demandes.

**Critères de sortie.** Clés/contraintes de version et contributeurs testées. Une capsule, sa brique facultative et son laboratoire facultatif restent trois objets distincts.

**Traçabilité.** F05/F06 ; AC13

#### B23 — Brouillons de capsule

**Commit proposé :** `feat(capsules): créer des versions documentées`

**À réaliser.** Création/édition d’un brouillon par auteur ou contributeur habilité selon contrat ; diagnostic, procédure, versions compatibles, limites et provenance. Ressources distinctes publiques/édition.

**Critères de sortie.** Brouillon absent du catalogue public ; source étrangère non autorisée refusée ; données requises et version testées. Aucun kit upload utilisateur ajouté.

**Traçabilité.** F05 ; AC03/AC12

#### B24 — Soumettre à la revue

**Commit proposé :** `feat(capsules): organiser une revue indépendante`

**À réaliser.** Transitions draft → in_review → changes_requested → in_review, avec note du réviseur habilité. Aucun auteur ne valide seul sa revue. Journal des changements et contrôle de diffusion.

**Critères de sortie.** Transitions illégales et auto-revue refusées même pour un admin. Revue ne signifie pas publication ni certification de sécurité.

**Traçabilité.** AC12 ; F06

#### B25 — Publier une version immuable

**Commit proposé :** `feat(capsules): figer les versions publiées`

**À réaliser.** PublishCapsuleVersionService vérifie revue indépendante, documentation, provenance et autorisation du kit. Publier atomiquement ; correction = nouvelle version ; ne jamais hériter automatiquement des tests.

**Critères de sortie.** AC12/AC13 : même admin auteur ne contourne pas ; impossible d’écraser le body publié ; anciennes preuves toujours rattachées à l’ancien digest.

**Traçabilité.** AC12/AC13 ; RM03

#### B26 — Catalogue de capsules

**Commit proposé :** `feat(capsules): rechercher les versions visibles`

**À réaliser.** Queries liste/détail avec slug et version choisie ; filtres technologie et présence de laboratoire pour cette version ; historique de versions autorisées ; absence de cache partagé contenant des permissions personnelles.

**Critères de sortie.** Version retirée introuvable dans recherche normale ; badge de laboratoire ne fuit pas sur une autre version ; aucune capsule inventée sur résultat vide.

**Traçabilité.** AC15/AC25 ; F12

#### B27 — Téléchargement contrôlé

**Commit proposé :** `feat(artifacts): contrôler l’accès aux kits versionnés`

**À réaliser.** Stockage privé et téléchargement via contrôle serveur courant, digest et notices. Aucun ZIP soumis par utilisateur. distribution_status inactif tant que les conditions d’évaluation ne sont pas approuvées ; message explicite plutôt que faux succès.

**Critères de sortie.** Retrait interdit immédiatement nouvel accès par ancienne URL de l’API ; un lien signé seul ne contourne pas le retrait. Aucun chemin disque divulgué ; fichiers déjà téléchargés non prétendument révocables.

**Traçabilité.** AC15/AC25 ; ARB05

#### B28 — Favoris et réutilisation

**Commit proposé :** `feat(capsules): rattacher favoris et retours aux versions`

**À réaliser.** PUT/DELETE favori idempotent et privé ; un retour actif par utilisateur/version, environnement/date/résultat/commentaire et historique. Contributions internes étiquetées ; pas de points pour auto-validation.

**Critères de sortie.** Autrui ne lit pas les favoris ; doublons contrôlés ; retour humain distinct d’un rapport machine ; version obligatoire et autorisée.

**Traçabilité.** AC14/AC27 ; F07/F11

#### B29 — Notifications internes

**Commit proposé :** `feat(notifications): livrer les événements sans doublons`

**À réaliser.** Liste paginée, compteur et marquage lu/non lu. Abonner les événements proposition/acceptation/réouverture/revue/laboratoire. Dédupliquer par destinataire/événement et documenter la reprise après transaction.

**Critères de sortie.** Échec d’envoi ne perd pas la résolution ; aucun code/secret dans notification ; impossible de lire ou marquer celle d’un autre membre. Retry ne duplique pas.

**Traçabilité.** AC08 ; F10

#### B30 — Signalements

**Commit proposé :** `feat(moderation): recueillir des signalements ciblés`

**À réaliser.** Créer reports avec ressource, catégorie et texte 20–1 000 ; un actif par membre/ressource ; liste admin avec filtres état/date/catégorie. Pas d’exposition publique du signalement privé.

**Critères de sortie.** Ressource autorisée requise ; doublon et abus bornés ; membre normal n’accède pas à la file de modération.

**Traçabilité.** AC25 ; F09

#### B31 — Retrait de contenus

**Commit proposé :** `feat(moderation): retirer les contenus avec motif`

**À réaliser.** Actions motivées : sans suite, correction, masquer, retirer version. Invalider les caches et accès aux kits ; expurger secrets de l’historique exposé selon procédure. Préserver l’audit nécessaire sans recopier le secret.

**Critères de sortie.** URL directe, recherche, notification liée et ancien lien de kit vérifiés après retrait. Pas de simple masquage React ; révocation du secret à la source expliquée.

**Traçabilité.** AC15/AC25 ; N01

#### B32 — Suspensions et rôles

**Commit proposé :** `feat(identity): révoquer les sessions des comptes suspendus`

**À réaliser.** Commandes admin de suspension/changement sensible de rôle avec motif/audit et révocation des sessions. Prévenir un admin de se verrouiller hors du seul accès d’exploitation selon règle documentée.

**Critères de sortie.** Sessions précédentes invalidées ; aucune écriture/lab possible ; message d’information accessible sans exposer les données privées. Modérateur ne suspend pas.

**Traçabilité.** AC26 ; F09

#### B33 — Registre du laboratoire

**Commit proposé :** `feat(lab): définir les exécutions et scénarios approuvés`

**À réaliser.** LabDefinition/Run/Result, enums, runner registry fixe, digests code/suite/version, configuration kill switch. Connexions de contrôle et fixtures restreintes. Pas de classe de runner déterminée par l’utilisateur.

**Critères de sortie.** Scénario inconnu, version retirée ou disabled refusés ; aucune API publique d’écriture des résultats. Schéma de rapport documenté.

**Traçabilité.** AC16/AC18 ; F08

#### B34 — Lancement et quotas

**Commit proposé :** `feat(lab): borner les lancements et la concurrence`

**À réaliser.** StartLabRunService, clé idempotence, réponse 202 et suivi. Une exécution active/membre, deux globales, cinq lancements/heure/membre comme limites proposées. Contrôle atomique des places actives et queue bornée. Déploiement retenu : Appliquer LAB_MAX_RUNNING=1 pour les runs simples et le comparateur ; cinq unités/h/membre, une ou deux unités selon opération.

**Critères de sortie.** Deux appels simultanés ne dépassent pas les quotas ; doublon n’occupe pas deux places ; 429 conserve Retry-After ; code/URL/command transmis = 422. Déploiement retenu : Deux lancements concurrents ne dépassent pas un run actif global ; réserver sans double comptage au rejeu.

**Traçabilité.** AC18/AC19 ; F08 ; ADR-004 / DEP

#### B35 — Brique B1

**Commit proposé :** `feat(b1): traiter les événements fictifs sans doublon`

**À réaliser.** Créer le module Laravel approuvé : event_id/order_ref/amount_minor/currency, transaction et unicité (run_id,event_id). Fixture entièrement fictive. B1-01 nominal, 02 doublon, 03 distincts, 04 invalide, 05 concurrence.

**Critères de sortie.** B1-01 à 04 passent sur PostgreSQL ; aucun paiement réel. Traitements distincts donnent deux commandes ; invalidation aucune écriture partielle. Tests du défaut pédagogique séparés.

**Traçabilité.** AC16/AC21 ; B1-01–B1-05

#### B36 — Worker et rapports réels

**Commit proposé :** `feat(lab): exécuter les scénarios sur un worker restreint`

**À réaliser.** ExecuteLabRunJob/Service via registry connu. Runtime du worker séparé sans .env applicatif lisible ni identifiants d’administration ; fixtures séparées. Rapport attendu/observé/digests/date/durée ≤64 Ko. Déploiement retenu : Implémenter le runner comme service local séparé sous haas-lab : paquet approuvé distinct, canal Unix borné, rôle SQL haas_lab, aucun bootstrap Laravel principal ni accès aux secrets APP.

**Critères de sortie.** Runs séparés ; résultat recalculé ; service exécuteur incapable de lire users/contenus privés (l’orchestrateur Laravel reste côté APP). Tester passed ET failed ; jamais une constante passée pour simuler le calcul. Déploiement retenu : Exécuter les tests négatifs d’accès aux fichiers et haas_app sous l’identité runner. Une base distincte seule n’est pas une isolation.

**Traçabilité.** AC16/AC17/AC21 ; N01 ; ADR-004 / DEP

#### B37 — Pannes du laboratoire

**Commit proposé :** `fix(lab): réconcilier les tâches interrompues`

**À réaliser.** Temps cible 15 s et abandon technique >20 s ; retry_after cohérent, tentatives bornées, claim atomique et finalisation unique. Réconciliation queued/running orphelins ; purge fixtures au plus tard 24 h. Déploiement retenu : Vérifier la perte du service local, le timeout, le verrou global et la continuité de l’API pendant un test.

**Critères de sortie.** Arrêt brutal, notification perdue et tentative rejouée n’engendrent ni faux succès ni double résultat terminal. Échec fonctionnel distinct de panne technique/délai. Déploiement retenu : Jamais un faux résultat positif ; file bornée et reprise observable ; mesurer la charge avant ouverture.

**Traçabilité.** AC19/AC20 ; N08 ; ADR-004 / DEP

#### B38 — API de démonstration B2

**Commit proposé :** `feat(b2): confirmer les reprises de commande fictive`

**À réaliser.** Construire uniquement l’API de démonstration, contrat JSON et idempotence par clé stable ; origine/runtime/configuration distincts de la session HAAS. Pas de composant React avant BACKEND_GATE. Déploiement retenu : API B2 cookie-free sur hôte hors .haas.example.com, sous un compte/pool et une base fictive distincts, même VPS.

**Critères de sortie.** Deux envois avec même clé donnent une seule commande fictive. Payload différent rejeté. Aucune session HAAS utilisée et données bornées/purgées. Déploiement retenu : Aucun accès aux sessions/comptes HAAS ; uniquement clés stables et entrées bornées de démonstration.

**Traçabilité.** AC23 ; B2 backend ; ADR-004 / DEP

#### BC01 — Schéma des projets et découverte volontaire

**Commit proposé :** `feat(community): ajouter les données des projets et de découverte`

**À réaliser.** Créer projects/project_technologies, états séparés, préférences de profiles et FK facultative help_requests.project_id. Étendre les enums et contraintes selon COMMUNAUTE_ET_PROJETS. Migration non destructive et factories.

**Critères de sortie.** Migrations montée/base neuve et données antérieures testées sur PostgreSQL ; uniques/FK valides ; opt-in false par défaut ; pas de données privées seedées.

**Traçabilité.** F16/F17 ; AC53/AC60

#### BC02 — Brouillons et édition de projet

**Commit proposé :** `feat(projects): créer et modifier ses fiches projet`

**À réaliser.** Requests/DTO/Policy/Services/Resources pour créer un brouillon et éditer ses champs. Réutiliser IdempotencyService, audit, lock_version et référentiel technologies. Liens externes inertes.

**Critères de sortie.** Tests ownership, mass assignment, validation, double création, conflit 409 et absence de fetch/clonage serveur. Aucune identité client acceptée.

**Traçabilité.** F16 ; AC53/AC54/AC56/AC57

#### BC03 — Publication et catalogue des projets

**Commit proposé :** `feat(projects): publier et rechercher les projets`

**À réaliser.** Commandes de publication/archivage ; Queries list/detail avec visibilité, filtres whitelist, pagination bornée et ordre stable ; Resources publiques/capacités.

**Critères de sortie.** Brouillons/masqués absents, publication conditionnelle, état de phase déclaratif, archivage non destructif et aucun compteur privé.

**Traçabilité.** F16 ; AC53/AC55/AC56

#### BC04 — Demandes liées à un projet

**Commit proposé :** `feat(collaboration): relier une demande à son projet`

**À réaliser.** Étendre création/édition de demande et GET projets/{id}/requests ; seul propriétaire relie sa demande à son projet publié. Lien stable après publication. Réutiliser discussion existante, sans nouveau fil projet.

**Critères de sortie.** Tiers/projet masqué/archivé privés refusés ; lien facultatif accepté ; modifications concurrentes et compteurs sous visibilité ; demandes autonomes inchangées.

**Traçabilité.** F16/F02 ; AC58/AC67

#### BC05 — Modération des projets et visibilité liée

**Commit proposé :** `feat(moderation): protéger la visibilité des projets et échanges`

**À réaliser.** Ajouter project aux types de signalement autorisés ; masquer/restaurer avec audit et motif. Appliquer visibilité parent aux demandes/cas/comments/proposals, caches/notifications ; expurger sources de capsules et traiter secrets selon règle existante.

**Critères de sortie.** Tester accès URL, listes, caches et demandes liées après masquage/suspension ; restauration ne republie pas un brouillon. Modérateur ne réécrit pas propriétaire ni solution.

**Traçabilité.** F16/F09 ; AC54/AC59/AC62

#### BC06 — API de découverte des développeurs

**Commit proposé :** `feat(developers): exposer un annuaire volontaire`

**À réaliser.** Étendre UpdateProfileRequest/Data/Service pour préférences whitelist ; GET /developers et DeveloperSummaryResource avec actif+vérifié+opt-in, filtres technologie/disponibilité et pagination.

**Critères de sortie.** AC60–63 passent : pas d’email/last_login/IP, retrait cache, profil tiers non modifiable, aucune promesse online ni classement ; types OpenAPI mis à jour.

**Traçabilité.** F17 ; AC60/AC61/AC62/AC63

#### BV201 — Schéma des cas et intentions

**Commit proposé :** `feat(cases): définir les cas versionnés et leurs parents`

**À réaliser.** Ajouter help_intent aux demandes, VerificationCase/Revision, enums, DTO, migrations et contraintes parent XOR, révisions uniques, auteur serveur. Conserver les anciennes données et états.

**Critères de sortie.** Migrations neuves et incrémentales passent ; parents invalides interdits ; aucun champ protected assignable.

**Traçabilité.** F13 ; AC33–AC35

#### BV202 — Créer et soumettre un cas

**Commit proposé :** `feat(cases): publier des cas de vérification documentaires`

**À réaliser.** FormRequests, Policies, Create/Revise/SubmitVerificationCaseService, Resources et lectures filtrées. Rejeter exécution dynamique et maintenir les révisions figées.

**Critères de sortie.** Auteur seul édite ses brouillons ; la modification invalide la revue ; masquage direct et listes testés.

**Traçabilité.** F13 ; AC34–AC36/AC51

#### BV203 — Revue et intégration des cas

**Commit proposé :** `feat(cases): relier une révision à un scénario approuvé`

**À réaliser.** Revue distincte/motif ; case_scenario_links alimenté via manifeste CI/release validé. Pas de code ni de chemin exécutable saisi depuis admin ; audit et notification sans doublon.

**Critères de sortie.** Reviewed distinct d’integrated ; pas d’auto-revue ; ancien lien reste attaché à sa révision.

**Traçabilité.** F13/F15 ; AC35–AC37/AC46

#### BV204 — Profils de comparaison B1

**Commit proposé :** `feat(comparisons): enregistrer les profils de comparaison approuvés`

**À réaliser.** Déclarer dans le registre baseline pédagogique/candidate liés à versions/digests/suite. Baseline non recommandée et non distribuée ; profils non administrables en code via HTTP.

**Critères de sortie.** Paire arbitraire/incompatible/retirée refusée ; intégrité manifeste testée ; scénarios fixes.

**Traçabilité.** F14 ; AC38/AC51

#### BV205 — Données et contrat des comparaisons

**Commit proposé :** `feat(comparisons): figer les entrées et les deux exécutions`

**À réaliser.** ComparisonRun/State/Outcome, migrations liens enfants et unique(comparison_id,side), snapshot d’entrée/seed/oracle/config. Requêtes et Resource sans fuite.

**Critères de sortie.** Même empreinte canonique pour les deux côtés, namespaces distincts, IDs/digests figés, exemple OpenAPI validé.

**Traçabilité.** F14 ; AC39

#### BV206 — Lancement idempotent et quotas partagés

**Commit proposé :** `feat(comparisons): réserver atomiquement deux unités de test`

**À réaliser.** StartComparisonService avec Policies, Idempotency-Key, réservation deux crédits, une opération/membre et mêmes limites globales que lab simple. Réponse 202 et suivi. Déploiement retenu : Réserver atomiquement deux unités de comparaison ; un seul run actif global partagé avec le lab simple.

**Critères de sortie.** Deux créations concurrentes testées sur PostgreSQL ; aucune double consommation au rejeu ; 409/429 corrects. Déploiement retenu : Comparer et lancer un run en concurrence ne contourne ni quota ni verrou.

**Traçabilité.** F14 ; AC41/AC42/AC52 ; ADR-004 / DEP

#### BV207 — Exécuter les deux côtés réels

**Commit proposé :** `feat(comparisons): exécuter la paire sur le worker restreint`

**À réaliser.** ExecuteComparisonJob/Service : enfants séquentiels, fixtures neuves, profils figés, claim/lease, finalisation unique et limite totale proposée 50 s. Pas de code entrant. Déploiement retenu : Appeler le même runner local restreint ; enfants successifs. Garder empreintes et fixtures séparées sans réexécuter arbitrairement une autre release.

**Critères de sortie.** Le doublon naïf produit réellement le défaut ; candidat conforme ; tests négatifs de permissions et isolation. Déploiement retenu : Deux observations nouvelles, manifestes vérifiés, données attendues distinctes des données observées.

**Traçabilité.** F14 ; AC39/AC40/AC43 ; ADR-004 / DEP

#### BV208 — Conclusions et pannes de comparaison

**Commit proposé :** `feat(comparisons): distinguer amélioration régression et panne`

**À réaliser.** ComparisonOutcomeCalculator pur, compatibilité assertions, cinq conclusions, ReconcileComparisonsService et retrait sécurisé des rapports.

**Critères de sortie.** Tester mixed, unchanged avec deux échecs, panne enfant, retry et données sensibles ; jamais de victoire automatique du candidat.

**Traçabilité.** F14 ; AC43–AC45

#### BV209 — Fiche de vérification et attributions

**Commit proposé :** `feat(evidence): relier les cas contributions et preuves versionnées`

**À réaliser.** VerificationSummaryQuery/Resource rassemble types de preuve distincts, liens de contributeurs, limite et états absents. Visibilité cohérente avec les sources ; pas de score global.

**Critères de sortie.** Nouvelle version sans badge hérité ; aucune auto-validation indépendante ; mêmes restrictions aux caches et URL.

**Traçabilité.** F15 ; AC45/AC46

#### BC07 — Questions sans formulaire de panne

**Commit proposé :** `feat(help): permettre les questions de connaissance sans code`

**À réaliser.** Après l’enum HelpIntent existant, ajouter ask_question. Validation conditionnelle goal/observed, expected/attempts nullables uniquement pour ce mode ; conserver règles des autres modes et les fils existants.

**Critères de sortie.** Tests migration contenu antérieur, 422 ciblée, question sans code/projet, commentaires sans laboratoire et anciennes demandes unblock/review/reproduce sans régression.

**Traçabilité.** F02/F03 ; AC64/AC68

#### BC08 — Recette API de la communauté

**Commit proposé :** `test(community): vérifier projets annuaire et questions`

**À réaliser.** Exécuter tests intégration PostgreSQL/routes F16/F17, contrats OpenAPI et négatifs, pagination, liens et caches. Compléter dictionnaire données et inventaire endpoints ; seeds explicites de projets éditoriaux.

**Critères de sortie.** Tous cas serveur AC53–64 observés ; preuves au SHA, pas de mocks dans routes P0 ; tests B1/proposals initiaux toujours verts ; charge et dépendances documentées.

**Traçabilité.** F01–F18 ; AC53–64/AC67/AC68

#### BV210 — Recette transversale de l’atelier

**Commit proposé :** `test(workshop): couvrir le parcours et les courses concurrentes`

**À réaliser.** Ajouter contrat/API A-B-C cas/revue/manifeste/comparaison/fiche et tests concurrence partagés. Mettre à jour matrice, OpenAPI et menaces ; aucun frontend.

**Critères de sortie.** AC33–AC46/AC50–AC52 partie backend ont commandes réelles ; failures pannes reproductibles et démo honnête.

**Traçabilité.** F13–F15 ; AC33–AC52 backend

#### BH01 — Schéma et ouverture aux coups de main

**Commit proposé :** `feat(help-offers): définir les états et l’ouverture volontaire`

**À réaliser.** Créer migration additive help_offers, HelpOfferState, HelpContributionCategory ; help_open=false, help_categories et préférences facultatives. Contraintes pending unique et cohérence accepted/request. Préparer réglages propriétaire et horodatages de consentement.

**Critères de sortie.** Migration vierge/existante sans perte ; aucun projet ouvert automatiquement ; états invalides/FK/indexes testés sur PostgreSQL.

**Traçabilité.** AC69/AC70

**Dépendances explicites.** BC01, B13

#### BH02 — Occasions de contribuer et préférences explicables

**Commit proposé :** `feat(discovery): proposer des besoins publics filtrables`

**À réaliser.** Créer ListHelpOpportunitiesQuery et Resource ; distinguer demande ouverte et projet volontaire, préserver fil direct ; filtres bornés, raisons basées sur champs déclarés, ordre stable. Étendre profil sans modifier opt-in annuaire.

**Critères de sortie.** AC71/72 : données cachées absentes, pas de compteurs pending ou score expert ; pas de doublon de carte favorisé ; préférences retirable, API publique non personnalisée en cache.

**Traçabilité.** AC71/AC72/AC87

**Dépendances explicites.** BH01, BC06

#### BH03 — Proposer un apport avec consentement et limites

**Commit proposé :** `feat(help-offers): soumettre une offre consentie sans doublon`

**À réaliser.** FormRequest/DTO/Policy/CreateHelpOfferService ; résumé public futur, résultat limité, accord non précoché côté UI ; quotas atomiques 5/jour et 5 pending, 1 pending par projet ; idempotence commune.

**Critères de sortie.** Acteur non propriétaire actif/vérifié ; refus champs serveur et consentement absent ; aucune offre publique ; double requête et concurrence de quotas testées.

**Traçabilité.** AC73–AC76

**Dépendances explicites.** BH01, B13

#### BH04 — Consulter ses offres sans fuite

**Commit proposé :** `feat(help-offers): protéger les offres reçues et envoyées`

**À réaliser.** ListMyHelpOffersQuery, HelpOfferResource privée et lecture individuelle ; propriétaire/proposant ; filtre sent/received, pagination, expiration serveur. Modération via signalement autorisé.

**Critères de sortie.** Autre membre/admin non habilité ne lit pas ; pas email ou cache public ; seules données visibles renvoyées et catégories datées.

**Traçabilité.** AC77/AC83

**Dépendances explicites.** BH03

#### BH05 — Accepter et créer un seul échange public

**Commit proposé :** `feat(help-offers): ouvrir un échange après double consentement`

**À réaliser.** AcceptHelpOfferService destination=create ; Policy, verrous et lock_version ; relire opt-in/délai/acteurs ; validation complète et service de demande réutilisés, public consent propriétaire et proposant ; audit/projection unique dans la transaction.

**Critères de sortie.** Refus consentement manquant/texte invalide ; owner fixé ; rollback ne laisse ni demande ni offre accepted ; résumé exact et acteur système distinct.

**Traçabilité.** AC78/AC80

**Dépendances explicites.** BH03, B14, BC07, B29

#### BH06 — Rattacher une offre à un échange choisi

**Commit proposé :** `feat(help-offers): rattacher une aide au bon échange`

**À réaliser.** Accepter mode attach explicitement ; même projet et propriétaire, public ouvert/en cours ; projection consentie sans altération du fil ; réponse de rejeu commune, ordre de verrous identique aux autres commandes.

**Critères de sortie.** Cas fil autre projet/auteur/masqué/résolu refusés ; acceptations concurrentes même offre ne dupliquent pas le lien ; aucune adhésion équipe.

**Traçabilité.** AC79/AC80

**Dépendances explicites.** BH05, BC04

#### BH07 — Refuser retirer expirer et fermer proprement

**Commit proposé :** `feat(help-offers): gérer le retrait et l’expiration des offres`

**À réaliser.** Services decline/withdraw/expire ; TTL7jours, expiration lors commandes et cron ; fermeture projet, archivage, catégorie retirée et suspensions invalident pending sans faux refus ; accepted reste historique.

**Critères de sortie.** Course accept/withdraw/close/suspend aboutit à un seul état valide ; aucun fil créé après fermeture ; motifs privés ; pas de malus ; pending périmé libère la contrainte.

**Traçabilité.** AC81/AC82/AC83/AC85

**Dépendances explicites.** BH06, B32

#### BH08 — Projections publiques progrès et notifications

**Commit proposé :** `feat(collaboration): relier les coups de main aux progrès observés`

**À réaliser.** Événement help_offer_accepted explicitement attribué, non commentaire usurpé ; ProjectProgressQuery depuis fils/résolutions/capsules ; notification after-commit unique avec rattrapage ; reports type help_offer et masquage.

**Critères de sortie.** Offre accepted affiche collaboration commencée jamais travail terminé ; aucun refus/pending/texte privé public ; caches et liens retirés ; notif sans corps sensible et unique au rejeu.

**Traçabilité.** AC84/AC86

**Dépendances explicites.** BH06, BH07, B29, B30

#### BH09 — Contrats et documentation des offres

**Commit proposé :** `docs(api): formaliser les contrats du coup de main`

**À réaliser.** Fusionner COUPS_DE_MAIN_API dans OpenAPI réel ; exposer can.offer/can.accept et erreurs ; documentation tables et enum ; tests schema/réponses ; commande génération types réutilisable pendant F.

**Critères de sortie.** Toutes routes P0 sont implémentées, pas uniquement décrites ; exemples cohérents avec CreateHelpRequestData actuel ; types protégés rejetés ; aucun YAML factice présenté testé.

**Traçabilité.** F18 ; AC69–AC87

**Dépendances explicites.** BH08

#### BH10 — Recette transactionnelle et privée des coups de main

**Commit proposé :** `test(help-offers): vérifier concurrence consentements et confidentialité`

**À réaliser.** Exécuter AC69–87 backend, tests réseau/idempotence/cron/suspension et transactions PostgreSQL ; vérifier non-régression F16/F17, questions sans code, lab inchangé ; documenter vrais résultats avant B39.

**Critères de sortie.** Courses réellement concurrentes et visibilité négative observées ; aucune preuve fabriquée ; tous nouveaux contrats intégrés au BACKEND_GATE ; charge réestimée.

**Traçabilité.** AC69–AC90 partie serveur

**Dépendances explicites.** BH02, BH09

#### B39 — Revue de sécurité API

**Commit proposé :** `test(security): couvrir les accès et entrées hostiles`

**À réaliser.** Compléter tests transversalement : mass assignment, IDOR, routes privées, limites de taille, liens non récupérés, Markdown/code inerte, session, CSRF, mots de passe et logs. Ajouts strictement nécessaires aux règles déjà définies. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure contrôle des parents projets, annuaire volontaire, champs publics et liens externes des F16/F17. Coup de main : Inclure F18 : IDOR offre, consentements, publication, états concurrents, quotas, logs et permissions du fil.

**Critères de sortie.** Aucun accès critique indu ; les tests ne désactivent pas discrètement le middleware évalué. Corriger les anomalies par petits commits de fix séparés au besoin. Alignement mail : AC53–64 négatifs couverts.

**Traçabilité.** AC02–AC07/AC18/AC25/AC26 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B40 — Contrat API complet

**Commit proposé :** `docs(api): vérifier les contrats de tous les endpoints P0`

**À réaliser.** Compléter OpenAPI, exemples fictifs, enums, champs, erreurs, pagination, capacités, limites et routes auth hors /api/v1. Tester la conformité des réponses. Préparer génération des types via un outil dev indépendant, sans créer la SPA. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure API projets/développeurs, intention ask_question, erreurs et capacités ; types générés synchronisés. Coup de main : Inclure COUPS_DE_MAIN_API et les types/erreurs F18 dans le contrat réel.

**Critères de sortie.** Toutes les opérations nécessaires aux écrans ont un contrat testé ; les routes nouvelles non listées initialement sont des précisions d’implémentation tracées, pas un changement métier implicite. Alignement mail : Aucun endpoint nouveau absent du contrat.

**Traçabilité.** F01–F12 ; N05 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B41 — Recette backend complète

**Commit proposé :** `test(backend): relier le parcours API et la concurrence`

**À réaliser.** Exécuter A demande → B proposition → A accepte → B révise la capsule → C teste/réutilise, par API. Tester B1-05 réellement concurrent et courses résolution/réouverture. Consolider fixtures et mesures API du cahier. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure AC53–64 serveur et questions sans lab. Coup de main : Réexécuter AC69–90 applicables au backend ; ne pas se limiter aux tests du laboratoire.

**Critères de sortie.** Rapport indique commit/environnement/commandes/observés. API couvre le parcours sans frontend ; ne pas déclarer AC32 navigateur réussi à ce stade. Alignement mail : Une réception antérieure ne couvre pas F16/F17.

**Traçabilité.** AC08–AC21 ; AC32 partie API ; N02 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B42 — Exploitation backend

**Commit proposé :** `chore(ops): préparer santé sauvegarde et reprise backend`

**À réaliser.** Runbook et scripts sûrs pour configuration, worker, cron, health minimal/protégé, sauvegarde chiffrée et restauration dédiée. Préparer la topologie Datacloud seulement sur capacités vérifiées. Déploiement retenu : Préparer exploitation d’un seul VPS : API, queues, runner distinct, PostgreSQL local, sauvegarde distante et health. Pas d’assets React servis ici pour l’application principale.

**Critères de sortie.** Exercice local documenté ; accès distant absent = BLOQUÉ pour ce contrôle. Aucune migration destructive production ni achat autorisé implicitement. Web et worker ont leurs configurations distinctes. Déploiement retenu : Runbook compatible Systalink ; secrets séparés ; aucun déploiement réel sans accord.

**Traçabilité.** AC29/AC30 partie backend ; N04 ; ADR-004 / DEP

#### B43 — Qodana backend

**Commit proposé :** `ci(qodana): contrôler le backend avec le profil validé`

**À réaliser.** Confirmer licence, linter, chemins et ce qui est envoyé au service ; installer à version/digest validé. Quality gate explicite, profil conservé, rapport réel lié au commit. Sans accès : alternative locale maintenue et décision humaine tracée. Déploiement retenu : Utiliser Ultimate déclaré existant. Configurer Qodana sur le vrai projet et l’offre ; audits licences/vulnérabilités restent indépendants lorsqu’ils ne sont pas compris dans Ultimate.

**Critères de sortie.** Une alerte témoin bloque effectivement le contrôle, puis est corrigée. Analyse indisponible affichée absente, jamais passée ; secrets Qodana uniquement dans environnement CI autorisé. Déploiement retenu : Token absent = contrôle non exécuté, pas vert ; ne pas annoncer les fonctions Ultimate Plus sans droit.

**Traçabilité.** AC28 ; ARB04 ; ADR-004 / DEP

#### B44 — Point de validation backend

**Commit proposé :** `docs(gate): consigner la réception technique du backend`

**À réaliser.** Renseigner BACKEND_GATE avec preuves de tous les P0 backend, contrôles locaux/CI, limites et questions externes. Livrer installation, API, tests et liste des endpoints. Demander la revue du second membre et GO_FRONTEND. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Consigner la conformité de la configuration serveur et les tests locaux/CI du contrat CORS/session ; distinguer la recette de domaines réels reportée à la livraison. Alignement mail : Recevoir F01–F18, API projets/annuaire et questions sans code, avant GO_FRONTEND humain. Coup de main : La porte backend couvre F18 et BH10 : demander une nouvelle revue et un GO_FRONTEND humain ; aucun frontend nouveau avant validation.

**Critères de sortie.** Aucune case cochée par hypothèse, aucun placeholder fonctionnel, aucune route P0 seulement mockée. Ne commencer aucun code frontend tant que le point de validation et l’autorisation manquent. Déploiement retenu : Revue humaine et GO_FRONTEND nécessaires ; aucune conformité navigateur réelle déclarée avant son test. Alignement mail : Aucun passage sur l’ancienne réception seule ; preuves BC01–BC08 examinées.

**Traçabilité.** F01–F12 partie backend ; N01–N08 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** BH10

### Phase F — Frontend après GO_FRONTEND

#### F01 — Socle React

**Commit proposé :** `chore(frontend): initialiser React TypeScript et Vite`

**À réaliser.** Après GO_FRONTEND, initialiser en préservant AGENTS.md ; scripts lint/typecheck/test/build, alias, frontières imports et env validée. Choisir versions compatibles et auditées ; aucune variable secrète VITE_. Déploiement retenu : Préparer Vite pour Vercel, racine frontend, dist ; configuration reproductible sans secret public.

**Critères de sortie.** Build et test minimal passent ; CI frontend exécutable ; aucun backend reconstruit ou remplacé. Types stricts, pas de any généralisé. Déploiement retenu : Compilation du frontend après GO_FRONTEND uniquement.

**Traçabilité.** N05/N06 ; ADR-004 / DEP

#### F02 — Tokens de design

**Commit proposé :** `feat(design): installer la palette et la typographie HAAS`

**À réaliser.** Transposer docs/design/tokens dans le frontend, configurer thème clair, police système, grille, tailles et focus. Nom de marque configurable. Créer une page de catalogue de composants de développement, pas une dépendance Storybook imposée.

**Critères de sortie.** Toutes les combinaisons autorisées passent le script de contraste ; border décorative non utilisée comme seule délimitation d’un input. Pas de couleur brute répétée dans les écrans.

**Traçabilité.** AC31 ; N03

#### F03 — Composants de formulaire

**Commit proposé :** `feat(ui): créer des champs et boutons accessibles`

**À réaliser.** Button, LinkButton, Input, Textarea, Select, Checkbox, FormField, ErrorSummary et PasswordField. Labels visibles, descriptions, erreurs liées, focus et tailles 44 px. Masque de mot de passe réversible et collage autorisé.

**Critères de sortie.** Tests keyboard/name/label/disabled/pending/error ; données longues et zoom. Aucun placeholder servant seul d’étiquette.

**Traçabilité.** AC05/AC31

#### F04 — Composants de lecture

**Commit proposé :** `feat(ui): structurer cartes dialogues et états`

**À réaliser.** Badge sémantique, EmptyState, LoadingState, Alert, Dialog, Drawer, Tabs, Pagination et CodeBlock. Choisir primitives accessibles auditées si utiles ; ne pas cumuler deux bibliothèques de composants.

**Critères de sortie.** Dialogs piègent puis rendent le focus ; Escape et titre accessible ; code inerte et défilement interne ; boutons avec noms explicites ; états sans dépendance à couleur seule.

**Traçabilité.** AC07/AC31

#### F05 — Client API et types

**Commit proposé :** `feat(http): centraliser le transport et les erreurs API`

**À réaliser.** Axios unique, types générés depuis OpenAPI, ApiError, abort, timeout et callbacks injectés. Interceptors nettoyés et réessais maîtrisés ; auth/CSRF hors préfixe API. Fonctions API par feature. Déploiement retenu : Utiliser VITE_API_URL validée, origine API absolue, withCredentials et withXSRFToken. Les routes auth/CSRF n’incluent pas /api/v1.

**Critères de sortie.** Tests 401/403/409/419/422/429/503/réseau/annulation ; aucun redirect infini ni POST rejoué aveuglément ; un 503 de /me ne déconnecte pas. Déploiement retenu : Aucun appel principal à baseURL=/ sur Vercel ; tests 401/419/422/429 et chemins auth/API.

**Traçabilité.** AC05/AC06 ; N01 ; ADR-004 / DEP

#### F06 — Session et guards

**Commit proposé :** `feat(auth): contrôler les écrans avec la session serveur`

**À réaliser.** QueryProvider, source session unique, Auth/Guest/VerifiedEmail/ActiveAccount/PermissionGuard. Capabilities calculées par le backend pour les actions. Purge caches privés et annulation sur changement d’identité. Déploiement retenu : Prendre en charge cookies inter-origines same-site et états de session distincts réseau/anonyme.

**Critères de sortie.** Anonyme/chargement/erreur réseau/connecté distincts ; deep links et retour après login sûrs ; session A ne fuit pas vers B ; aucune confiance dans rôle local. Déploiement retenu : Logout purge les caches privés ; un 503 ne devient pas une déconnexion.

**Traçabilité.** AC02/AC03/AC26 ; ADR-004 / DEP

#### F07 — Navigation responsive

**Commit proposé :** `feat(layout): simplifier la navigation publique et membre`

**À réaliser.** PublicLayout/MemberLayout/AdminLayout ; trois accès principaux Aider/Explorer/Mon espace plus action Demander de l’aide. Navigation mobile avec libellés ; skip link, page title, focus après navigation et retour navigateur conservé. Alignement mail : Navigation principale Explorer/Projets/Développeurs ; action Demander de l’aide ; Mon espace et contributions dans compte.

**Critères de sortie.** Utilisable à 320/360/390/768/1280 px et clavier ; pas de sidebar permanent sur petit écran ; aucun élément collant ne masque le focus. Alignement mail : Menu mobile lisible ; pas de laboratoire CTA principal.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F08 — Pages de compte

**Commit proposé :** `feat(auth): relier inscription connexion et récupération`

**À réaliser.** Pages réelles login/register/verification/forgot/reset, messages français, caps lock utile si disponible, erreurs inline et lien retour sûr. Écran de compte suspendu informatif.

**Critères de sortie.** Parcours contre Sanctum réel, CSRF inclus ; compte non vérifié guidé ; messages sans révélation de compte au reset ; aucun faux succès local.

**Traçabilité.** AC01/AC02/AC26

#### F09 — Accueil utile

**Commit proposé :** `feat(home): présenter l’entraide sans surcharge`

**À réaliser.** Promesse courte, CTA Demander de l’aide/Explorer les solutions, explication Demander→Collaborer→Vérifier→Partager et trois contenus utiles réels/éditoriaux étiquetés. Pas de faux compteur ni de carrousel. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA. Alignement mail : Promesse communautaire ; aperçus de projets, échanges et solutions ; lien vers les développeurs ; pas de faux comptes. Coup de main : L’accueil comporte les deux entrées Faire avancer mon projet / Donner un coup de main, sans bloquer explorer ou question.

**Critères de sortie.** Un visiteur comprend les deux actions avant connexion ; liens reliés à des contenus disponibles ; empty/error/load states travaillés. Alignement mail : Présentation de projet possible depuis accueil ; atelier expliqué en secondaire.

**Traçabilité.** F02/F12 ; AC27/AC31 ; ADR-005 / [M]

#### F10 — Catalogue

**Commit proposé :** `feat(explorer): rechercher les demandes et solutions`

**À réaliser.** Onglets clairs demandes/capsules, recherche et filtres visibles/supprimables avec URL synchronisée. Pagination explicite, cartes lisibles, état sans résultat guidé. Utiliser l’API réelle.

**Critères de sortie.** Rechargement conserve les filtres ; recherche n’expose pas retirés/brouillons ; raccourcis non nécessaires ; mobile sans défilement horizontal de page.

**Traçabilité.** AC15/AC25/AC31

#### F11 — Formulaire de demande

**Commit proposé :** `feat(requests): guider la publication en trois étapes`

**À réaliser.** Contexte → Problème → Vérification ; RHF/Zod depuis contrat, libellés et exemples du screen spec. Brouillon privé, résumé avant publication, alerte de secrets. Sauvegarde en mémoire sauf action explicite vers serveur. Alignement mail : Mode ask_question sans champs panne obligatoires ; lien projet facultatif prérempli pour propriétaire.

**Critères de sortie.** Erreurs sous champs, focus première erreur, précédent sans perte ; création/édition connectées ; expiration/réseau ne détruisent pas la saisie ni ne promettent une sauvegarde inexistante. Alignement mail : Question simple réalisable sans code/lab ; erreurs accessibles.

**Traçabilité.** AC05/AC06/AC07/AC31 ; ADR-005 / [M]

#### F12 — Discussion de demande

**Commit proposé :** `feat(collaboration): afficher les échanges et propositions`

**À réaliser.** Détail demande, contexte repliable, discussion et propositions distinctes, panneau de progression. Commenter/éditer avec ownership ; polling léger uniquement visible/en ligne ; historique lisible.

**Critères de sortie.** Création idempotente, suppression/modération bien interprétée ; commentaire ne prétend pas résoudre ; accès interdit et contenu retiré gérés.

**Traçabilité.** AC08/AC25/AC31

#### F13 — Résolution guidée

**Commit proposé :** `feat(resolutions): confirmer et rouvrir une solution`

**À réaliser.** Formulaire de proposition, note d’acceptation, self-resolution signalée, non-retenue et réouverture avec motif. Afficher actions selon can du serveur ; attendre réponse pour succès.

**Critères de sortie.** Conflit 409 conserve la saisie et propose rechargement ; double clic sans effet supplémentaire ; admin non auteur ne voit pas un faux pouvoir de validation.

**Traçabilité.** AC09/AC10/AC11

#### F14 — Éditeur de capsule

**Commit proposé :** `feat(capsules): documenter les versions issues de l’aide`

**À réaliser.** Création depuis demande résolue ou origine éditoriale habilitée, champs procédure/limites/provenance/versions, contributeurs et aperçu. Soumission à revue avec état visible.

**Critères de sortie.** Brouillon privé ; auteur comprend ce qui manque ; pas de mélange entre capsule, brique et laboratoire ; pas d’édition du texte d’une version publiée.

**Traçabilité.** AC12/AC13

#### F15 — Lecture et preuves de capsule

**Commit proposé :** `feat(capsules): rendre les versions et preuves compréhensibles`

**À réaliser.** Page capsule avec sélection de version, diagnostic/correctif/procédure/limites, attribution et panneau de preuves. Distinguer accepté/retour déclaré/test machine. Téléchargement contrôlé et état droits non validés explicite.

**Critères de sortie.** Badge lié à la version choisie ; retrait désactive actions avec motif ; code copiable en sécurité ; aucun slogan « code sécurisé » pour un seul test.

**Traçabilité.** AC13/AC14/AC15/AC25

#### F16 — Lancement et rapport du laboratoire

**Commit proposé :** `feat(lab): suivre les tests et afficher les résultats réels`

**À réaliser.** Lancer B1, gérer 202/queued/running et résultats terminaux, quota et délai. Polling stoppé à la fin ou hors écran ; nouvelle intention nouvelle clé. Afficher attendu/observé, date/version/digests, limites.

**Critères de sortie.** Failed/error/timed_out distincts ; aucun pourcentage inventé ; run interrompu réconcilié ; seuls B1-01 à 04 rejouables web, B1-05 étiqueté CI.

**Traçabilité.** AC16–AC21

#### F17 — Espace personnel et profils

**Commit proposé :** `feat(profiles): organiser les demandes et contributions`

**À réaliser.** Mon espace avec brouillons/demandes/notifications/favoris et liens vers contributions. Profil public sans faux score ni données privées ; édition des champs autorisés. Alignement mail : Afficher Mes projets et lien vers les préférences annuaire ; profils listent uniquement leurs projets publics visibles.

**Critères de sortie.** Premier usage guidé ; compteurs de démo distingués ; état vide utile ; inaccessible aux autres lorsque privé. Alignement mail : Aucun brouillon ni état de session d’un autre membre.

**Traçabilité.** F11 ; AC27/AC31 ; ADR-005 / [M]

#### F18 — Favoris et retours

**Commit proposé :** `feat(reuse): recueillir des retours de réutilisation précis`

**À réaliser.** Ajouter/retirer favoris et déposer un retour sur la version utilisée : environnement, date, reproduit/partiellement/non reproduit et observation. Afficher origine interne lorsque pertinente.

**Critères de sortie.** Un seul retour actif, édition historisée ; favoris privés ; le libellé ne transforme pas une déclaration en certification.

**Traçabilité.** AC14 ; F07

#### F19 — Notifications

**Commit proposé :** `feat(notifications): présenter les événements utiles`

**À réaliser.** Panneau/listes paginées, lu/non lu, liens profonds et résumé sans secret. Sur ressource retirée, afficher un état sûr. Pas de toast en rafale à chaque polling.

**Critères de sortie.** Lecture unique et compteur serveur cohérents ; cache purgé à la sortie ; navigation clavier/petits écrans vérifiée.

**Traçabilité.** F10 ; AC25/AC31

#### F20 — Back-office

**Commit proposé :** `feat(moderation): traiter les revues et signalements`

**À réaliser.** Files de revue capsule, signalements, comptes et lab incidents ; filtres simples ; dialogues avec motif. Publication indépendante, correction, retrait et suspension via APIs existantes. Alignement mail : Inclure signalements de projet ; ne pas ajouter un back-office projet indépendant.

**Critères de sortie.** Un membre n’accède pas aux écrans admin ni aux données sous-jacentes ; un admin auteur ne se relit pas ; actions dangereuses nommées, pas de bouton générique « OK ». Alignement mail : Propagation de masquage visible et auditée.

**Traçabilité.** AC12/AC25/AC26 ; ADR-005 / [M]

#### F21 — Démonstrateur B2

**Commit proposé :** `feat(b2): conserver les commandes fictives hors connexion`

**À réaliser.** Mini React isolé sous modules/B2-offline-form ; IndexedDB pour commandes fictives et clé stable, service worker de shell limité à cette origine. États local/envoi/confirmé/à reprendre ; synchronisation manuelle fiable. Déploiement retenu : Déployer B2 dans un projet Vercel distinct, demo.example.com, API demo-api.example.com cookie-free ; uniquement données fictives.

**Critères de sortie.** Vraie coupure puis fermeture/réouverture retrouve la saisie si stockage intact. Effacement/stockage indisponible expliqué ; aucun cookie/cache HAAS inclus. Déploiement retenu : Aucun cookie du parent .haas.example.com envoyé à B2 ; aucune persistance hors connexion de la plateforme principale.

**Traçabilité.** AC22/AC23/AC24 ; ADR-004 / DEP

#### FC01 — Catalogue et fiche projet

**Commit proposé :** `feat(projects): présenter les projets et leurs échanges`

**À réaliser.** Créer features/projects et UX18 : listes/détails reliés API, filtres URL, propriétaire/capacités, liens externes sûrs, états vide/erreur ; actions vers demandes existantes.

**Critères de sortie.** API réelle, aucun bouton mort ni faux compteur ; mobile/clavier, masquage et 403/404 ; état du projet affiché déclaré.

**Traçabilité.** F16 ; UX18 ; AC55/AC59/AC65/AC66

#### FC02 — Édition de projet et demande liée

**Commit proposé :** `feat(projects): publier une fiche et demander de l’aide`

**À réaliser.** Implémenter UX19, formulaire de projet, publication/archivage, brouillon et lien prérempli vers demande avec project_id ; reprise 409/422 sans pertes ; ajouter Mes projets au compte.

**Critères de sortie.** Créer→publier→demander sur API réelle ; owner/tiers, conflit et idempotence ; données de démonstration identifiées ; cache session vidé.

**Traçabilité.** F16 ; UX19 ; AC53–58/AC65/AC66

#### FC03 — Découverte et préférences des membres

**Commit proposé :** `feat(developers): découvrir les membres par technologie`

**À réaliser.** Créer features/developers et UX20 ; annuaire filtrable, profil et projets/contributions publiques ; opt-in et disponibilité dans préférences compte ; aucune messagerie ni faux indicateur online.

**Critères de sortie.** AC60–63 côté navigateur, clavier/mobile, cache après opt-out, formulaire et labels explicites ; aucun appel Axios en JSX.

**Traçabilité.** F17 ; UX20 ; AC60–63/AC65/AC66

#### FH01 — Accueil et occasions concrètes d’aider

**Commit proposé :** `feat(discovery): ouvrir le parcours donner un coup de main`

**À réaliser.** Après GO_FRONTEND : UX21 deux entrées plus accès explorer/question ; /coups-de-main réel avec filtres/résultats et explication de correspondance, CTA direct vers fil ou offre selon cas.

**Critères de sortie.** Pas de faux besoins ; loading/vide/erreur séparés ; pas d’annuaire imposé ; URL filtres et mobile/clavier.

**Traçabilité.** UX21 ; AC71/AC72/AC87

**Dépendances explicites.** B44, BH10, F05, F07, FC01

#### FH02 — Ouvrir son projet volontairement

**Commit proposé :** `feat(projects): configurer une demande de coup de main`

**À réaliser.** Modifier UX19 et préférences ; switch non précoché, catégories, help_sought requis seulement pour ouverture ; prévisualisation, état fermé explicite et fermeture confirmée ; accès toujours propriétaire.

**Critères de sortie.** Aucun opt-in induit par publication ; consentement annuaire séparé ; 409/422 conserve saisie en mémoire ; fermer ne masque pas les anciens fils.

**Traçabilité.** AC69/AC70/AC82 ; UX19/UX21

**Dépendances explicites.** FH01, FC02

#### FH03 — Proposition et aperçu de publication

**Commit proposé :** `feat(help-offers): proposer une aide avec aperçu lisible`

**À réaliser.** UX22 + api/models/schemas/hooks centralisés ; formulaire catégorie/résumé/résultat, consentement public explicite ; confirmation pending privée ; retour connexion sûr sans secret persistant.

**Critères de sortie.** Libellés compréhensibles ; pas de soumission sans consentement ; erreur réseau réconciliée même clé ; succès API seulement ; captures mobile/clavier.

**Traçabilité.** UX22 ; AC73/AC75/AC88/AC89

**Dépendances explicites.** FH02, F06

#### FH04 — Offres reçues envoyées et décision

**Commit proposé :** `feat(help-offers): accepter ou décliner une aide clairement`

**À réaliser.** UX23 listes privées, détail et choix explicite create/attach ; préremplissage editable côté propriétaire, résumé proposant figé ; double consentement, états finaux, retrait et expiration.

**Critères de sortie.** Pas d’acceptation optimiste ; 409 relit sans inventer action ; 403/404 ne fuit pas existence ; logout purge ; notifications distinctes de chat.

**Traçabilité.** UX23 ; AC77–AC85/AC88/AC89

**Dépendances explicites.** FH03, F19

#### FV201 — Formulaire et suivi des cas

**Commit proposé :** `feat(cases-ui): guider la proposition d’un cas concret`

**À réaliser.** Feature verification-cases : API/types/schémas/hooks/pages/composants. Intention d’aide ciblée, états draft/submitted/reviewed et explication non exécuté ; réutiliser auth et design.

**Critères de sortie.** API Laravel réelle, labels et erreurs liés, formulaire préservé en mémoire ; aucun statut optimiste « testé ».

**Traçabilité.** F13 ; AC33–AC37 frontend

#### FV202 — Choisir et lancer une comparaison

**Commit proposé :** `feat(comparison-ui): choisir un scénario approuvé`

**À réaliser.** Feature comparisons : profil/versions non libres, choix scénario fixe, aperçu des deux côtés, indication quota, clé par intention et suivi 202.

**Critères de sortie.** Rejeux/409/429/réseau testés ; profils retirés absents ; annonce clair du coût de deux unités.

**Traçabilité.** F14 ; AC38/AC41/AC42

#### FV203 — Lecture comparative accessible

**Commit proposé :** `feat(comparison-ui): montrer les observations avant et après`

**À réaliser.** ComparisonReportPage/Cards : expected/observed par côté, trois états techniques distincts, cinq conclusions, dates et détail de preuve. Polling borné/focus, historique daté.

**Critères de sortie.** Mobile une colonne, clavier, partiel/error/timeout/régression visibles ; aucune victoire présumée ni indicateur purement coloré.

**Traçabilité.** F14 ; AC40/AC43/AC44/AC48/AC49

#### FV204 — Fiche de vérification et mémoire

**Commit proposé :** `feat(evidence-ui): expliquer les preuves et leurs contributeurs`

**À réaliser.** VerificationSummaryPanel et timeline de contributions. Types de preuves nommés, limites lisibles. P1 copie de contexte uniquement après P0 reçu, aperçu/annulation/succès réel, pas d’API IA.

**Critères de sortie.** Version sélectionnée exacte ; absent/retiré géré ; P1 absent implique aucun bouton. Si livré, AC47 entièrement exécuté.

**Traçabilité.** F15 ; AC45–AC47

#### FV205 — Recette visuelle et bout en bout atelier

**Commit proposé :** `test(workshop-ui): vérifier les cas et comparaisons en situation`

**À réaliser.** Playwright contre API réelle + revue manuelle mobile/clavier/contrastes et états réseau. Données fictives explicitement séparées du pilote externe.

**Critères de sortie.** Captures/rapports du commit courant ; AC48–AC50 réels ; aucun contrôle auto ne vaut revue humaine complète.

**Traçabilité.** F13–F15 ; AC48–AC52

#### FC04 — Parcours communautaire complet

**Commit proposé :** `test(community): valider la découverte et les échanges de bout en bout`

**À réaliser.** Relier accueil/navigation à projets/développeurs ; mode Poser une question dans UX05 sans code ni résultat imposé. Exécuter A→projet→B→aide→capsule→C et une question sans labo ; vérifier textes/états et capture datée.

**Critères de sortie.** AC64–68 exécutés avec API réelle ; lecteur extérieur comprend comment participer ; l’absence de laboratoire ne bloque aucune contribution ordinaire. Pas de chiffres d’adoption inventés.

**Traçabilité.** F02/F03/F16/F17 ; UX01/UX05/UX18–20 ; AC64–68

#### FH05 — Progrès visibles et recette navigateur

**Commit proposé :** `test(collaboration): vérifier la première aide de bout en bout`

**À réaliser.** Vue progrès du projet et projection dans fil ; Playwright projet sans fil→offre→acceptation→apport→résolution éventuelle ; route attach et refus/retrait ; revue du texte, focus, version mobile et zoom.

**Critères de sortie.** AC86–90 observés ; accepté≠travail réalisé ; sans laboratoire obligatoire ; traces rapportées au commit réel, aucun faux usage ou résultat.

**Traçabilité.** AC86–AC90 ; UX18/UX21–23

**Dépendances explicites.** FH04, F13, FC04

#### F22 — Résilience frontend

**Commit proposé :** `test(resilience): vérifier les reprises et caches privés`

**À réaliser.** Tests d’intégration frontend sur timeout, 419, résultat serveur perdu, invalidation et passage utilisateur A→B. Corriger les hooks et interceptors au besoin en commits ciblés. Coup de main : Vérifier les offres pending privées, reprise idempotente et purge de cache après changement de compte.

**Critères de sortie.** Aucune duplication de mutation, fuite cache, re-login infini ou faux accusé serveur. Boutons de reprise présents et effets réellement vérifiés en API.

**Traçabilité.** AC06/AC23/AC26

#### F23 — Audit d’accessibilité

**Commit proposé :** `test(a11y): vérifier clavier focus et contrastes`

**À réaliser.** Automatisation Playwright/axe selon licences validées, plus clavier manuel, labels, reflow, zoom, mouvement réduit, lecteurs d’écran disponibles. Faire le contrôle de contraste des états hover/focus/error et des blocs de code. Alignement mail : Inclure UX18–UX20 dans la revue clavier/mobile/contraste/zoom. Coup de main : Vérifier les consentements et UX21–23 au clavier, à 360/390 px et au zoom.

**Critères de sortie.** Pas de violation connue non traitée sur parcours central ; capturer limites des tests manuels. Audit automatique seul ne vaut pas conformité globale. Alignement mail : AC65 et AC66 sur nouveaux écrans.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F24 — Finition visuelle

**Commit proposé :** `style(ui): harmoniser les écrans sur le design system`

**À réaliser.** Revue dans navigateur exécuté à 360,390,768,1280 px : hiérarchie, espaces, lisibilité, formulaires longs, contenus vides/erreurs et alignement. Corriger les incohérences sans changer la charte page par page. Alignement mail : Revoir la cohérence communauté-first ; mêmes tokens, textes et composants sur projets et développeurs.

**Critères de sortie.** Captures datées du build réel, revue de l’autre membre ; pas de retouche d’image pour masquer un défaut produit ; les actions restent accessibles à 320 px. Alignement mail : Aucun maquettage joli non relié à l’API.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F25 — Recette navigateur complète

**Commit proposé :** `test(e2e): vérifier le parcours HAAS contre Laravel`

**À réaliser.** Tests A→B→A→réviseur→C, demande/capsule/B1/retour, plus B2 coupure/reprise, mobile et modération. Exécuter le build servi comme en production, pas seulement Vite en développement. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Recette sur deux origines compatibles : login/CSRF, lecture, écriture, déconnexion, erreurs et parcours complet. Pas de wildcard pour previews. Alignement mail : Exécuter AC53–AC68 et routes communauté avec backend réel ; joindre preuves. Coup de main : Inclure le parcours AC90 réel avec création et rattachement du fil, refus et retour aux demandes classiques.

**Critères de sortie.** AC32 prouvé sur API réelle et utilisateurs distincts ; mesure 4 Mbit/s/150 ms et build budget ; tests négatifs et droits exécutés ; mocks confinés aux tests composants. Déploiement retenu : Test navigateur du vrai flux requis ; une preview visuelle ou des mocks ne valident pas l’API. Alignement mail : Le parcours question/discussion fonctionne sans laboratoire.

**Traçabilité.** AC01–AC32 selon matrice ; N02 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

#### F26 — Point de validation frontend

**Commit proposé :** `docs(gate): consigner la recette frontend et UX`

**À réaliser.** Mettre à jour FRONTEND_GATE, matrice AC, captures et limitations ; relire toute la microcopie. Confirmer absence d’écran non relié, faux contenu d’adoption et appels HTTP dispersés. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Consigner toutes les vues liées à la vraie API et les vérifications de cookies/CORS en environnement de recette. Alignement mail : Recevoir UX01–UX20 et F16/F17, formulaires/questions et contributions avant release. Coup de main : Couvrir UX21–23 et FH05 avec preuve réelle ; une ancienne recette ne couvre pas F18.

**Critères de sortie.** Contrôles frontend et Qodana applicable réels ; preuves intégration/UX liées au commit ; revue humaine distincte. Ne pas appeler « production » un build seulement local. Déploiement retenu : Les tests DNS/TLS finaux restent dans DEPLOYMENT_GATE, jamais marqués passés par déduction. Alignement mail : Vingt familles, preuves datées réelles et aucun bug bloquant.

**Traçabilité.** F01–F12 ; AC31/AC32 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** FH05

### Phase R — Réception et livraison

#### R01 — Inventaires et droits

**Commit proposé :** `docs(compliance): inventorier dépendances droits et usage IA`

**À réaliser.** Consolider versions, licences directes/transitives/runtime/outillage, provenance du code/visuels/skills et déclarations IA. Séparer propres droits, droits tiers, contributions de testeurs et diffusion contrôlée des kits. Déploiement retenu : Vérifier plan Vercel, dépôts privés et sièges avec les vrais auteurs Git ; Ultimate existant et couverture du projet.

**Critères de sortie.** Aucun composant inconnu accepté sans décision, aucune licence publique appliquée au projet pour convenance. ARB de concours non résolu apparaît en blocage explicite. Déploiement retenu : Aucune publication du dépôt ni falsification des auteurs pour contourner des conditions.

**Traçabilité.** N06/N07 ; ARB01–ARB06 ; ADR-004 / DEP

#### R02 — Artefact reproductible

**Commit proposé :** `ci(release): construire un artefact lié au commit testé`

**À réaliser.** Builder backend/frontend et kits aux versions verrouillées, manifeste/digests et contrôles requis. GitHub environment et protections disponibles vérifiés ; approbation indépendante avant action distante. Déploiement retenu : Construire les artefacts API/runner et frontend, chacun avec empreinte, SHA et configuration cible ; manifeste de versions liées.

**Critères de sortie.** Aucun binaire généré hors pipeline présenté comme testé ; secrets exclus ; job requis absent ne permet pas un déploiement silencieux. Déploiement retenu : Aucune reconstruction silencieuse différente ; les rapports correspondent aux artefacts publiés.

**Traçabilité.** AC28 ; N05 ; ADR-004 / DEP

#### R03 — Recette et livraison Systalink / Vercel

**Commit proposé :** `ci(deploy): livrer l’API Systalink puis React Vercel`

**À réaliser.** Après autorisation d’accès/déploiement, configurer React sur Vercel et API/auth sur Systalink, B2 isolé, cookies bornés au parent de confiance, worker/cron, santé et alertes. Effectuer migrations compatibles et smoke tests. Déploiement retenu : Recette locale/CI et test contrôlé des domaines réels ; accord GO_PRODUCTION ; livrer API Systalink puis React Vercel, de façon compatible et contrôlée. Pas de préproduction distante permanente imposée.

**Critères de sortie.** URL réellement accessible, /api inconnue JSON, CSP/session/caches contrôlés, isolation lab vérifiée. Pas de supposition de capacité, achat ou transfert de secret. Déploiement retenu : Vercel ne publie pas main avant les contrôles ; TLS, cookies, CORS, liens profonds et sous-lots DEP validés.

**Traçabilité.** N01/N04 ; F08 ; ADR-004 / DEP

#### R04 — Restauration et retour stable

**Commit proposé :** `test(ops): vérifier restauration et retour au build stable`

**À réaliser.** Restaurer sauvegarde chiffrée sur environnement séparé ; mesurer temps/perte. Tester retour au code précédent sans rollback destructif automatique de migrations ; procédures lisibles par l’autre membre. Déploiement retenu : Restaurer une sauvegarde chiffrée hors production. Tester rollback app/backend/frontend compatible et redémarrage des workers.

**Critères de sortie.** AC29/AC30 avec horodatage et résultat réel ; sauvegarde seule n’est pas restauration ; aucune donnée de production supprimée par le test. Déploiement retenu : Ni migration destructive ni effacement de production ; durées et pertes cibles mesurées.

**Traçabilité.** AC29/AC30 ; N04 ; ADR-004 / DEP

#### RV201 — Preuve d’utilité collaborative

**Commit proposé :** `docs(pilot): documenter l’apport d’un cas extérieur`

**À réaliser.** Faire proposer ou reproduire un cas par un testeur extérieur consentant puis observer réutilisation. Séparer ce pilote du scénario éditorial préparé ; consigner erreurs et limites.

**Critères de sortie.** Contributions attestées ou objectif non atteint signalé ; pas de noms/mesures inventés ; pitch actualisé au périmètre livré.

**Traçabilité.** F13–F15 ; AC46/AC50 ; pilote

#### RH01 — Observer les premières collaborations

**Commit proposé :** `docs(pilote): documenter les coups de main réellement utiles`

**À réaliser.** Pilote volontaire après recette : cible qualitative trois petits coups de main ; noter absence de réponse/refus et bénéfice sans orchestrer tous les clics ; consentement présentation, exemples fictifs séparés.

**Critères de sortie.** Objectif atteint ou non explicitement indiqué ; pas de données inventées ; vérifier compréhension publication et distinguer offre acceptée/apport utile ; pitch cohérent avec logiciel livré.

**Traçabilité.** F18 ; AC90 ; pilote

**Dépendances explicites.** FH05, RV201

#### R05 — Pilote et démonstration

**Commit proposé :** `docs(demo): préparer un scénario fondé sur des preuves`

**À réaliser.** Préparer données fictives étiquetées, comptes limités et scénario quatre minutes. Conduire les tests utilisateurs possibles, enregistrer observations brutes et correctifs. La vidéo de secours porte date/commit. Alignement mail : Pilote : un testeur découvre un projet/un membre et contribue ; observation de réutilisation séparée de la démo éditoriale. Présentation commence par le mail et la communauté.

**Critères de sortie.** Aucune métrique d’adoption fabriquée ; texte/pitch cohérents avec fonctionnalités livrées ; chaque personne peut expliquer architecture, sécurité et contribution de l’IA. Alignement mail : Aucune mesure inventée ; schémas remplacés seulement par captures du produit réellement livré.

**Traçabilité.** AC27/AC32 ; N07/N08 ; ADR-005 / [M]

#### R06 — Réception et transmission

**Commit proposé :** `docs(release): finaliser la réception et le dossier de reprise`

**À réaliser.** Renseigner RELEASE_GATE, release notes, manuel, runbook, contrats, kits approuvés, matrice et registre. Préparer tag après validation humaine, maintien de service et rappel individuel du vote selon calendrier confirmé. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Rendre URL frontend et API, référence produit Datacloud actif, manifeste, contrôle de restauration, rapports Qodana réels et points ouverts. Alignement mail : Remettre cahier, contrats et instructions communauté ; vérifier cohérence du périmètre et noms sans suffixe de version. Coup de main : Livrer F18 avec AC69–90, journal de décisions et preuves de consentement ; pas de collaboration fictive annoncée réelle.

**Critères de sortie.** Les 90 AC (AC47 non applicable si P1 absent) ont une preuve ou un écart explicitement bloquant/accepté selon nature. Aucun défaut de sécurité ou faux rapport dérogeable. Aucune victoire ni conformité juridique garantie. Déploiement retenu : Les documents concordent avec l’infrastructure réellement livrée ; aucun badge vert fictif. Alignement mail : F01–F18, AC01–AC90 (AC47 conditionnel), dépôt et démo décrivent le même produit.

**Traçabilité.** F01–F12 ; AC01–AC32 ; N01–N08 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** RH01

## 13. Reprise entre sessions

État minimal à conserver : phase, dernier lot terminé, prochain lot, branche, commit réel, fichiers non committés, commandes et résultats, preuves, décisions nécessaires. Ne pas relancer un bootstrap si le projet existe déjà. Réexaminer les changements faits par l’autre personne depuis la session précédente.

Rapport de session attendu :

```text
Phase / lot : B19
Livré : [comportement réellement terminé]
Contrôles : [commandes et résultats réellement constatés]
Commit : [SHA réel, ou aucun commit et raison]
Reste ouvert : [défaut, test absent ou accès externe manquant]
Prochain lot : B20
Gate : backend non encore reçu ; frontend non commencé
```

En cas d’erreur : reproduire, écrire le test manquant, corriger le plus petit périmètre et revalider. Ne pas contourner un défaut en supprimant le test, en désactivant la Policy ou en inventant un résultat de laboratoire. Les dérogations de présentation ne permettent jamais une fuite de données.

## 14. Livraison attendue de Codex

Application Laravel et React réellement exécutables ; migrations/seeders fictifs ; client API typé ; workflows configurés ; tests et rapports ; kits B1/B2 retenus ; docs installation/contrats/architecture/design/runbook ; notices/déclaration IA ; liste des limites ; historique de petits commits ; preuve du parcours central.

La première session commence par S01, S02 puis le premier lot backend possible. Continuer les lots backend tant que les prérequis sont réunis ; terminer la session sur un état cohérent. S’arrêter au gate pour la revue demandée, pas pour créer prématurément quelques pages React. Les documents de suivi se remplissent à partir de faits, pas à partir de ce que l’agent pense pouvoir faire plus tard.

## 15. Références externes (historique et contrôle antérieur)

Ces références complètent les choix d’implémentation ; les exigences produit proviennent de [M], [P], [A] et [U]. Le registre docs/sources/REFERENCES_PRODUIT.md indique les références effectivement relues le 1er octobre 2026 ; le catalogue design existant conserve sa date et doit être revérifié avant installation. Consultation du 30 septembre 2026. Les contenus et licences évoluent : revérifier la version exacte avant installation. Les URL sont des sources, pas une instruction d’exécuter du code distant.

- **S01** — OpenAI, création et découverte des skills : https://developers.openai.com/codex/skills (redirige vers https://learn.chatgpt.com/docs/build-skills).
- **S02** — OpenAI, AGENTS.md : https://developers.openai.com/codex/guides/agents-md (référence active relue antérieurement).
- **S03** — Anthropic, frontend-design : https://github.com/anthropics/skills/tree/main/skills/frontend-design ; LICENSE.txt du dossier consulté : Apache-2.0.
- **S04** — Vercel, agent-skills : https://github.com/vercel-labs/agent-skills ; dossiers web-design-guidelines, react-best-practices, composition-patterns. Vérifier le manifeste et les fichiers de licence à la révision retenue.
- **S05** — OpenAI, skills navigateur : https://github.com/openai/skills/tree/main/skills/.curated/playwright et https://github.com/openai/skills/tree/main/skills/.curated/screenshot.
- **S06** — Laravel, Sanctum : https://laravel.com/docs/13.x/sanctum.
- **S07** — JetBrains, Qodana PHP et GitHub Actions : https://www.jetbrains.com/help/qodana/php.html ; https://www.jetbrains.com/help/qodana/github.html.
- **S08** — GitHub, sécurisation des workflows : https://docs.github.com/en/actions/reference/security/secure-use.
- **S09** — W3C, contraste textuel : https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html.
- **S10** — W3C, taille minimale de cible : https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html.
- **S11** — W3C, contraste non textuel : https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html.
- **S12** — Playwright, tests d’accessibilité et limites : https://playwright.dev/docs/accessibility-testing.
- **S13** — Figma skill, conditions spécifiques : https://raw.githubusercontent.com/openai/skills/main/skills/.curated/figma-implement-design/LICENSE.txt.


## Spécification autonome des compléments communautaires

# HAAS — Communauté, projets et découverte

**Base :** mail de cadrage fourni [M], décisions de l’équipe [U], cahier des charges consolidé.  
**Statut :** spécification de conception [H] à implémenter et à vérifier. Aucun usage réel n’est attesté par ce document.

## 1. Positionnement et limite

HAAS est d’abord une plateforme communautaire : découvrir des développeurs et leurs projets, poser des questions, partager des connaissances et collaborer. L’atelier est sa fonctionnalité distinctive pour certains cas, pas une condition d’entrée. Un échange utile peut s’achever sans code, capsule, laboratoire ou comparaison.

**Promesse : Présentez vos projets. Trouvez de l’aide. Construisez ensemble.**

Deux compléments P0 sont retenus : **F16, fiches projet et demandes liées ; F17, découverte volontaire des développeurs**. F02 est précisé pour accepter une question de connaissance sans formulaire de bug. Il ne s’agit pas de trois nouveaux produits. Pas de fil social infini, de messagerie privée, de recrutement, de gestion d’équipe, de dépôt de code interne ni de nouveau moteur de test.

## 2. F16 — Fiche projet légère

### Données et présentation

| Champ | Règle proposée |
|---|---|
| id | UUID serveur ; adresse stable /projets/:id. |
| owner_id | Utilisateur authentifié ; jamais accepté du navigateur. |
| name | 3–80 caractères. |
| summary | 30–240 caractères ; description lisible sur une carte. |
| description | 80–4 000 caractères ; Markdown restreint et texte inerte. |
| project_stage | idea, prototype, in_development, launched ; état déclaré par l’auteur, pas validation de qualité. |
| technologies | 1–5 entrées du référentiel partagé ; aucune nouvelle taxonomie séparée. |
| help_sought | Facultatif, 1 500 caractères maximum ; ce que l’auteur recherche. |
| demo_url / repository_url | Facultatifs, HTTPS, 2 048 caractères maximum, sans identifiants intégrés ; jamais récupérés par le serveur. |
| publication_state | draft, published, archived ; distinct de project_stage et de la modération. |
| hidden_at / moderation_reason | Champs serveur pour retrait ; raison publique expurgée si nécessaire. |
| lock_version / timestamps | Conflit 409 si édition obsolète ; UTC, historique et audit. |

Aucune image téléversée, capture automatique, iframe de démonstration, prévisualisation OpenGraph, clone Git, import de dépôt ou exécution n’est requis. Utiliser un visuel initiales/technologies. Présenter un projet ne transfère pas les droits sur son dépôt, ne prouve pas sa propriété intellectuelle et n’autorise aucune copie de son code.

### Cycle et permissions

Le propriétaire actif et vérifié peut créer un brouillon, modifier ses champs, publier et archiver. La publication exige une fiche complète et une confirmation de visibilité. Les modifications publiées sont historisées ; le nom ou le statut n’est pas modifié silencieusement dans les traces de contribution. Un projet archivé reste consultable s’il était public et non masqué, avec l’étiquette « Archivé » ; il n’accepte plus de nouvelle demande liée et il est exclu par défaut du catalogue.

Un modérateur peut masquer/restaurer avec motif, pas réécrire le contenu au nom du propriétaire. Une restauration ne republie pas un brouillon et ne modifie pas la phase déclarée. Un membre suspendu ne peut pas publier, modifier, relier ou archiver. La suspension masque de la découverte ses projets ; la revue décide de la suite selon les règles de modération existantes.

Créer et publier sont des commandes métier distinctes. La création utilise l’idempotence commune ; les mises à jour et transitions contrôlent lock_version. Il n’y a ni publication sur simple PATCH de publication_state, ni suppression destructive de conversations.

### Catalogue et détail

Catalogue public paginé : recherche dans nom/résumé, technologie, phase déclarée, tri date de publication puis id ; pas de score de popularité. Filtres dans l’URL, page de 20, maximum 50. Indexer seulement les requêtes retenues et mesurer le résultat. Le détail montre le propriétaire, les technologies, le besoin d’aide et les échanges accessibles. Aucun compteur ne révèle des demandes privées.

### Lien entre projet et demande

`help_requests.project_id` est facultatif. Dans ce périmètre, **seul le propriétaire d’un projet peut lui rattacher sa propre demande**, et uniquement quand le projet est publié, non masqué, non archivé. Cette restriction simple évite des associations trompeuses. Un visiteur contribue ensuite dans les demandes publiques du projet ; il ne devient pas membre d’une équipe ni coauteur du dépôt.

Le projet ne reçoit pas un second fil de commentaires : toutes les discussions utilisent les demandes, commentaires et propositions existants. Sur une fiche propriétaire : « Demander de l’aide pour ce projet ». Pour un autre membre : « Voir les échanges » et, lorsqu’une demande est ouverte, « Proposer une aide » vers cette demande. Si aucune demande n’est ouverte et help_open=true, proposer « Je peux t’aider » vers F18 : une offre bornée, puis création d’un fil après consentement et acceptation du propriétaire. Si les coups de main sont fermés, l’expliquer et proposer d’explorer d’autres besoins ; aucun faux bouton de contact.

Changer le rattachement exige une demande encore en brouillon et aucun commentaire/proposition. Après publication, le lien est stable ; un retrait nécessaire passe par la modération auditée. Une demande liée n’hérite jamais d’un droit d’accès depuis une URL.

### Propagation de visibilité

Un projet masqué ou un propriétaire suspendu rend les listes et détails des demandes rattachées inaccessibles au public, ainsi que leurs commentaires, cas et propositions. Contrôler l’ancêtre dans Query/Policy, recherches, compteurs, notifications, caches et accès direct. Les personnes habilitées conservent une vue de traitement. Un lien enregistré n’accorde jamais le droit de lire.

Une capsule est un objet documentaire indépendant et soumis à revue : si elle cite ce projet/demande devenu masqué, supprimer les détails et extraits du lien source dans la Resource. Si elle contient elle-même un secret ou le contenu retiré, la masquer/revoir selon la procédure existante ; ne pas promettre qu’un simple masquage efface un secret déjà divulgué.

## 3. F17 — Découverte volontaire des développeurs

L’annuaire est un moyen simple de se retrouver, pas un système d’amis, de messagerie ou de recrutement. La publication d’un profil dans l’annuaire est une préférence distincte des contributions publiques déjà attribuées.

| Champ dans profiles | Règle proposée |
|---|---|
| directory_visible | Booléen, false par défaut ; choix explicite modifiable par le membre. |
| help_availability | not_specified, available, unavailable ; auto-déclaré, jamais présence en ligne. |
| contribution_interests | Texte facultatif, 300 caractères maximum, sans coordonnées privées obligatoires. |
| availability_updated_at | Horodatage serveur lors d’un changement de disponibilité ; pas last_login. |

Une inscription visible dans l’annuaire exige compte actif, courriel vérifié et directory_visible=true. Seules les propriétés publiques autorisées sont retournées : pseudonyme, bio courte, technologies, langue, intention d’aide et liens vers les contributions/projets visibles. Ne pas exposer courriel, IP, sessions, téléphone, date de dernière connexion, statut administratif ou données de modération.

Filtres P0 : pseudonyme/recherche textuelle simple, technologie, disponibilité déclarée. Pagination 20/max50 et tri stable par pseudonyme puis id. Pas d’inférence d’expertise, de recommandation IA, de géolocalisation ou de filtre politique/personnel. Le pays demeure facultatif dans le profil existant, mais aucun filtre pays n’est nécessaire au P0.

« Ouvert à une relecture Laravel » ne promet ni réponse ni délai. La date de mise à jour est consultable. Retirer sa présence de l’annuaire purge les résultats/cache concernés, sans effacer automatiquement l’attribution d’une contribution publique. Un compte suspendu ne figure plus dans l’annuaire, même par filtre ou requête directe. Les paramètres privés du propriétaire restent consultables selon le parcours existant du compte.

La collaboration se poursuit dans une demande publique. Le profil conduit aux projets/contributions ; sur un projet ouvert, F18 permet de proposer un coup de main même sans fil initial. Ce n’est ni un contact privé général ni une invitation d’équipe. Le propriétaire accepte et confirme la projection publique du résumé consenti.

## 4. F02 précisé — Poser une question sans être en panne

`help_intent` ajoute **ask_question** aux valeurs existantes unblock, review_solution et reproduce_behavior. Cette extension conserve le même fil, les mêmes autorisations et le même service de discussion ; elle ne crée pas un forum parallèle.

Pour ask_question : titre 15–140, contexte dans goal 30–2 000, question dans observed 30–4 000, 1–5 technologies. Les champs expected, attempts, code et environnement sont facultatifs ; ils restent affichables s’ils existent. Expected/attempts deviennent nullables pour ce mode ; migration non destructive et validation conditionnelle explicite. Les autres intentions conservent leurs règles. Le simple mot « aucune » n’est pas exigé pour remplir artificiellement un champ inutile.

Libellés : « Votre question », « Ce que vous souhaitez comprendre », « Ajouter un exemple — facultatif ». Expliquer avant publication que l’échange sera public. Une discussion peut apprendre quelque chose sans accepter une proposition, publier une capsule ou exécuter un test. Les commentaires restent disponibles ; une réponse structurée et sa résolution utilisent les règles actuelles sans transformer l’absence de laboratoire en erreur.

## 5. Architecture — extensions ciblées

### Laravel

Créer Models/Project, Enums/Projects/{ProjectStage,ProjectPublicationState}, Enums/Identity/HelpAvailability, ProjectPolicy, Data/Projects, Requests/Projects, Resources/Projects, Queries/Projects et Services/Projects. Commandes : CreateProject, UpdateProject, PublishProject, ArchiveProject et Moderation dédiée au type project. Les classes portent le suffixe Service selon la convention existante.

Identity conserve les préférences de profil et l’annuaire : UpdateProfileRequest/Data/Service étendus par liste de champs, ListDevelopersRequest/Query et DeveloperSummaryResource séparée de MeResource. Pas de sérialisation brute de User. HelpRequests vérifie le lien au projet et l’intention ; pas de ProjectService monolithique ni d’accès Eloquent dispersé dans les contrôleurs.

### Données

Table projects (UUID, owner_id, données éditoriales, états séparés, lock_version, published_at, hidden_at, timestamps). Pivot project_technologies unique (project_id,technology_id). Ajouter help_requests.project_id nullable avec FK ; aucune suppression en cascade des discussions. Étendre profiles et enum HelpIntent ; migration de données existantes contrôlée.

Index proposés : projects(publication_state,published_at,id), projects(owner_id,created_at), project_technologies(technology_id,project_id), help_requests(project_id,created_at). L’annuaire filtre le statut/vérification dans users et l’opt-in dans profiles ; mesurer avant d’ajouter un index supplémentaire.

### API cible

| Méthode et route /api/v1 | Contrat |
|---|---|
| GET /projects | Catalogue public filtré ; brouillons/masqués jamais exposés. |
| GET /projects/{project} | Détail accessible ; capacités calculées selon l’acteur. |
| POST /projects | Créer son brouillon ; membre actif vérifié ; Idempotency-Key. |
| PATCH /projects/{project} | Champs autorisés et lock_version ; propriétaire. |
| POST /projects/{project}/publish | Valider fiche/visibilité puis publier ; propriétaire. |
| POST /projects/{project}/archive | Archiver avec motif et lock_version ; propriétaire. |
| GET /projects/{project}/requests | Seulement demandes et compteurs visibles à cet acteur. |
| GET /developers | Liste volontaire, comptes actifs vérifiés, filtrage borné. |
| PATCH /me/profile | Étendre la route de profil existante ; si son chemin diffère dans le dépôt, garder un seul chemin et le documenter. |
| POST /requests | Ajouter project_id facultatif et help_intent=ask_question ; toutes les règles existantes s’appliquent. |
| POST /reports | Type project dans la liste autorisée ; pas de classe libre. |

Les actions de modération réutilisent les routes d’administration des signalements. Erreurs HAAS communes (401/403/404/409/422/429), tests de schéma et types OpenAPI générés. Une route ci-dessus est une cible, pas une implémentation déjà fournie.

### React

Créer features/projects et features/developers, avec api/models/schemas/hooks/queries/components/pages/tests selon besoin. Le client HTTP, guards, erreurs, forms et composants sont réutilisés. `shared` n’importe pas ces features. Les modèles de transport viennent d’OpenAPI, pas d’un doublon manuel.

Capacités `can.edit`, `can.publish`, `can.archive`, `can.create_request` calculées par le serveur. Les boutons suivent ces capacités sans remplacer les Policies. Invalider catalogue, détail, listes de demandes et profils concernés après mutation. Un retrait de l’annuaire purge la recherche et le cache de profil adapté sans fabriquer un nombre de membres.

## 6. Navigation et usage

Navigation principale : **Explorer · Projets · Développeurs**, bouton d’action **Demander de l’aide**. Dans le compte : **Mon espace · Mes contributions · Notifications**. Sur mobile, liens principaux dans un menu nommé, pas deux barres empilées avec dix destinations. Pas de laboratoire en action principale de l’accueil.

Accueil : promesse communautaire, « Explorer les projets » et « Demander de l’aide », trois aperçus de contenu public étiqueté s’il est éditorial. Un lien secondaire mène aux développeurs. Aucun compte demandé avant de comprendre l’utilité ; connexion demandée au moment de contribuer.

Le parcours de réception est : A publie un projet, ouvre une demande liée ; B le découvre et échange ; un cas ou une proposition apporte de la valeur ; A accepte si pertinent ; une capsule documentée permet à C de reprendre la solution. L’atelier B1 reste une seconde branche du même récit, jamais une étape forcée de toutes les interactions.

## 7. Charge et validation

F16/F17/ask_question ont apporté BC01–BC08/FC01–FC04. Le plan actif compte maintenant 122 lots S2/B72/F40/R8, car F18 ajoute BH01–BH10/FH01–FH05/RH01. Les 106 identifiants précédents sont conservés. Ne pas écraser le suivi du dépôt par ces états TODO documentaires.

AC53–AC68 restent la recette communautaire ; AC69–AC90 complètent les coups de main. UX18–20 restent applicables, avec leurs CTA F18 ; UX21–23 décrivent la découverte d’occasions, l’offre et sa décision. Les gates couvrent F01–F18 ; aucune ancienne approbation ne reçoit automatiquement ces ajouts.

## 8. F18 — Du profil au premier échange

Le parcours d’offre remplace l’impasse sans demande initiale : projet volontaire, proposition bornée, consentements explicites, propriétaire qui accepte et autorise un fil. Les détails actifs figurent dans COUPS_DE_MAIN.md et COUPS_DE_MAIN_API.md. Pas de messagerie privée, accès au dépôt ou équipe ; l’opt-in annuaire n’est pas obligatoire pour aider. Les demandes ordinaires restent utilisables directement.


## Spécification autonome — Rencontre par le coup de main

# HAAS — La rencontre par le coup de main

**Exigence : F18.** Décision de conception retenue par la demande de mise à jour du ZIP, le 1er octobre 2026. **Source :** proposition « La rencontre par le coup de main » puis accord de l’utilisateur. Le mail CADEV décrit les usages ; il n’impose ni cette fonction ni ses détails techniques. **Statut :** à développer et à vérifier ; aucune application ni collaboration réelle n’est attestée dans ce pack.

> Rencontrez-vous en construisant quelque chose ensemble.

## 1. Résultat visé et périmètre

Un propriétaire ouvre son projet à un petit coup de main. Une autre personne propose un apport concret. Le propriétaire accepte et un échange public contextualisé commence dans les fils existants. Une réponse, un test, une relecture ou une amélioration de documentation peut ensuite contribuer au projet. La capsule et le laboratoire restent facultatifs pour les personnes ; les démonstrateurs prévus restent à livrer.

Deux entrées d’accueil : **« Faire avancer mon projet »** et **« Donner un coup de main »**. Explorer les projets, lire les solutions ou poser une question reste possible sans choisir une entrée. Aucune contribution préalable n’est exigée pour demander de l’aide.

F18 ajoute une offre bornée avec consentement, pas une messagerie générale, un contrat de prestation, une équipe ou un accès au dépôt. Aucun paiement, calendrier, rendez-vous, délai de réponse garanti, chat privé, terminal, classement ou moteur IA. Un seul échange d’offre avant acceptation ; pas de réponses privées imbriquées.

**Évolution explicite de F16 :** en l’absence de demande ouverte, un membre peut maintenant proposer son aide si le propriétaire a activé l’ouverture aux coups de main. Le tiers ne peut toujours pas créer librement une demande au nom du propriétaire ni la rattacher à son projet. C’est l’acceptation du propriétaire qui autorise le lien, dans une transaction. Les discussions existantes restent utilisables quand les offres spontanées sont fermées.

## 2. Ouverture volontaire et types de contributions

Ajouter aux projets `help_open`, faux par défaut, et `help_categories`. Pour ouvrir : propriétaire actif et vérifié, projet publié et non masqué/archivé, `help_sought` décrivant le petit résultat attendu (30–1 500 caractères), 1 à 5 catégories. Aucun projet existant ne devient ouvert lors de la migration. Fermer ne supprime ni projet ni discussion.

| Valeur stable | Libellé UI | Exemple de contribution |
|---|---|---|
| usability_feedback | Retour sur l’interface | Signaler où le parcours devient difficile. |
| code_review | Relecture technique | Examiner un extrait volontairement partagé. |
| reproduce_behavior | Reproduction d’un comportement | Décrire les étapes et l’observation obtenue. |
| explain_concept | Explication | Clarifier un concept avec son contexte. |
| documentation | Documentation | Essayer ou améliorer une procédure. |

Ces catégories décrivent une intention, pas une expertise certifiée. Dans le profil, `preferred_help_categories` est facultatif ; les technologies existent déjà. La visibilité annuaire (`directory_visible`) reste un consentement distinct : aider n’exige pas l’opt-in à l’annuaire. Un compte suspendu ne peut ni ouvrir, proposer, accepter, retirer ou décliner ; il garde seulement les accès de recours existants.

## 3. Découvrir une occasion concrète d’aider

`GET /api/v1/help-opportunities` agrège deux types explicites :

- `request` : demande publique ouverte/en cours, auteur actif, visibilité parent respectée. L’action mène au fil existant pour y contribuer directement, sans offre obligatoire.
- `project` : projet publié, non masqué/archivé, propriétaire actif, `help_open=true`. L’action propose un coup de main. Si une demande publique pertinente existe, la carte propose d’abord de la consulter ; un projet ne doit pas remplir le flux avec des doublons de cartes quasi identiques.

Filtres en liste autorisée : technologie, catégorie, type ; pagination 20/max50, tri `published_at DESC, id ASC` stable, pas de score social. Les demandes ne portent pas toutes une catégorie : ne pas en inférer une ; le filtre catégorie ne les retient que si une catégorie a été déclarée dans le besoin. Pour limiter la migration, utiliser la catégorie du projet pour les demandes liées et présenter cette origine explicitement. Les demandes autonomes sans catégorie restent dans la vue non filtrée.

Les préférences ne servent qu’à préremplir les filtres après accord et à expliquer les correspondances exactes. Le visiteur peut modifier/retirer chaque filtre. Afficher par exemple « React figure parmi vos technologies sélectionnées ». Ne pas écrire « Le meilleur développeur pour vous ». Les raisons sont dérivées de champs publics, jamais du dernier login ou d’une inférence d’expertise.

Une liste vide signifie seulement qu’aucun besoin visible ne correspond. Actions : retirer un filtre, explorer les projets, poser une question. Une panne réseau ne devient pas un état vide. Le catalogue public ne révèle aucun nombre d’offres en attente ou refusées.

## 4. Proposition limitée et consentement à la publication

Un membre actif vérifié, autre que le propriétaire, remplit une fiche courte. Avant acceptation, seul le proposant, le propriétaire et la modération habilitée sur un signalement peuvent lire l’offre. Il n’existe pas de galerie publique d’offres ni de refus publics.

| Champ proposé | Contrat |
|---|---|
| category | Une catégorie actuellement ouverte sur le projet. |
| public_summary | 30–800 caractères ; apport concret, pas coordonnées personnelles ni secret. |
| expected_outcome | 20–500 caractères ; résultat limité : observation, explication ou procédure. |
| allow_public_summary | Booléen explicitement true : accord pour afficher ces deux textes dans un échange public si l’auteur accepte. Jamais précoché. |

L’aperçu montre exactement les deux textes qui pourront devenir publics, le projet et le pseudonyme attribué. Le formulaire prévient : « Cette proposition est visible par vous et le propriétaire. Si elle est acceptée, ce résumé et le résultat proposé rejoindront un échange public. » Le contact e-mail n’est jamais ajouté. Pas de texte caché importé depuis une conversation privée, pas de fetch de lien, d’image, de fichier ou d’exécutable.

Les textes d’une offre soumise sont figés pour cette décision. Pour les modifier, retirer l’offre encore en attente puis en créer une nouvelle, dans les limites d’usage. Le propriétaire ne modifie pas silencieusement les mots du proposant.

Le refus ne donne pas de malus ; le proposant peut retirer une offre en attente. Un motif de refus est facultatif, court, privé aux deux acteurs, modérable et non recopié dans les notifications. Pas de rappel automatique agressif.

## 5. États et transitions

`HelpOfferState = pending | accepted | declined | withdrawn | expired`.

| État de départ | Action / acteur | État d’arrivée | Effet |
|---|---|---|---|
| absent | proposer / membre autorisé | pending | Offre privée, expiration à 7 jours UTC, notification minimale au propriétaire. |
| pending | accepter / propriétaire | accepted | Crée ou rejoint UN fil autorisé, publie le résumé consenti, inscrit un événement attribué. |
| pending | décliner / propriétaire | declined | Aucun fil, aucune pénalité publique. |
| pending | retirer / proposant | withdrawn | Aucun fil ; possibilité de proposer de nouveau dans les limites. |
| pending | échéance atteinte / système | expired | Aucun fil ni acceptation tardive ; motif interne `time_limit`. |
| pending | fermeture, archivage, masquage ou suspension / système | expired | Motif interne adapté ; pas de refus attribué artificiellement au propriétaire. |
| accepted | consulter le fil | accepted | L’accord est une trace historique, pas un travail achevé. |

Les états terminaux sont immuables ; pas de retour automatique à pending. Une offre acceptée ne porte pas de statut « terminé », de score ou de preuve technique. Le travail se poursuit dans le fil. Si quelqu’un s’arrête, il peut le signaler dans le fil ; l’archivage/réouverture existants restent les mécanismes de suivi. Le retrait d’un contenu dangereux peut expurger la projection publique via la modération, sans falsifier l’historique.

L’expiration est évaluée sur le serveur à chaque lecture ou commande critique, pas uniquement par cron. La purge physique et la rétention suivent une décision documentée de confidentialité ; « expiré » ne signifie pas supprimé.

## 6. Accepter sans fabriquer un projet, un fil ou un résultat

La page d’acceptation montre l’offre figée et l’espace public de destination. Le propriétaire choisit explicitement :

**Créer un échange.** Le titre, le contexte, la question/le point à examiner et l’intention sont préremplis pour économiser la saisie, mais restent à relire. La publication valide tous les champs nécessaires au même `CreateHelpRequestService` que les demandes ordinaires. Pour `ask_question`, aucun code, expected ou attempts n’est exigé. `publish_consent=true` est un accord distinct du propriétaire, non précoché. Le serveur fixe `author_id` au propriétaire actuel ; aucune usurpation du proposant.

**Rejoindre un échange.** Le propriétaire sélectionne une de ses demandes publiques, ouvertes/en cours, rattachées à CE projet. L’interface affiche le fil exact ; pas de sélection silencieuse « dernier fil ». La commande vérifie de nouveau tous les droits/états. Aucun titre, texte ou contenu déjà présent n’est remplacé.

Dans les deux cas : transaction unique pour décision, création éventuelle du fil, lien unique et projection publique du résumé consenti. Un événement système `help_offer_accepted` porte l’acteur décideur et le proposant distinctement ; il n’est pas présenté comme un commentaire rédigé par ce dernier. Aucune « contribution terminée » n’est ajoutée. Les permissions du proposant restent celles d’un membre ordinaire.

Une seule offre active en attente par couple projet/proposant. Pour éviter la multiplication accidentelle de fils, l’acceptation n’a jamais un mode implicite. Elle crée une nouvelle demande uniquement après confirmation de ce choix ; sinon elle rattache le fil sélectionné. Plusieurs offres acceptées peuvent rejoindre le même fil si le propriétaire le choisit explicitement.

## 7. Transactions, doublons et limites d’usage

Toutes les commandes sensibles utilisent actor serveur, DTO validé, Policy, transaction, audit et Resource whitelist. `lock_version` obligatoire sur les transitions ; `Idempotency-Key` obligatoire sur création et acceptation. Le rejeu de la même clé/empreinte rend le même identifiant sans nouvelle discussion/notification. Même clé et corps différent : 409. Les droits et la visibilité courants sont revérifiés avant lecture d’une réponse mémorisée ; ne pas rendre un ancien contenu devenu masqué.

Ordre de verrouillage à documenter et partager pour les commandes qui touchent plusieurs objets : lignes de quotas/acteurs nécessaires dans un ordre déterministe, projet, offre, puis demande liée. Ne jamais acquérir ces verrous en ordre inverse. Les services appelés ne doivent ni ouvrir un second commit ni envoyer de mail dans la transaction. Revalider projet ouvert, catégorie, acteurs, délai, état et lien. Les autres commandes qui peuvent invalider ces invariants participent au protocole de verrouillage et aux tests de concurrence (fermeture, suspension, archivage, acceptation et retrait).

Contraintes minimales : index unique partiel `help_offers(project_id, proposer_id) WHERE state='pending'` ; unicité de la projection publique `source_offer_id` ; FK de `accepted_request_id` avec contrôle du même projet/auteur ; `accepted` implique request_id/accepted_at non nuls ; états et dates cohérents. Pas de cascade qui efface les discussions. Expirer les offres périmées avant de libérer/créer une nouvelle offre ; un index unique ne gère pas le temps automatiquement.

Limites proposées et configurables : **5 créations par 24 heures glissantes et par membre**, **5 offres en attente globalement par membre**, **1 offre en attente par projet/membre**. Deux créateurs concurrents ne contournent pas ces limites : réservation atomique côté serveur, pas un COUNT puis INSERT non verrouillé. Ces seuils sont des choix à ajuster après essai, pas des garanties de réponse ni un quota de laboratoire. Notification interne une fois après commit par événement, avec rattrapage/clé d’unicité prévus selon les mécanismes existants.

## 8. Confidentialité, retraits et modération

Avant acceptation, l’offre ne figure ni dans recherche publique, ni profil, ni compteur, ni payload d’un projet. Après acceptation, seule sa projection consentie est visible dans le fil si tous les parents sont visibles. Les états declined/withdrawn/expired, motifs privés et identifiants d’acteurs non publics ne sont jamais divulgués par la fiche de progrès.

Le masquage/archivage ferme les nouvelles offres et empêche leur acceptation ; le masquage rend les fils liés inaccessibles au public selon F16. Une fermeture de `help_open` seule n’efface pas les fils déjà ouverts. La suspension d’un proposant n’invente pas un échec de son travail ; elle bloque ses actions et les décisions en attente. Le traitement respecte la différence entre l’annuaire opt-in et l’attribution publique existante.

La modération peut consulter une offre signalée dans le dossier concerné, masquer sa projection ou traiter le signalement avec audit. Aucun administrateur non propriétaire ne l’accepte au nom de l’auteur. Aucune donnée brute d’offre dans les logs ou notifications ; lien authentifié et message minimal. À la déconnexion : annuler lectures et vider les caches privés, y compris les offres reçues/envoyées. Ne pas mettre ces réponses dans un cache CDN public.

## 9. Voir ce que nous avons amélioré ensemble

La fiche projet affiche une vue des échanges et résultats autorisés : contribution liée, résolution validée par l’auteur, capsule publiée et, lorsque disponible, vérification de la bonne version. L’offre acceptée peut apparaître comme **« Collaboration commencée »**, jamais comme **« Amélioration réalisée »** avant un résultat enregistré.

Cette vue ne crée ni nouveau score, ni certificat, ni pourcentage de projet terminé. Elle n’affiche pas les offres en attente. Une explication utile sans résolution peut rester visible comme échange, sans être promue automatiquement en correction vérifiée. Les données de démo sont identifiées et exclues des chiffres d’adoption.

## 10. API, écrans, livraison

Lire `docs/api/COUPS_DE_MAIN_API.md`, les écrans UX21–23 et les tests AC69–90. Les classes et routes sont des spécifications à implémenter dans l’architecture existante ; pas un serveur livré. Les technologies, catégories et états sont typés ; aucune requête HTTP dans JSX, pas de service global contenant tous les cas d’usage.

Les offres ne lancent jamais un laboratoire. Un cas documentaire doit toujours suivre la revue et la release des scénarios approuvés. Systalink + Vercel, PostgreSQL local, runner restreint, Qodana Ultimate et les portes humaines restent inchangés. Les seize lots BH01–BH10/FH01–FH05/RH01 complètent le plan ; chaque lot est découpable en petits commits testables. Réestimer le travail à partir du dépôt. Retirer les P1 avant d’alléger les droits, consentements ou tests de concurrence.

## 11. Pilote et démonstration honnêtes

Scénario fictif : Awa présente une application et ouvre un retour d’interface ; Moussa propose de tester ; Awa accepte ; un fil public consenti est créé ; l’observation puis la correction sont documentées. Une comparaison B1 peut ensuite illustrer un cas approuvé. Le résumé d’offre, la contribution réalisée et le rapport machine restent distingués.

Cible qualitative proposée : observer trois petits coups de main entre testeurs volontaires, en consignant aussi refus, abandons et non-réponses. Mesurer découverte→offre, offre→acceptation et acceptation→premier apport utile. Ne pas interpréter une acceptation comme un travail accompli. Un testeur doit pouvoir expliquer ce qui deviendra public avant de confirmer. Les résultats, durées et noms ne sont renseignés que s’ils ont réellement été observés et autorisés à la présentation.


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
