<?php

namespace Tests\Integration\Demo;

use App\Models\Demo\DemoOrder;
use App\Services\Demo\PurgeExpiredDemoOrdersService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use InvalidArgumentException;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

/**
 * Scénarios de purge B2 (lot B38) : la commande Artisan `demo:prune`
 * supprime uniquement les commandes fictives expirées, ne touche à aucune
 * autre table, est idempotente et respecte l'option `--older-than`.
 */
final class PruneDemoOrdersTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-06T12:00:00+00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expired_orders_are_removed_and_recent_orders_are_kept(): void
    {
        $this->seedOrder(ageInHours: 25); // juste au-delà de 24 h
        $this->seedOrder(ageInHours: 48); // bien expiré
        $recent = $this->seedOrder(ageInHours: 23); // sous le seuil

        $this->runPrune()->assertSuccessful()->run();

        $this->assertDatabaseCount('demo_orders', 1);
        $this->assertNotNull(DemoOrder::query()->whereKey($recent->id)->first());
    }

    public function test_prune_does_not_touch_the_users_table(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        $this->seedOrder(ageInHours: 48);

        $this->runPrune()->assertSuccessful()->run();

        $this->assertDatabaseCount('demo_orders', 0);
        $this->assertFalse(Schema::hasTable('users'));
    }

    public function test_second_invocation_removes_zero_rows(): void
    {
        $this->seedOrder(ageInHours: 48);
        $this->seedOrder(ageInHours: 23);

        $this->runPrune()->expectsOutput('1 commande(s) fictive(s) retirée(s).')->assertSuccessful()->run();
        $this->runPrune()->expectsOutput('0 commande(s) fictive(s) retirée(s).')->assertSuccessful()->run();

        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_older_than_option_overrides_default_retention(): void
    {
        // Rétention 1 h → cutoff = now - 3600 s.
        $this->seedOrder(ageInMinutes: 10); // 10 min < 1 h → conservé
        $this->seedOrder(ageInMinutes: 90); // 1 h 30 > 1 h → supprimé

        $this->runPrune(['--older-than' => 3_600])
            ->expectsOutput('0 commande(s) fictive(s) retirée(s).')
            ->assertSuccessful()
            ->run();

        $this->assertDatabaseCount('demo_orders', 2);
    }

    public function test_invalid_older_than_option_is_refused_without_delete(): void
    {
        $this->seedOrder(ageInHours: 48);

        // Options invalides : la commande doit renvoyer INVALID et ne rien supprimer.
        $this->runPrune(['--older-than' => 'abc'])->assertFailed()->run();
        $this->runPrune(['--older-than' => '-10'])->assertFailed()->run();

        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_cutoff_rejects_rentention_outside_bounds(): void
    {
        $service = $this->app->make(PurgeExpiredDemoOrdersService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->cutoffFor(CarbonImmutable::now('UTC'), PurgeExpiredDemoOrdersService::MAXIMUM_RETENTION_SECONDS + 1);
    }

    /** @param  array<string, int|string>  $parameters */
    private function runPrune(array $parameters = []): PendingCommand
    {
        $command = $this->artisan('demo:prune', $parameters);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    private function seedOrder(?int $ageInHours = null, ?int $ageInMinutes = null): DemoOrder
    {
        $createdAt = CarbonImmutable::now('UTC');
        if ($ageInHours !== null) {
            $createdAt = $createdAt->subHours($ageInHours);
        }
        if ($ageInMinutes !== null) {
            $createdAt = $createdAt->subMinutes($ageInMinutes);
        }

        return DemoOrder::unguarded(fn (): DemoOrder => DemoOrder::factory()->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'expires_at' => $createdAt->addDay(),
        ]));
    }
}
