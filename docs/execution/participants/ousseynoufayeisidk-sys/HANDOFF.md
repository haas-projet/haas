# Reprise — socle/auth

Branche : `backend/socle-auth`. B01 testé avec Laravel 13.34.0, PHP 8.5.10 et PostgreSQL 17.0 ; résolution Composer sur PHP 8.4.0. Lire `docs/COMMANDS.md` et `docs/quality/B01_BOOTSTRAP.md` avant toute relance.

PostgreSQL temporaire arrêté. Les prochains tests SQL exigent une nouvelle base locale/CI dédiée `haas_*_test`, rôle `haas_test`, et une connexion explicite. Ne pas réutiliser automatiquement le port de recette. Aucun secret applicatif enregistré.

B01 est intégré dans main par la PR #4, commit `462af72b992ed9bc5c77440ac04ec41c87bf2efd`, sur autorisation explicite de fusion de l'utilisateur. Aucune revue GitHub d'un collègue attestée. Retrouver le commit de suivi et les branches synchronisées dans le bilan de session. Prochain lot : B02, puis B03 ; inscription et connexion non implémentées. Les deux collègues peuvent préparer et coder leurs parties indépendantes selon `docs/execution/BACKEND_A_TROIS.md`.
