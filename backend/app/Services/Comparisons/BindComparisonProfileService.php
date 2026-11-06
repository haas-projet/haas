<?php

declare(strict_types=1);

namespace App\Services\Comparisons;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Lab\LabScenario;
use App\Exceptions\Comparisons\ComparisonProfileBindingRejected;
use App\Infrastructure\Comparisons\ComparisonProfileRegistry;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Commande interne BV204 : lie un manifeste de release à un profil de
 * comparaison B1 approuvé. Jamais invoquée par une route HTTP ; aucune
 * mutation publique ne crée, modifie ou active un profil (CAHIER §24
 * ligne 1643 : « non publiés comme capsule recommandée », §43 ligne 1686).
 *
 * Refuse : candidate non publiée, retirée, non visible ; digests divergents ;
 * scénario hors LabScenario ; famille différente de b1.idempotent.v1 ; paire
 * baseline/candidate incompatible ; baseline inexistante tant qu'aucune
 * référence pédagogique n'est joint à la release. Tant que
 * ComparisonProfileRegistry::BASELINE_SOURCE_COMMIT est null, seule une
 * fixture explicitement test_only_fixture est acceptée, et uniquement sous
 * app.env === 'testing' : HAAS n'invente pas de baseline en production.
 */
final class BindComparisonProfileService
{
    public function __construct(private readonly ComparisonProfileRegistry $registry) {}

    public function bindFromManifest(string $manifestPath): ComparisonProfile
    {
        if (! is_file($manifestPath) || ! is_readable($manifestPath) || is_link($manifestPath)) {
            throw new ComparisonProfileBindingRejected('Manifeste illisible.');
        }
        $contents = @file_get_contents($manifestPath);
        if (! is_string($contents) || $contents === '') {
            throw new ComparisonProfileBindingRejected('Manifeste vide.');
        }
        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ComparisonProfileBindingRejected('Manifeste JSON invalide.');
        }
        $data = $this->validate($decoded);
        $this->assertBaselineAcceptable($data['comparison_profiles']['baseline']);
        if (! $this->registry->labIntegrity()) {
            throw new ComparisonProfileBindingRejected('Intégrité du registre B33 compromise ; profil refusé.');
        }

