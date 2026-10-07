<?php

use App\Models\Lab\LabConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Commandes fictives dérivées des événements B1 : une commande pour un événement
 * traité avec succès. Nom de table `test_orders` aligné sur la « Séparation des
 * données de test » décrite au cahier des charges §24. La FK vers `test_events`
 * garantit qu'une commande est toujours attachée à l'événement qui l'a produite,
 * et la contrainte unique sur `source_event_id` matérialise l'invariant « un
 * événement = au plus une commande ».
 *
 * `run_id` reste un identifiant logique sans FK : `lab_runs` (B33) vit dans la
 * base `haas_app`, distincte de la base `haas_lab` à laquelle `test_orders`
 * appartient conceptuellement (cahier des charges §15 et §33). Même protocole
 * de bascule que `test_events` : `$connection = LabConnection::NAME` ici,
 * et le câblage de `haas_lab` touchera `App\Models\Lab\LabConnection::NAME`
 * côté domaine, `backend/config/database.php`, `backend/phpunit.xml` et la CI
 * côté socle, puis la connexion de nettoyage et `RefreshDatabase` côté tests.
 *
 * Dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (une migration
 * par table, nom descriptif, sans préfixe de lot), à arbitrer par le relecteur.
 */
return new class extends Migration
{
    protected $connection = LabConnection::NAME;

    public function up(): void
    {
        Schema::connection($this->connection)->create('test_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->foreignUuid('source_event_id')
                ->references('id')->on('test_events')
                ->restrictOnDelete();
            $table->string('order_ref', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamps();
            $table->unique('source_event_id', 'test_orders_source_event_unique');
            $table->index(['run_id', 'order_ref'], 'test_orders_run_order_index');
        });

        DB::connection($this->connection)->statement(<<<'SQL'
ALTER TABLE test_orders
    ADD CONSTRAINT test_orders_amount_positive CHECK (amount_minor > 0),
    ADD CONSTRAINT test_orders_currency_format CHECK (currency ~ '^[A-Z]{3}$'),
    ADD CONSTRAINT test_orders_order_ref_trim CHECK (order_ref = btrim(order_ref) AND btrim(order_ref) <> '')
SQL);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('test_orders');
    }
};
