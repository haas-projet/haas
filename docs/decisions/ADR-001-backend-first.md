# ADR-001 — Terminer le backend avant de développer le frontend

**Date :** 30 septembre 2026. **Statut :** décision demandée par l’utilisateur.

## Contexte
Le cahier V1 et la section 18 du document d’architecture recommandaient des tranches verticales backend/frontend. La demande la plus récente exige un backend complet, puis le frontend, avec de petits commits explicites.

## Décision
L’ordre de développement devient : cadrage technique → backend P0 complet et testé → revue du BACKEND_GATE → frontend P0 → intégration et réception finale. Ce changement de séquence est explicite ; il ne réécrit pas silencieusement les documents sources.

Pendant la phase backend, il est permis de préparer les parcours, tokens, spécifications d’écrans, contrats et cas de recette dans docs/. Il est interdit de générer la SPA ou des écrans React dans frontend/src, ainsi que le composant React B2. Seul le contrat et l’API du démonstrateur B2 sont réalisés côté backend.

L’ouverture de la phase frontend exige la preuve des contrôles backend et une autorisation de passage par un membre de l’équipe. L’agent n’invente ni signature humaine, ni CI verte, ni disponibilité Qodana. Les dépendances externes indisponibles sont documentées et ne sont jamais marquées comme testées.

## Conséquences
Deux développeurs peuvent se répartir services, contrats, tests API, fixtures et exploitation pendant le backend. Ils ne modifient pas simultanément les mêmes fichiers de migration. Les validations visuelles interviennent plus tard : les contrats et états d’erreur sont donc écrits avant les écrans pour réduire les reprises.

Les jalons calendaires et les 216 heures-personnes du cahier restent des hypothèses historiques. Ce nouvel ordre doit être réestimé ; aucun nombre de commits ne prouve la faisabilité du délai. Aucune fonctionnalité P0 ne disparaît sans décision explicite et mise à jour de la présentation.

## Autorité documentaire
Le règlement fourni définit les conditions du concours ; il n’est pas remplacé par ce pack. La dernière demande utilisateur fixe l’ordre. Le cahier source fixe le périmètre produit. L’architecture source fixe les frontières de code. HAAS_CODEX_MASTER.md et les instructions locales précisent l’exécution. Une contradiction sur un droit, une règle métier ou le périmètre est signalée, pas devinée.