        return DB::connection('pgsql')->transaction(function () use ($data): ComparisonProfile {
            $candidate = $data['comparison_profiles']['candidate'];
            $version = CapsuleVersion::on('pgsql')->whereKey($candidate['version_id'])->lockForUpdate()->first();
            if ($version === null || $version->state !== CapsuleVersionState::Published) {
                throw new ComparisonProfileBindingRejected('Version candidate inconnue ou non publiée : '.$candidate['version_id']);
            }
            if ($version->content_digest === null || $version->content_digest !== $candidate['content_digest']) {
                throw new ComparisonProfileBindingRejected('Empreinte du candidat divergente pour la version : '.$version->id);
            }
            if ($candidate['code_digest'] !== $this->registry->expectedCandidateCodeDigest()
                || $candidate['suite_digest'] !== $this->registry->expectedCandidateSuiteDigest()
                || $candidate['release'] !== $this->registry->expectedCandidateRelease()
                || $candidate['source_commit'] !== $this->registry->expectedCandidateSourceCommit()) {
                throw new ComparisonProfileBindingRejected('Candidat hors du registre B33 approuvé.');
            }
            $capsule = Capsule::on('pgsql')->whereKey($version->capsule_id)->lockForUpdate()->first();
            if ($capsule === null || $capsule->visibility !== CapsuleVisibility::Visible) {
                throw new ComparisonProfileBindingRejected('Capsule candidate retirée ou non visible : '.$version->id);
            }
            $existing = ComparisonProfile::query()->where('candidate_version_id', $version->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->family_key !== $this->registry->familyKey()
                    || $existing->suite_digest !== $candidate['suite_digest']
                    || $existing->input_schema_version !== $this->registry->inputSchemaVersion()) {
                    throw new ComparisonProfileBindingRejected('Profil existant divergent pour la version : '.$version->id);
                }

                return $existing;
            }
            $profile = (new ComparisonProfile)->forceFill([
                'id' => (string) Str::uuid(),
                'family_key' => $this->registry->familyKey(),
                'baseline_manifest' => $data['comparison_profiles']['baseline'],
                'candidate_version_id' => $version->id,
                'suite_digest' => $candidate['suite_digest'],
                'input_schema_version' => $this->registry->inputSchemaVersion(),
                'enabled' => (bool) $data['comparison_profiles']['enabled'],
                'created_at' => now()->utc(),
            ]);
            $profile->save();

            return $profile;
        });
    }

    /**
     * @return array{comparison_profiles: array{candidate: array{version_id:string,release:string,source_commit:string,code_digest:string,suite_digest:string,content_digest:string}, baseline: array<string, mixed>, enabled: bool}}
     */
    private function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new ComparisonProfileBindingRejected('Manifeste non objet.');
        }
        $allowedScenarios = array_map(static fn (LabScenario $s): string => $s->value, LabScenario::cases());
        try {
            Validator::make($decoded, [
                'comparison_profiles' => ['required', 'array:family_key,candidate,baseline,allowed_scenarios,enabled'],
                'comparison_profiles.family_key' => ['required', 'string', Rule::in([$this->registry->familyKey()])],
                'comparison_profiles.candidate' => ['required', 'array:version_id,release,source_commit,code_digest,suite_digest,content_digest'],
                'comparison_profiles.candidate.version_id' => ['required', 'string', 'uuid'],
                'comparison_profiles.candidate.release' => ['required', 'string'],
                'comparison_profiles.candidate.source_commit' => ['required', 'string', 'regex:/\A[0-9a-f]{40}\z/'],
                'comparison_profiles.candidate.code_digest' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
                'comparison_profiles.candidate.suite_digest' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
                'comparison_profiles.candidate.content_digest' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
                'comparison_profiles.baseline' => ['required', 'array'],
                'comparison_profiles.baseline.source_commit' => ['required_without:comparison_profiles.baseline.test_only_fixture', 'nullable', 'string', 'regex:/\A[0-9a-f]{40}\z/'],
                'comparison_profiles.baseline.label' => ['required', 'string'],
                'comparison_profiles.baseline.test_only_fixture' => ['sometimes', 'boolean'],
                'comparison_profiles.allowed_scenarios' => ['required', 'array', 'list', 'min:1'],
                'comparison_profiles.allowed_scenarios.*' => ['required', 'string', Rule::in($allowedScenarios)],
                'comparison_profiles.enabled' => ['required', 'boolean'],
            ])->validate();
        } catch (ValidationException $e) {
            throw new ComparisonProfileBindingRejected('Manifeste hors gabarit : '.$e->getMessage());
        }

        /** @var array{comparison_profiles: array{family_key:string, candidate: array{version_id:string,release:string,source_commit:string,code_digest:string,suite_digest:string,content_digest:string}, baseline: array<string, mixed>, allowed_scenarios: list<string>, enabled: bool}} $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $baseline
     */
    private function assertBaselineAcceptable(array $baseline): void
    {
        $expected = $this->registry->expectedBaselineSourceCommit();
        if ($expected !== null) {
            if (($baseline['source_commit'] ?? null) !== $expected) {
                throw new ComparisonProfileBindingRejected('Baseline divergente du registre interne.');
            }

            return;
        }
        // Aucune baseline réelle n'est joint au registre : refuser en production
        // et n'accepter qu'une fixture déclarée test_only_fixture en
        // environnement de test. HAAS n'invente aucune référence pédagogique.
        $isTestFixture = (bool) ($baseline['test_only_fixture'] ?? false);
        if (! $isTestFixture || config('app.env') !== 'testing') {
            throw new ComparisonProfileBindingRejected('Baseline réelle absente du registre ; profil refusé.');
        }
    }
}
