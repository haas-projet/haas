# Reprise — socle/auth

Branche : `backend/socle-auth`. B01 testé avec Laravel 13.34.0, PHP 8.5.10 et PostgreSQL 17.0 ; résolution Composer sur PHP 8.4.0. Lire `docs/COMMANDS.md` et `docs/quality/B01_BOOTSTRAP.md` avant toute relance.

PostgreSQL temporaire arrêté. Les prochains tests SQL exigent une nouvelle base locale/CI dédiée `haas_*_test`, rôle `haas_test`, et une connexion explicite. Ne pas réutiliser automatiquement le port de recette. Aucun secret applicatif enregistré.

B01 attend une revue humaine. Aucun merge applicatif dans main ou les branches des collègues. Retrouver le SHA réel et la PR dans le bilan de session. Prochain lot : B02 après revue, puis B03 ; inscription et connexion non implémentées.
