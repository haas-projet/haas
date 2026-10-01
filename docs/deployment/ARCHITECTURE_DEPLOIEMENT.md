# HAAS — Architecture de déploiement simplifiée
**Date :** 1er octobre 2026. **Statut :** cible de réalisation. Aucun serveur acheté, configuré ou testé par la production de ce dossier.
**Décision active :** ADR-004. Une machine Systalink, une interface Vercel, des sauvegardes distantes.

## 1. Topologie retenue
```text
Utilisateur / navigateur
  |-- HTTPS --> Vercel : React + TypeScript + Vite
  |                app.haas.example.com
  |
  `-- HTTPS --> Systalink : api.haas.example.com
                    Nginx -> PHP-FPM -> API Laravel
                                  |-- PostgreSQL : haas_app
                                  |-- sessions / queue / cache de base
                                  `-- orchestrateur -> service LAB local restreint
                                                       PostgreSQL : haas_lab
                    |
                    `-- sauvegarde chiffrée -> stockage distant

GitHub Actions + Qodana Ultimate -> contrôles -> accord humain
                                 -> API Systalink -> frontend Vercel
```
Les hôtes `example.com` sont des EXEMPLES ; ils ne sont ni disponibles ni provisionnés par ce dossier. Le navigateur charge React depuis Vercel, puis appelle directement l'API HTTPS. Les réponses privées ne transitent pas par un cache public de Vercel. Vercel ne sert ni PHP ni PostgreSQL dans cette architecture. [D03, D06]

## 2. Ressources et limites
| Élément | Cible retenue | Portée |
|---|---|---|
| VPS Systalink | Business : 2 vCPU, 8 Go RAM, 100 Go NVMe | Référence publique de départ ; éligibilité, région et panier à confirmer. |
| React/Vite | Un projet Vercel principal | Offre et droits des deux contributeurs à valider ; pas de fonctions serveur indispensables. |
| PostgreSQL | Bases distinctes `haas_app`, `haas_lab` ; B2 distincte | Même instance possible, rôles non super-utilisateurs et accès réellement restreints. |
| Exécuteur LAB | Un run actif global ; comparaison séquentielle | Service local sous un autre compte, sans `.env` Laravel et sans base métier. |
| Sauvegarde | Stockage distant ; chiffrement côté client | Le panier est distinct du VPS ; restauration à tester. |
| Recette | Environnements locaux/CI jetables | Pas de préproduction distante permanente facturée. Un environnement temporaire supplémentaire demande accord et budget. |

Systalink affiche la référence Business ; le dimensionnement reste une hypothèse à éprouver, pas une garantie de débit. La gestion du VPS n'est pas présumée incluse. [D01]

## 3. Processus et responsabilités
`haas-app` exécute PHP-FPM et les jobs métier. Il utilise uniquement les permissions requises sur `haas_app`. Une identité de migration distincte applique les changements de schéma. Nginx n'expose que le dossier `public/` de Laravel, jamais la racine du dépôt.

L'orchestrateur autorise chaque opération, crée ses identifiants, réserve le quota et conserve les résultats dans la base métier. L'exécuteur `haas-lab` reçoit seulement des identifiants de scénarios et de manifestes approuvés, des identifiants de run et les fixtures canoniques autorisées. Le résultat est un JSON borné et validé. Le code de la brique est livré par la CI, pas téléchargé à la demande.

**Frontière locale proposée :** service séparé, canal Unix local et permissions de groupe permettant au seul orchestrateur d'émettre une demande. Aucun port public, shell arbitraire ou nom de classe transmis par le navigateur. Le runner est un paquet d'exécution minimal, séparé du bootstrap Laravel principal : lancer `php artisan` dans le projet principal sous un autre nom d'utilisateur ne suffit pas.

Le runner n'a ni `APP_KEY`, ni accès au code/configuration privés de l'API, ni identifiants SMTP, GitHub, Vercel ou sauvegarde. Son rôle SQL n'accède qu'aux fixtures ; il ne lit pas la queue applicative. Il renvoie ses observations à l'orchestrateur via le canal déjà ouvert. Les fichiers, sockets et identités effectifs figurent dans le runbook.

## 4. Droits et ressources du laboratoire
Séparer les répertoires `/srv/haas-api`, `/opt/haas-lab` et les espaces de travail temporaires. Retirer au compte laboratoire tout groupe donnant accès aux secrets APP. La configuration du service limite les fichiers accessibles, les familles réseau, la mémoire, le CPU et le nombre de processus ; vérifier ces options sur l'OS retenu. Pas de droit Docker, sudo générique ou montage du socket d'administration.

Les rôles PostgreSQL ne sont ni propriétaires de la base métier ni super-utilisateurs. Contrôler `CONNECT`, les schémas et les droits accordés par défaut à `PUBLIC`. Tester explicitement la lecture de `users`, la connexion à `haas_app`, la lecture du `.env` et l'ouverture des sauvegardes sous l'identité laboratoire : ils doivent échouer. La seule création d'un deuxième nom de base ne constitue pas cette preuve.

Une borne de ressources est définie après mesure ; point de départ proposé : au plus 512 Mio pour le runner, consommation CPU limitée et un processus d'exécution à la fois. Les traitements PostgreSQL, même isolés par rôle, consomment les ressources du même serveur : limiter aussi les connexions et durées de requêtes. Mesurer la latence de l'API pendant une comparaison avant d'accepter la configuration.

**Limite :** un incident de noyau, d'hôte ou de PostgreSQL reste partagé. En cas d'accès indu ou de perturbation significative de l'API, garder le laboratoire fermé et corriger les restrictions ou revoir l'hébergement. Ne jamais présenter un service suspendu comme un laboratoire livré.

## 5. Quotas et sincérité
Un run simple consomme une unité ; une comparaison en réserve deux atomiquement. Cinq unités par heure et par membre, une opération active par membre, **un run actif globalement**. La même clé idempotente ne réserve pas deux fois. Les deux enfants d'une comparaison sont successifs, avec données neuves et mêmes entrées attendues. La file est bornée ; un quota dépassé donne une réponse 429 explicite.

Les bornes retenues du cahier sont 15 secondes de traitement visées et 20 secondes maximum par enfant ; 50 secondes après démarrage pour la comparaison. Le temps en file est affiché séparément. Une interruption, un résultat mal formé ou une version indisponible donne erreur/délai dépassé et aucune conclusion favorable. Le réconciliateur libère les réservations d'exécution abandonnées sans fabriquer de test réussi.

## 6. Domaines, TLS et navigateur
| Usage | Hôte d'exemple | Destination |
|---|---|---|
| Application principale | `app.haas.example.com` | Vercel, projet HAAS |
| API principale | `api.haas.example.com` | VPS Systalink |
| Démonstrateur B2 | `demo.example.com` | Projet Vercel B2 séparé, si B2 est livré |
| API B2 | `demo-api.example.com` | Vhost/pool/service distinct sur le VPS, uniquement données fictives |

Seuls les hôtes de confiance appartiennent à `.haas.example.com`. Les deux hôtes B2 sont hors de ce périmètre : aucun cookie HAAS ne doit y être envoyé. Ils n'appellent pas la base métier. Leurs réglages et artefacts sont distincts ; cela n'ajoute pas un VPS.

Saisir les enregistrements DNS effectivement demandés par Vercel et l'adresse publique réellement attribuée par Systalink : aucune IP n'est inventée. TLS sur les quatre hôtes retenus, renouvellement surveillé. Session Secure/HttpOnly, CSRF lisible par le client selon Sanctum. Ne pas confondre même site avec même origine. Voir `AUTH_CORS_SANCTUM.md`. [D03, D06]

## 7. Déploiement Vercel
Projet principal : racine `frontend`, Vite, `npm ci`, `npm run build`, sortie `dist`. Ajouter la réécriture SPA pour les liens profonds et tester les assets : une URL de ressource manquante ne doit pas servir silencieusement un écran de connexion. Pas de réécriture proxy `/api` prévue ; le client emploie l'origine API configurée. [D06]

`VITE_API_URL` contient uniquement l'origine publique. Aucun secret dans `VITE_*`. La séparation Preview/Production porte sur domaines, variables et données. Les previews automatiques `*.vercel.app` ne sont jamais ajoutées en bloc à CORS ou aux domaines Sanctum. Une preview non autorisée reste une revue visuelle sur données fictives ; la recette fonctionnelle se déroule avec une API dédiée de test et des domaines compatibles. Aucune prévisualisation non fiable n'obtient les secrets ni les données de production.

Le forfait doit permettre le dépôt et la collaboration : Hobby n'est pas acquis comme option gratuite admissible ; le déploiement depuis une organisation GitHub privée et la qualité d'auteur des commits sont à vérifier. Ne pas rendre le dépôt public, falsifier un auteur ou partager un compte pour contourner les conditions. [D04, D05]

## 8. Livraison coordonnée
1. Exécuter validations, tests, contrats et Qodana sur le SHA retenu ; conserver les rapports réellement produits.
2. Construire les artefacts backend, runner approuvé et frontend pour la configuration cible ; identifier leurs empreintes dans le manifeste. Une reconstruction ultérieure n'est pas réputée identique sans nouvelle vérification.
3. Exécuter la recette sur données fictives ; obtenir la revue de l'autre membre et `GO_PRODUCTION`. Aucun secret de production dans les jobs qui analysent une contribution non approuvée.
4. Sauvegarder. Déployer sur Systalink une release API compatible avec le frontend encore en ligne. Appliquer uniquement les migrations revues et compatibles. Recharger les processus Laravel persistants. [D07, D09]
5. Tester l'API, son contrat, le runner et les droits. Publier ensuite l'artefact React approuvé sur Vercel ; contrôler domaine, variables et liens profonds.
6. Faire une connexion réelle, une requête authentifiée, une déconnexion et un parcours synthétique fictif via les domaines finaux. Lister le résultat de chaque contrôle.

La publication Vercel en production n'est pas un effet automatique non contrôlé de chaque push sur `main`. Choisir une promotion explicite par CI autorisée, ou une branche de release protégée dont la mise à jour attend le backend. Vérifier le comportement réellement offert par le compte. Les contrôles requis ne deviennent jamais facultatifs pour finir plus vite. [D04, D09]

## 9. Reprise et sauvegardes
Conserver au moins deux releases API et les déploiements Vercel correspondants. Un manifeste relie SHA, API, frontend, runner, migrations et empreintes. Le retour à un frontend précédent est possible seulement si le backend courant garde le contrat requis. Si le backend doit aussi revenir, vérifier la compatibilité de la base avant toute action. Pas de migration inverse destructive automatique.

Sauvegarder quotidiennement `haas_app` et les artefacts privés nécessaires ; conserver sept générations, chiffrées, sur stockage distant. Les fixtures laboratoire/B2 peuvent être recréées : elles ne remplacent pas une sauvegarde métier. Les clés de récupération sont conservées hors du VPS et transmises uniquement aux personnes autorisées. Tester une restauration hors production. Cibles proposées : perte maximale 24 h, reprise moins de 2 h ; ce sont des objectifs à mesurer, pas un SLA. [D02]

Un stockage hors du VPS mais chez le même fournisseur reste exposé à certains incidents du fournisseur ou du compte : droits distincts et seconde copie hors compte à considérer selon le budget. Ne pas promettre une indépendance totale sans la démontrer.

## 10. Supervision minimale
Surveiller disponibilité API/frontend, validité TLS, disque, connexion SQL, queue métier, opérations laboratoire bloquées et présence de sauvegarde. Un `/health` public reste minimal ; les détails de base et version sont protégés. Les logs corrèlent par request_id sans mot de passe, code privé ou cookie. Les alertes parviennent aux deux développeurs et la personne de permanence est connue.

L'indisponibilité du laboratoire doit être visible mais ne doit pas empêcher de consulter une capsule ou de créer une demande. Le site n'affiche pas de promesse « 99,99 % », de haute disponibilité ou de montée en charge illimitée.

## 11. Vérifications avant ouverture
Valider les quatorze points de `docs/quality/DEPLOYMENT_GATE.md`, dont CORS refusé, cookie hors B2, un run global, tentative d'accès aux secrets sous l'identité runner, test de restauration et promotion liée au bon SHA. Le domaine, le forfait Vercel, SMTP, le panier Datacloud et le token Qodana sont des inconnues à lever, pas des valeurs que Codex peut inventer.
