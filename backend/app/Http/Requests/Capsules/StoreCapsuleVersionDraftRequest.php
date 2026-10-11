<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Rules\NoLikelySecret;
use App\Rules\TrimmedMinimumLength;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreCapsuleVersionDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'version_label' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\z/', 'max:40'],
            'body' => ['required', 'string', 'min:'.VersionDraftData::BODY_MIN, 'max:'.VersionDraftData::BODY_MAX, new TrimmedMinimumLength(VersionDraftData::BODY_MIN), 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
            'limits' => ['nullable', 'string', 'min:1', 'max:'.VersionDraftData::LIMITS_MAX, 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
            'technologies' => ['sometimes', 'array', 'list', 'max:'.VersionDraftData::TECHS_MAX],
            'technologies.*' => ['array:technology_id,version_label'],
            'technologies.*.technology_id' => ['required', 'string', 'uuid', 'distinct:ignore_case', 'exists:technologies,id'],
            'technologies.*.version_label' => ['nullable', 'string', 'max:40', 'regex:/\A\S(?:.*\S)?\z/u', 'not_regex:/[[:cntrl:]]/u', new NoLikelySecret],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['version_label', 'body', 'limits', 'technologies']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
            foreach (['owner_id', 'reviewer_id', 'state', 'published_at', 'id', 'capsule_id'] as $forbidden) {
                if ($this->has($forbidden)) {
                    $validator->errors()->add($forbidden, 'Ce champ est fixé par le serveur et ne peut pas être soumis.');
                }
            }
        });
    }

    public function toDraft(): VersionDraftData
    {
        /** @var array{version_label:string,body:string,limits?:?string,technologies?:list<array{technology_id:string,version_label:?string}>} $v */
        $v = $this->validated();
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
