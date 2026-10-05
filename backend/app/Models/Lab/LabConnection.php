<?php

namespace App\Models\Lab;

/**
 * Point de bascule applicatif vers la base `haas_lab`.
 *
 * Le cahier des charges §15, §24 et §33 sépare `haas_app` et `haas_lab` ; les
 * tables `test_events` et `test_orders` appartiennent à `haas_lab`. Tant que la
 * connexion dédiée n'est pas câblée dans `backend/config/database.php`,
 * `backend/phpunit.xml` et la CI (fichiers réservés au responsable 1,
 * cf. `docs/execution/BACKEND_A_TROIS.md:103`), les modèles et les migrations
 * utilisent la connexion par défaut via `LabConnection::NAME = null`.
 *
 * Au moment de câbler `haas_lab`, trois endroits doivent évoluer de concert :
 * 1. domaine capsules/laboratoire : changer la valeur de `LabConnection::NAME` ;
 * 2. socle (responsable 1) : ajouter la connexion `lab` dans
 *    `backend/config/database.php`, étendre `backend/phpunit.xml` et la CI ;
 * 3. tests : aligner la connexion secondaire utilisée pour le nettoyage
 *    hors transaction PHPUnit et la configuration `RefreshDatabase`.
 */
final class LabConnection
{
    public const NAME = null;
}
