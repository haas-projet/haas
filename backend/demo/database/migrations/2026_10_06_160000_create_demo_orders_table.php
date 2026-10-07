<?php

use App\Models\Demo\DemoConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Schéma B2 exclusivement fictif, sur la connexion demo : commande, TTL et cache/quota partagés. */
return new class extends Migration
{
    protected $connection = DemoConnection::NAME;

    public function up(): void
    {
        Schema::connection($this->connection)->create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration')->index();
        });
        Schema::connection($this->connection)->create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
        Schema::connection($this->connection)->create('demo_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('idempotency_key_hash', 64)->unique('demo_orders_idempotency_key_unique');
            $table->char('payload_hash', 64);
            $table->string('order_ref', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('state', 9);
            $table->timestamps();
            $table->timestamp('expires_at')->index();
        });

        DB::connection($this->connection)->statement(<<<'SQL'
ALTER TABLE demo_orders
    ADD CONSTRAINT demo_orders_amount_positive CHECK (amount_minor BETWEEN 1 AND 1000000),
    ADD CONSTRAINT demo_orders_currency_format CHECK (currency = 'EUR'),
    ADD CONSTRAINT demo_orders_order_ref_trim CHECK (order_ref ~ '^demo-[0-9]{4}$'),
    ADD CONSTRAINT demo_orders_state_confirmed CHECK (state = 'confirmed'),
    ADD CONSTRAINT demo_orders_key_hash_hex CHECK (idempotency_key_hash ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT demo_orders_payload_hash_hex CHECK (payload_hash ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT demo_orders_expiry CHECK (expires_at = created_at + interval '24 hours')
SQL);
        DB::connection($this->connection)->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION demo_bound_cache() RETURNS trigger LANGUAGE plpgsql VOLATILE AS $$
DECLARE total bigint; maximum integer;
BEGIN
    IF current_setting('transaction_isolation') <> 'read committed' THEN
        RAISE EXCEPTION USING ERRCODE = '25000', MESSAGE = 'B2_CACHE_ISOLATION';
    END IF;
    -- Verrou commun aux deux tables ; les requêtes suivantes voient les commits précédents.
    PERFORM pg_advisory_xact_lock(238039);
    maximum := CASE TG_TABLE_NAME WHEN 'cache' THEN 1000 ELSE 100 END;
    EXECUTE format('DELETE FROM %I WHERE expiration <= floor(extract(epoch FROM clock_timestamp()))::bigint', TG_TABLE_NAME);
    EXECUTE format('SELECT count(*) FROM %I WHERE key <> $1', TG_TABLE_NAME) INTO total USING NEW.key;
    IF total >= maximum THEN
        RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'B2_CACHE_CAPACITY';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER demo_cache_capacity BEFORE INSERT ON cache FOR EACH ROW EXECUTE FUNCTION demo_bound_cache();
CREATE TRIGGER demo_cache_locks_capacity BEFORE INSERT ON cache_locks FOR EACH ROW EXECUTE FUNCTION demo_bound_cache();
SQL);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('demo_orders');
        Schema::connection($this->connection)->dropIfExists('cache_locks');
        Schema::connection($this->connection)->dropIfExists('cache');
        DB::connection($this->connection)->statement('DROP FUNCTION IF EXISTS demo_bound_cache()');
    }
};
