<?php

declare(strict_types=1);

namespace Tests\Support\Comparisons;

use App\Infrastructure\Comparisons\ComparisonProfileRegistry;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Fixtures BV204 : jamais une preuve d'exécution ; destinée aux tests. */
trait ComparisonFixtures
{
    /** @param array<string, mixed> $overrides */
    private function enabledComparisonProfile(CapsuleVersion $candidate, array $overrides = []): string
    {
        $registry = app(ComparisonProfileRegistry::class);
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('comparison_profiles')->insert(array_replace([
            'id' => $id,
            'family_key' => $registry->familyKey(),
            'baseline_manifest' => json_encode([
                'label' => 'Exemple pédagogique incorrect',
                'test_only_fixture' => true,
            ], JSON_THROW_ON_ERROR),
            'candidate_version_id' => $candidate->id,
            'suite_digest' => $registry->expectedCandidateSuiteDigest(),
            'input_schema_version' => $registry->inputSchemaVersion(),
            'enabled' => true,
            'created_at' => now()->utc(),
        ], $overrides));

        return $id;
    }

    /**
     * Construit un manifeste de release conforme au gabarit BV204, prêt à être
     * passé à lab:bind-comparison-profile. Jamais exécuté en production : la
     * baseline est explicitement marquée test_only_fixture, et le service
     * refuse ces manifestes hors d'un environnement de test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function comparisonManifestForVersion(CapsuleVersion $version, array $overrides = []): array
    {
        $lab = (new ApprovedLabRegistry)->manifest();
        $manifest = [
            'comparison_profiles' => [
                'family_key' => $lab['runner_key'],
                'candidate' => [
                    'version_id' => $version->id,
                    'release' => $lab['release'],
                    'source_commit' => $lab['source_commit'],
                    'code_digest' => $lab['code_digest'],
                    'suite_digest' => $lab['suite_digest'],
                    'content_digest' => (string) $version->content_digest,
                ],
                'baseline' => [
                    'label' => 'Exemple pédagogique incorrect',
                    'source_commit' => null,
                    'test_only_fixture' => true,
                ],
                'allowed_scenarios' => ['nominal_event', 'duplicate_event', 'distinct_events', 'invalid_event'],
                'enabled' => true,
            ],
        ];
        foreach ($overrides as $path => $value) {
            $this->setDeep($manifest, $path, $value);
        }

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bv204_');
        if ($path === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire pour le manifeste.');
        }
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function setDeep(array &$array, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor = &$array;
        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;

                return;
            }
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }
}
