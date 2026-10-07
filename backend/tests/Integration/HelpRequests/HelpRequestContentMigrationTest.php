<?php

namespace Tests\Integration\HelpRequests;

use App\Enums\HelpRequests\HelpIntent;
use App\Models\HelpRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class HelpRequestContentMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_b11_requests_and_defaults_the_single_shared_intent(): void
    {
        $request = HelpRequest::factory()->create();
        $title = $request->title;
        $migration = $this->migration();
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasColumn('help_requests', 'help_intent'));
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertSame($title, $request->refresh()->title);
        $this->assertSame(HelpIntent::Unblock, $request->help_intent);
        $this->assertSame('fr', $request->primary_language);
        $this->assertNull($request->code);
    }

    public function test_rollback_refuses_to_destroy_an_incomplete_draft(): void
    {
        $request = HelpRequest::factory()->create(['expected' => null, 'attempts' => null]);
        $migration = $this->migration();
        try {
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
            $this->fail('Retour incompatible attendu.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Retour B14 refusé', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('help_requests', 'help_intent'));
        $this->assertDatabaseHas('help_requests', ['id' => $request->id, 'expected' => null, 'attempts' => null]);
    }

    public function test_database_rejects_an_unknown_intent(): void
    {
        $request = HelpRequest::factory()->create();
        try {
            DB::transaction(fn () => DB::table('help_requests')->where('id', $request->id)->update(['help_intent' => 'unknown']));
            $this->fail('Contrainte SQL attendue.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertSame(HelpIntent::Unblock, $request->refresh()->help_intent);
    }

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_10_04_000014_b14_extend_help_request_content.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }
}
