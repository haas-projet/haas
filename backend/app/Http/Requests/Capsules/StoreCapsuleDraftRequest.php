<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Rules\NoLikelySecret;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreCapsuleDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $allowedKinds = implode(',', array_map(static fn (CapsuleSourceKind $k): string => $k->value, CapsuleSourceKind::cases()));

        return [
            'slug' => ['required', 'string', 'min:3', 'max:120', 'regex:/\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z/'],
            'source' => ['required', 'array:kind,help_request_id,editorial_origin'],
            'source.kind' => ['required', 'string', 'in:'.$allowedKinds],
            'source.help_request_id' => ['nullable', 'string', 'uuid'],
            'source.editorial_origin' => ['nullable', 'string', 'min:3', 'max:100', 'regex:/\A\S(?:.*\S)?\z/u', 'not_regex:/[[:cntrl:]]/u', new NoLikelySecret],
            'version' => ['required', 'array:version_label,body,limits,technologies'],
            'version.version_label' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\z/', 'max:40'],
            'version.body' => ['required', 'string', 'min:'.VersionDraftData::BODY_MIN, 'max:'.VersionDraftData::BODY_MAX, 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
            'version.limits' => ['nullable', 'string', 'min:1', 'max:'.VersionDraftData::LIMITS_MAX, 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
            'version.technologies' => ['sometimes', 'array', 'list', 'max:'.VersionDraftData::TECHS_MAX],
            'version.technologies.*' => ['array:technology_id,version_label'],
            'version.technologies.*.technology_id' => ['required', 'string', 'uuid', 'distinct:ignore_case', 'exists:technologies,id'],
            'version.technologies.*.version_label' => ['nullable', 'string', 'max:40', 'regex:/\A\S(?:.*\S)?\z/u', 'not_regex:/[[:cntrl:]]/u', new NoLikelySecret],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['slug', 'source', 'version']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
            // Rejet des champs serveur (AC04).
            foreach (['owner_id', 'reviewer_id', 'state', 'published_at', 'id', 'author_id'] as $forbidden) {
                if ($this->has($forbidden) || $this->has('version.'.$forbidden)) {
                    $validator->errors()->add($forbidden, 'Ce champ est fixé par le serveur et ne peut pas être soumis.');
                }
            }
            // Contrainte XOR entre source.kind et le champ attendu : évaluée AVANT la Policy et le DTO.
            $kind = $this->input('source.kind');
            $hasHelp = $this->filled('source.help_request_id');
            $hasEditorial = $this->filled('source.editorial_origin');
            if ($kind === 'help_request' && ! $hasHelp) {
                $validator->errors()->add('source.help_request_id', 'La source help_request exige un help_request_id.');
            }
            if ($kind === 'editorial' && ! $hasEditorial) {
                $validator->errors()->add('source.editorial_origin', 'La source editorial exige un editorial_origin.');
            }
            if ($kind === 'help_request' && $hasEditorial) {
                $validator->errors()->add('source.editorial_origin', 'editorial_origin ne doit pas être posé pour help_request.');
            }
            if ($kind === 'editorial' && $hasHelp) {
                $validator->errors()->add('source.help_request_id', 'help_request_id ne doit pas être posé pour editorial.');
            }
        });
    }

    public function toCapsuleDraft(): CapsuleDraftData
    {
        /** @var array{slug:string,source:array{kind:string,help_request_id?:?string,editorial_origin?:?string},version:array{version_label:string,body:string,limits?:?string,technologies?:list<array{technology_id:string,version_label:?string}>}} $v */
        $v = $this->validated();
        $kind = CapsuleSourceKind::from($v['source']['kind']);

        return new CapsuleDraftData(
            slug: $v['slug'],
            sourceKind: $kind,
            sourceRequestId: $kind === CapsuleSourceKind::HelpRequest ? ($v['source']['help_request_id'] ?? null) : null,
            editorialOrigin: $kind === CapsuleSourceKind::Editorial ? ($v['source']['editorial_origin'] ?? null) : null,
            version: $this->versionDraft($v['version']),
        );
    }

    /** @param array{version_label:string,body:string,limits?:?string,technologies?:list<array{technology_id:string,version_label:?string}>} $v */
    private function versionDraft(array $v): VersionDraftData
    {
        $tech = [];
        foreach ($v['technologies'] ?? [] as $attachment) {
            $tech[] = new TechnologyAttachmentData(
                technologyId: $attachment['technology_id'],
                versionLabel: $attachment['version_label'] ?? null,
            );
        }

        return new VersionDraftData(
            versionLabel: $v['version_label'],
            body: $v['body'],
            limits: $v['limits'] ?? null,
            technologies: $tech,
        );
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
