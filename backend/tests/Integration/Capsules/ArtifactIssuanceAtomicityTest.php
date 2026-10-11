<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Http\Controllers\Capsules\IssueArtifactDownloadController;
use App\Services\Capsules\IssueArtifactDownloadService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\PostgresTestCase;
use Tests\Support\Capsules\ArtifactKitFixture;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\TestDatabaseGuard;

final class ArtifactIssuanceAtomicityTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_invalid_configured_origin_cannot_leave_an_issued_audit_without_links(): void
    {
        Storage::fake('local');
        [$actor, $capsule, $version, $artifact] = ArtifactKitFixture::create('audit-sans-liens');
        config(['app.url' => 'https://api.haas.example.com/private?fixture=1']);
        $request = Request::create('/api/v1/capsules/'.$capsule->id.'/versions/'.$version->id.'/artifact/'.$artifact->id, 'GET');
        $request->setUserResolver(fn () => $actor);
        // Exercise the real HTTP adapter below the global config middleware,
        // which normally denies a broken SPA origin before this point.
        try {
            app(IssueArtifactDownloadController::class)($request, app(IssueArtifactDownloadService::class),
                $capsule->id, $version->id, $artifact->id);
            $this->fail('Une origine invalide devait etre refusee.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('content_revisions')->where('resource_id', $version->id)->count(),
            'Aucune emission ne doit etre auditee lorsque la generation des liens echoue.');
    }
}
