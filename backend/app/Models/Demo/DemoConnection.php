<?php

namespace App\Models\Demo;

/**
 * Point de bascule applicatif vers la base de démonstration B2.
 *
 * Le cahier des charges §17 et §24 isole B2 de la plateforme HAAS :
 * sous-domaine distinct `demo-api.example.com`, aucun cookie de session
 * partagé avec `.haas.example.com`, données exclusivement fictives et
 * base conceptuellement séparée des bases métier (`haas_app`) et de
 * laboratoire (`haas_lab`).
 *
 * Tant que la connexion dédiée `demo` n'est pas câblée dans
 * `backend/config/database.php`, `backend/phpunit.xml` et la CI
 * (fichiers réservés au responsable 1, cf.
 * `docs/execution/BACKEND_A_TROIS.md:103`), le modèle et la migration
 * utilisent la connexion par défaut via `DemoConnection::NAME = null`.
 *
 * Au moment de câbler `demo`, trois endroits doivent évoluer de concert :
 * 1. domaine B2 : changer la valeur de `DemoConnection::NAME` ;
 * 2. socle (responsable 1) : ajouter la connexion `demo` dans
 *    `backend/config/database.php`, étendre `backend/phpunit.xml` et la CI ;
 * 3. tests : aligner la connexion secondaire utilisée pour le nettoyage
 *    hors transaction PHPUnit et la configuration `RefreshDatabase`.
 */
final class DemoConnection
{
    public const NAME = null;
}
