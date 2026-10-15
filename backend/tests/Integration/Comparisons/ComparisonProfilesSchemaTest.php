<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;

/**
 * Les CHECK sont vérifiés par insertion directe en base, pas par un chemin
 * applicatif : aucune route n'écrit comparison_profiles.
 */
final class ComparisonProfilesSchemaTest extends PostgresTestCase
{
    use RefreshDatabase, RegistryFixtures;

    public function test_family_key_other_than_b1_idempotent_v1_is_rejected(): void
    {
        $version = $this->publishedVersion();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_profiles_family_fixed');
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id, [
            'family_key' => 'b2.other.v1',
        ]));
    }

    public function test_suite_digest_must_be_lowercase_hex_64(): void
    {
        $version = $this->publishedVersion();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_profiles_suite_digest_shape');
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id, [
            'suite_digest' => 'ZZ'.str_repeat('a', 62),
        ]));
    }

    public function test_input_schema_version_must_match_semver(): void
    {
        $version = $this->publishedVersion();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_profiles_input_schema_shape');
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id, [
            'input_schema_version' => 'v1',
        ]));
    }

    public function test_baseline_manifest_must_be_a_non_empty_object(): void
    {
        $version = $this->publishedVersion();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_profiles_baseline_manifest_is_object');
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id, [
            'baseline_manifest' => json_encode([], JSON_THROW_ON_ERROR),
        ]));
    }

    public function test_candidate_version_id_is_unique(): void
    {
        $version = $this->publishedVersion();
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id));
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id));
    }

    public function test_dropping_a_referenced_capsule_version_is_restricted(): void
    {
        $version = $this->publishedVersion();
        DB::connection('pgsql')->table('comparison_profiles')->insert($this->row($version->id));
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('capsule_versions')->where('id', $version->id)->delete();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(string $versionId, array $overrides = []): array
    {
        return array_replace([
            'id' => (string) Str::uuid(),
            'family_key' => 'b1.idempotent.v1',
            'baseline_manifest' => json_encode(['label' => 'test_only'], JSON_THROW_ON_ERROR),
            'candidate_version_id' => $versionId,
            'suite_digest' => str_repeat('a', 64),
            'input_schema_version' => '1.0.0',
            'enabled' => true,
            'created_at' => now()->utc(),
        ], $overrides);
    }
}
