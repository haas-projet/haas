# ADR-004 — Backend Systalink, frontend Vercel
**Statut :** décision utilisateur adoptée pour la conception du déploiement, 1er octobre 2026. Mise en œuvre non effectuée.

## Base de la décision
L'utilisateur a retenu : « j'ai Ultimate faisons simple la backend systalink front vercel », puis demandé de finaliser les documents. Les fonctions F01–F15, la séquence backend-first et les petits commits restent en vigueur.

## Changements explicites
- Un seul VPS Systalink/Datacloud pour Laravel, PostgreSQL et les traitements. React/Vite est publié sur Vercel.
- La recommandation de deux VPS APP/LAB et la préproduction permanente de la cible antérieure sont remplacées ; l'ancien texte n'est pas inclus dans ce dossier et n'est plus actif.
- Le laboratoire devient un service local restreint : un seul run effectivement actif au total, deux enfants successifs par comparaison. Les quotas de cinq unités par heure et membre ne changent pas.
- La SPA et l'API ont deux origines HTTPS distinctes mais partagent un domaine parent de confiance pour Sanctum. CORS explicite, cookies et CSRF sont à tester ; l'hypothèse de même origine des anciennes versions est remplacée.
- B2 reste sur des hôtes extérieurs au périmètre des cookies HAAS, avec uniquement des données fictives.
- Ultimate est déclaré déjà disponible par l'utilisateur. Aucun nouvel achat Qodana n'est budgété ; le projet, le token, les contributeurs couverts et les fonctions accessibles sont à vérifier, sans confondre Ultimate avec Ultimate Plus.
- Un stockage distant chiffré reste prévu pour les sauvegardes. Vercel Hobby n'est ni imposé ni présumé admissible.

## Conséquences assumées
Le VPS est un point unique de panne. La séparation des comptes système et rôles SQL ne vaut pas isolation entre machines : même noyau, même hôte PostgreSQL et ressources physiques partagées. Aucune haute disponibilité annoncée. Les tests de refus d'accès, d'interruption et de charge conditionnent l'ouverture du laboratoire.

## Réception
Les 94 identifiants de lots et 52 AC sont conservés. Douze sous-lots DEP et quatorze vérifications DEP-AC sont adaptés à la cible actuelle ; ils ne constituent pas 12 nouveaux modules. Tout suivi rempli dans le dépôt utilisateur est préservé et réévalué, jamais effacé. GO_FRONTEND et GO_PRODUCTION restent des décisions humaines.
