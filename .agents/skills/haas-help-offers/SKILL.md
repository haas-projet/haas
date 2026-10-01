---
name: haas-help-offers
description: "Concevoir, implémenter et vérifier le parcours Coup de main HAAS : ouverture volontaire, proposition consentie, acceptation transactionnelle et progrès attribué."
---

# Coup de main HAAS

Lire ADR-006, COUPS_DE_MAIN.md, COUPS_DE_MAIN_API.md et le lot courant BH/FH/RH. Utiliser les conventions Laravel/React et les compétences de design existantes ; ne pas relire tout le pack à chaque modification locale.

1. Distinguer propriétaire, proposant et visiteur ; aucune acceptation par admin non propriétaire.
2. Vérifier projet volontaire, comptes actifs vérifiés, disponibilité déclarée, TTL et quotas. Pas de dépendance à directory_visible pour aider.
3. Avant publication, tester les deux consentements et l’aperçu exact ; pending reste privé. Pas de texte privé automatiquement publié, de faux commentaire utilisateur ou de droit équipe.
4. Créer/rattacher le fil choisi dans une transaction, validation normale, idempotence, ordre de verrous partagé et projection unique.
5. Tester les courses accept/retrait/fermeture/suspension, expiration sans cron, compteur privé et invalidation des caches.
6. UI après GO_FRONTEND : réutiliser les composants, pas de matching IA ni messagerie générale ; vide et réseau distincts, clavier/mobile, textes faciles à comprendre.
7. Une acceptation prouve une intention commune, pas un travail achevé. Les progrès proviennent des contributions effectivement enregistrées.
8. Rapporter les commandes et observations réelles ; laisser TODO/BLOQUÉ là où l’application n’a pas été exécutée. Préserver les anciens identifiants de tâche.

Sortie : petit commit cohérent, tests pertinents, contrats et suivi mis à jour. Les modifications ne doivent pas déclencher déploiement, achat ou publication de dépôt sans accord.
