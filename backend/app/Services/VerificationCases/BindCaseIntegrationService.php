<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Enums\Lab\LabScenario;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\VerificationCases\VerificationCaseBindingRejected;
use App\Models\User;
use App\Models\VerificationCases\CaseScenarioLink;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use App\Support\Identity\MemberAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// BV203 — commande interne d'intégration.
// Lit un manifeste de release (JSON) et crée les liens entre les révisions
// relues et les scénarios approuvés de LabScenario. Idempotente : refuse une
// révision déjà intégrée et refuse un digest incohérent. Jamais invoquée
// par une route HTTP (pas de passage public à `integrated`).
final class BindCaseIntegrationService
{
    public function __construct(private readonly MemberAccess $access) {}

    /** @return array{inserted: int, skipped: int} */
    public function bindFromManifest(string $manifestPath): array
    {
        if (! is_file($manifestPath) || ! is_readable($manifestPath)) {
            throw new VerificationCaseBindingRejected('Manifeste illisible.');
        }
        $contents = @file_get_contents($manifestPath);
        if (! is_string($contents) || $contents === '') {
            throw new VerificationCaseBindingRejected('Manifeste vide.');
        }
        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new VerificationCaseBindingRejected('Manifeste JSON invalide.');
        }
        $data = $this->validate($decoded);

        return DB::connection('pgsql')->transaction(function () use ($data): array {
            $inserted = 0;
            $skipped = 0;
            foreach ($data['verification_cases']['bindings'] as $binding) {
                $revision = VerificationCaseRevision::whereKey($binding['case_revision_id'])
                    ->lockForUpdate()
                    ->first();
                if ($revision === null || $revision->submitted_at === null) {
                    throw new VerificationCaseBindingRejected('Révision inconnue ou non figée : '.$binding['case_revision_id']);
                }
                $case = VerificationCase::whereKey($revision->case_id)->lockForUpdate()->firstOrFail();
                if ($case->state !== VerificationCaseState::Reviewed && $case->state !== VerificationCaseState::Integrated) {
                    throw new VerificationCaseBindingRejected('Cas non revu : '.$case->id);
                }
                if ((int) $case->current_revision !== (int) $revision->revision_no) {
                    throw new VerificationCaseBindingRejected('La révision cible n\'est pas la révision soumise courante : '.$revision->id);
                }
                if ($revision->digest !== $binding['suite_digest']) {
                    // suite_digest est un invariant transporté par le manifeste
                    // par révision ; une incohérence écarte le binding sans
                    // modifier l'état.
                    throw new VerificationCaseBindingRejected('Digest incohérent pour la révision : '.$revision->id);
                }
                $reviewer = User::whereKey($binding['reviewer_id'])->lockForUpdate()->first();
                if ($reviewer === null || ! $this->access->verified($reviewer) || $reviewer->id === $case->author_id) {
                    throw new VerificationCaseBindingRejected('Relecteur introuvable, non vérifié ou auto-revue : '.$binding['reviewer_id']);
                }
                $existing = CaseScenarioLink::where('case_revision_id', $revision->id)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->scenario_key !== $binding['scenario_key']
                        || $existing->suite_digest !== $binding['suite_digest']
                        || $existing->release_commit !== $data['verification_cases']['release_commit']
                        || $existing->reviewer_id !== $binding['reviewer_id']) {
                        throw new VerificationCaseBindingRejected('Lien existant divergent pour la révision : '.$revision->id);
                    }
                    $skipped++;

                    continue;
                }
                (new CaseScenarioLink)->forceFill([
                    'case_revision_id' => $revision->id,
                    'scenario_key' => $binding['scenario_key'],
                    'suite_digest' => $binding['suite_digest'],
                    'release_commit' => $data['verification_cases']['release_commit'],
                    'reviewer_id' => $reviewer->id,
                    'integrated_at' => now()->utc(),
                ])->save();
                if ($case->state !== VerificationCaseState::Integrated) {
                    $case->state = VerificationCaseState::Integrated;
                    $case->lock_version++;
                    $case->save();
                }
                $inserted++;
            }

            return ['inserted' => $inserted, 'skipped' => $skipped];
        });
    }

    /**
     * @return array{verification_cases: array{release_commit: string, bindings: list<array{case_revision_id:string,scenario_key:string,suite_digest:string,reviewer_id:string}>}}
     */
    private function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new VerificationCaseBindingRejected('Manifeste non objet.');
        }
        $allowedScenarios = array_map(static fn (LabScenario $s): string => $s->value, LabScenario::cases());
        try {
            Validator::make($decoded, [
                'verification_cases' => ['required', 'array:release_commit,bindings'],
                'verification_cases.release_commit' => ['required', 'string', 'regex:/\A[0-9a-f]{40}\z/'],
                'verification_cases.bindings' => ['required', 'array', 'list', 'min:1'],
                'verification_cases.bindings.*' => ['array:case_revision_id,scenario_key,suite_digest,reviewer_id'],
                'verification_cases.bindings.*.case_revision_id' => ['required', 'string', 'uuid'],
                'verification_cases.bindings.*.scenario_key' => ['required', 'string', Rule::in($allowedScenarios)],
                'verification_cases.bindings.*.suite_digest' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
                'verification_cases.bindings.*.reviewer_id' => ['required', 'string', 'uuid'],
            ])->validate();
        } catch (ValidationException $e) {
            throw new VerificationCaseBindingRejected('Manifeste hors gabarit : '.$e->getMessage());
        }

        /** @var array{verification_cases: array{release_commit: string, bindings: list<array{case_revision_id:string,scenario_key:string,suite_digest:string,reviewer_id:string}>}} $decoded */
        return $decoded;
    }
}
