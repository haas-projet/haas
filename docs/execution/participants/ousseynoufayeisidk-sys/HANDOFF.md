# Reprise — socle/auth

Branche : `backend/socle-auth`. B01 testé avec Laravel 13.34.0, PHP 8.5.10 et PostgreSQL 17.0 ; résolution Composer sur PHP 8.4.0. Lire `docs/COMMANDS.md` et `docs/quality/B01_BOOTSTRAP.md` avant toute relance.

PostgreSQL temporaire arrêté. Les prochains tests SQL exigent une nouvelle base locale/CI dédiée `haas_*_test`, rôle `haas_test`, et une connexion explicite. Ne pas réutiliser automatiquement le port de recette. Aucun secret applicatif enregistré.

B01 est intégré dans main par la PR #4, commit `462af72b992ed9bc5c77440ac04ec41c87bf2efd`, sur autorisation explicite de fusion de l'utilisateur. B02 est maintenant IN_REVIEW sur cette branche : Pint 1.32.1, Larastan 3.12.2, PHPStan 2.2.16 niveau 8, contrôle AST. Aucun avis de collègue ni CI inventé. Lire `docs/quality/B02_QUALITY.md` : 23 tests / 33 assertions distincts réussis, SQL compris, témoin retiré et serveur temporaire arrêté.

Reprendre B03 après revue/intégration de B02 et synchronisation avec main. Composer 2.10.3 a été vérifié en copie temporaire ; installation globale inchangée. PHP minimal 8.4 reste à exécuter nativement dans la CI. Retrouver le SHA et la PR dans le bilan de session. Inscription et connexion non implémentées ; les deux collègues poursuivent les parties indépendantes selon `docs/execution/BACKEND_A_TROIS.md`.
