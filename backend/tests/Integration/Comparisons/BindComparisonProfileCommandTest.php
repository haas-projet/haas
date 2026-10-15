<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonFixtures;
use Tests\Support\Lab\RegistryFixtures;

final class BindComparisonProfileCommandTest extends PostgresTestCase
{
    use ComparisonFixtures, RefreshDatabase, RegistryFixtures;

    public function test_valid_test_fixture_manifest_inserts_a_single_profile_in_testing_env(): void
    {
        $version = $this->publishedVersion();
        $path = $this->writeManifest($this->comparisonManifestForVersion($version));
        try {
            $this->assertSame(0, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertSame(1, DB::table('comparison_profiles')->where('candidate_version_id', $version->id)->count());
            $profile = ComparisonProfile::query()->sole();
            $this->assertSame('b1.idempotent.v1', $profile->family_key);
            $this->assertTrue($profile->enabled);
            $this->assertSame('1.0.0', $profile->input_schema_version);
        } finally {
            @unlink($path);
        }
    }

    public function test_rerun_with_identical_manifest_is_idempotent(): void
    {
        $version = $this->publishedVersion();
        $path = $this->writeManifest($this->comparisonManifestForVersion($version));
        try {
            $first = Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]);
            $second = Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]);
            $this->assertSame(0, $first);
            $this->assertSame(0, $second);
            $this->assertSame(1, DB::table('comparison_profiles')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_divergent_existing_profile_is_refused(): void
    {
        $version = $this->publishedVersion();
        $this->enabledComparisonProfile($version, ['input_schema_version' => '2.0.0']);
        $path = $this->writeManifest($this->comparisonManifestForVersion($version));
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('divergent', Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    public function test_candidate_version_not_published_is_refused(): void
    {
        $version = $this->publishedVersion();
        CapsuleVersion::query()->whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $path = $this->writeManifest($this->comparisonManifestForVersion($version));
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('non publi', Artisan::output());
            $this->assertSame(0, DB::table('comparison_profiles')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_hidden_capsule_candidate_is_refused(): void
    {
        $version = $this->publishedVersion();
        Capsule::query()->whereKey($version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden->value]);
        $path = $this->writeManifest($this->comparisonManifestForVersion($version));
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertSame(0, DB::table('comparison_profiles')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_divergent_code_digest_is_refused(): void
    {
        $version = $this->publishedVersion();
        $manifest = $this->comparisonManifestForVersion($version, ['comparison_profiles.candidate.code_digest' => str_repeat('b', 64)]);
        $path = $this->writeManifest($manifest);
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('registre B33', Artisan::output());
            $this->assertSame(0, DB::table('comparison_profiles')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_scenario_out_of_lab_scenarios_is_refused(): void
    {
        $version = $this->publishedVersion();
        $manifest = $this->comparisonManifestForVersion($version, [
            'comparison_profiles.allowed_scenarios' => ['payload_injection'],
        ]);
        $path = $this->writeManifest($manifest);
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('hors gabarit', Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    public function test_family_other_than_b1_idempotent_v1_is_refused(): void
    {
        $version = $this->publishedVersion();
        $manifest = $this->comparisonManifestForVersion($version, ['comparison_profiles.family_key' => 'b2.other.v1']);
        $path = $this->writeManifest($manifest);
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('hors gabarit', Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    public function test_baseline_without_test_fixture_is_refused_when_registry_has_no_real_baseline(): void
    {
        $version = $this->publishedVersion();
        $manifest = $this->comparisonManifestForVersion($version, [
            'comparison_profiles.baseline.test_only_fixture' => false,
            'comparison_profiles.baseline.source_commit' => str_repeat('c', 40),
        ]);
        $path = $this->writeManifest($manifest);
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('Baseline', Artisan::output());
            $this->assertSame(0, DB::table('comparison_profiles')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_divergent_content_digest_is_refused(): void
    {
        $version = $this->publishedVersion();
        $manifest = $this->comparisonManifestForVersion($version, [
            'comparison_profiles.candidate.content_digest' => str_repeat('d', 64),
        ]);
        $path = $this->writeManifest($manifest);
        try {
            $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => $path]));
            $this->assertStringContainsString('divergente', Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_manifest_path_is_refused(): void
    {
        $this->assertSame(1, Artisan::call('lab:bind-comparison-profile', ['manifest' => '/path/does/not/exist.json']));
        $this->assertStringContainsString('illisible', Artisan::output());
    }
}
