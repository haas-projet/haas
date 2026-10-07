<?php

use App\Models\Lab\LabConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Journal d'entrée de la brique B1 : événements de test fictifs reçus par un run
 * de laboratoire. Nom de table `test_events` aligné sur la « Séparation des données
 * de test » décrite au cahier des charges §24. La table appartient conceptuellement
 * à la base `haas_lab` (cahier des charges §15 et §33) ; tant que
 * `backend/config/database.php`, `backend/phpunit.xml` et la CI ne sont pas étendus
 * avec la connexion dédiée (fichiers réservés au responsable 1,
 * cf. `docs/execution/BACKEND_A_TROIS.md:103`), la migration s'applique sur la
 * connexion par défaut via `$connection = LabConnection::NAME`. Le câblage de
 * `haas_lab` touchera `App\Models\Lab\LabConnection::NAME` côté domaine,
 * `backend/config/database.php`, `backend/phpunit.xml` et la CI côté socle,
 * puis la connexion de nettoyage et la configuration `RefreshDatabase` côté tests.
 *
 * Dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (une migration
 * par table, nom descriptif, sans préfixe de lot), à arbitrer par le relecteur.
 *
 * `run_id` est un identifiant logique du run de laboratoire. Il n'y a pas de
 * clé étrangère : `lab_runs` (B33) vit dans la base `haas_app`, distincte de
 * `haas_lab`, donc aucune FK ne peut être posée en travers.
 */
return new class extends Migration
{
    protected $connection = LabConnection::NAME;

    public function up(): void
    {
        Schema::connection($this->connection)->create('test_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('event_id', 64);
            $table->string('order_ref', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'event_id'], 'test_events_run_event_unique');
        });

        DB::connection($this->connection)->statement(<<<'SQL'
ALTER TABLE test_events
    ADD CONSTRAINT test_events_amount_positive CHECK (amount_minor > 0),
    ADD CONSTRAINT test_events_currency_format CHECK (currency ~ '^[A-Z]{3}$'),
    ADD CONSTRAINT test_events_event_id_trim CHECK (event_id = btrim(event_id) AND btrim(event_id) <> ''),
    ADD CONSTRAINT test_events_order_ref_trim CHECK (order_ref = btrim(order_ref) AND btrim(order_ref) <> '')
SQL);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('test_events');
    }
};
