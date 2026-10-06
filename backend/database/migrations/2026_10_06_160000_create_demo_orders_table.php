<?php

use App\Models\Demo\DemoConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Commandes fictives de la démonstration B2 (lot B38). Chaque ligne porte
 * conjointement l'empreinte de la clé d'idempotence client et l'empreinte
 * de la charge canonique : l'unicité sur `idempotency_key_hash` matérialise
 * l'invariant « une clé = une commande » du cahier des charges §17 ; la
 * comparaison de `payload_hash` au rejeu permet de distinguer une reprise
 * (même clé + même charge) d'un conflit (même clé + charge différente).
 *
 * Les contraintes CHECK bornent les champs à des valeurs fictives valides
 * (montant strictement positif, devise ISO 4217 alpha-3 majuscule, texte
 * sans espaces de bordure) côté base, avant même toute logique applicative.
 *
 * La table appartient conceptuellement à la base de démonstration isolée
 * décrite au cahier des charges §17, §21 et §24 ; tant que la connexion
 * dédiée n'est pas câblée côté socle (voir `DemoConnection`), la migration
 * s'applique sur la connexion par défaut via `$connection = DemoConnection::NAME`.
 *
 * Dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (une migration
 * par table, nom descriptif, sans préfixe de lot), même convention que B35.
 */
return new class extends Migration
{
    protected $connection = DemoConnection::NAME;

    public function up(): void
    {
        Schema::connection($this->connection)->create('demo_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('idempotency_key_hash', 64)->unique('demo_orders_idempotency_key_unique');
            $table->char('payload_hash', 64);
            $table->string('order_ref', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('state', 9);
            $table->timestamps();
        });

        DB::connection($this->connection)->statement(<<<'SQL'
ALTER TABLE demo_orders
    ADD CONSTRAINT demo_orders_amount_positive CHECK (amount_minor > 0),
    ADD CONSTRAINT demo_orders_currency_format CHECK (currency ~ '^[A-Z]{3}$'),
    ADD CONSTRAINT demo_orders_order_ref_trim CHECK (order_ref = btrim(order_ref) AND btrim(order_ref) <> ''),
    ADD CONSTRAINT demo_orders_state_confirmed CHECK (state = 'confirmed'),
    ADD CONSTRAINT demo_orders_key_hash_hex CHECK (idempotency_key_hash ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT demo_orders_payload_hash_hex CHECK (payload_hash ~ '^[0-9a-f]{64}$')
SQL);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('demo_orders');
    }
};
