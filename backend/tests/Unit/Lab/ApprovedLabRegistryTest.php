<?php

declare(strict_types=1);

namespace Tests\Unit\Lab;

use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Lab\LabDefinition;
use App\Models\Lab\LabResult;
use App\Models\Lab\LabRun;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ApprovedLabRegistryTest extends TestCase
{
    public function test_manifest_is_bound_to_existing_source_bytes_and_fixed_release(): void
    {
        $registry = new ApprovedLabRegistry;
        $definition = new LabDefinition;
        $definition->setRawAttributes($registry->manifest());

        $this->assertTrue($registry->integrity());
        $this->assertTrue($registry->matches($definition));
        $this->assertSame('b1.idempotent.v1', $registry->manifest()['runner_key']);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $registry->manifest()['code_digest']);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $registry->manifest()['suite_digest']);
    }

    #[DataProvider('changedFields')]
    public function test_database_content_cannot_select_another_release_or_runner(string $field): void
    {
        $registry = new ApprovedLabRegistry;
        $manifest = $registry->manifest();
        $manifest[$field] = 'untrusted';
        $definition = new LabDefinition;
        $definition->setRawAttributes($manifest);

        $this->assertFalse($registry->matches($definition));
    }

    /** @return iterable<string, array{string}> */
    public static function changedFields(): iterable
    {
        foreach (['runner_key', 'release', 'module_version', 'source_commit', 'code_digest', 'suite_digest'] as $field) {
            yield $field => [$field];
        }
    }

    public function test_control_models_use_only_the_application_connection_and_reject_mass_assignment(): void
    {
        foreach ([new LabDefinition, new LabRun, new LabResult] as $model) {
            $this->assertSame('pgsql', $model->getConnectionName());
            $this->assertSame(['*'], $model->getGuarded());
        }
        $this->assertFalse(config('lab.enabled'));
    }
}
