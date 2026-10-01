# Environnement observé — 1er octobre 2026

Inventaire S01 partiel pour B01, branche `backend/socle-auth`.

| Élément | Observé / retenu |
|---|---|
| PHP de recette locale | 8.5.10 dans Laragon, avec pdo_pgsql |
| PHP du PATH initial | 8.3.12 ; insuffisant pour le minimum du projet |
| Minimum / résolution Composer | PHP 8.4 / `config.platform.php=8.4.0` ; exécution native 8.4 non effectuée |
| Squelette officiel | laravel/laravel v13.10.1, import backend sélectif |
| Laravel / PHPUnit verrouillés | 13.34.0 / 12.5.37 |
| Composer | 2.8.5 ; dépréciations sous PHP 8.5, commandes exécutées avec succès |
| PostgreSQL | 17.0 ; recette sur un cluster temporaire distinct du service existant |
| Node / npm / Git | 24.19.0 / 11.2.0 via npm.cmd / 2.45.1.windows.1 |
| GitHub au démarrage | Dépôt privé, trois collaborateurs ; aucune CI applicative ni protection de branche |
| Systalink, domaine, SMTP, Vercel, Qodana | NON VÉRIFIÉS |

Le PATH global et le service PostgreSQL existant ne sont pas modifiés. Confirmer PHP 8.4 ou supérieur sur l'hébergement avant livraison. Versions/licences détaillées : `backend/composer.lock` et `docs/quality/B01_DEPENDENCIES.json`.

Sources : [installation Laravel 13](https://laravel.com/docs/13.x/installation), [versions prises en charge](https://laravel.com/docs/13.x/releases), métadonnées Composer et commandes locales réellement exécutées.
