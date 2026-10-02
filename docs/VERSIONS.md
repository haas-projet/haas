# Environnement observé — 1er/2 octobre 2026

Inventaire S01 partiel, complété par B02 puis la CI B03 sur `backend/socle-auth-ci`.

| Élément | Observé / retenu |
|---|---|
| PHP de recette locale | 8.5.10 dans Laragon, avec pdo_pgsql |
| PHP du PATH initial | 8.3.12 ; insuffisant pour le minimum du projet |
| Minimum / résolution Composer | PHP 8.4 / `config.platform.php=8.4.0` ; CI réellement réussie sur PHP 8.4.26 et 8.5.11 |
| Squelette officiel | laravel/laravel v13.10.1, import backend sélectif |
| Laravel / PHPUnit verrouillés | 13.34.0 / 12.5.37 |
| Composer de recette B02 | 2.10.3, copie temporaire vérifiée ; aucun avertissement de dépréciation observé. Installation globale 2.8.5 inchangée |
| Qualité PHP B02 | Pint 1.32.1, Larastan 3.12.2, PHPStan 2.2.16, PHP-Parser 5.9.0 |
| PostgreSQL | 17.0 ; recette sur un cluster temporaire distinct du service existant |
| Node / npm / Git | 24.19.0 / 11.2.0 via npm.cmd / 2.45.1.windows.1 |
| GitHub au démarrage | Dépôt privé, trois collaborateurs ; aucune CI applicative ni protection de branche |
| GitHub après B03 | Workflow Backend CI réussi dans le run 36944003232 ; Actions épinglées, PostgreSQL 17 isolé ; protections inchangées |
| Systalink, domaine, SMTP, Vercel, Qodana | NON VÉRIFIÉS |

Le PATH global et le service PostgreSQL existant ne sont pas modifiés. Confirmer PHP 8.4 ou supérieur sur l'hébergement avant livraison. Versions/licences détaillées : `backend/composer.lock` et `docs/quality/B01_DEPENDENCIES.json`.

B02 ajoute quatre paquets de développement recensés dans `docs/quality/B02_DEPENDENCIES.json`, sans mise à jour du framework ou des dépendances B01. Composer 2.10.3 téléchargé depuis le site officiel ; SHA-256 comparé à la valeur publiée : `7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6`. L'exécutable temporaire n'est pas inclus dans le dépôt.

Sources : [installation Laravel 13](https://laravel.com/docs/13.x/installation), [versions prises en charge](https://laravel.com/docs/13.x/releases), métadonnées Composer et commandes locales réellement exécutées.
