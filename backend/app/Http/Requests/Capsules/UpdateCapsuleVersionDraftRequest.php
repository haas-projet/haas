<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\UpdateDraftData;
use App\Data\Capsules\VersionDraftData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCapsuleVersionDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'body' => ['sometimes', 'string', 'min:'.VersionDraftData::BODY_MIN, 'max:'.VersionDraftData::BODY_MAX],
            'limits' => ['sometimes', 'nullable', 'string', 'min:1', 'max:'.VersionDraftData::LIMITS_MAX],
            'technologies' => ['sometimes', 'array', 'max:'.VersionDraftData::TECHS_MAX],
            'technologies.*' => ['array:technology_id,version_label'],
            'technologies.*.technology_id' => ['required', 'string', 'uuid'],
            'technologies.*.version_label' => ['nullable', 'string', 'max:40', 'regex:/\A\S(?:.*\S)?\z/u'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['owner_id', 'reviewer_id', 'state', 'published_at', 'id', 'capsule_id', 'version_label'] as $forbidden) {
                if ($this->has($forbidden)) {
                    $validator->errors()->add($forbidden, 'Ce champ est fixé par le serveur et ne peut pas être soumis.');
                }
            }
            if (array_intersect(array_keys($this->all()), ['body', 'limits', 'technologies']) === []) {
                $validator->errors()->add('changes', 'Fournissez au moins un champ à modifier.');
            }
        });
    }

    public function toUpdateData(): UpdateDraftData
    {
        /** @var array{lock_version:int,body?:string,limits?:?string,technologies?:list<array{technology_id:string,version_label:?string}>} $v */
        $v = $this->validated();
        $tech = null;
        if (array_key_exists('technologies', $v)) {
            $tech = [];
            foreach ($v['technologies'] as $attachment) {
                $tech[] = new TechnologyAttachmentData(
                    technologyId: $attachment['technology_id'],
                    versionLabel: $attachment['version_label'] ?? null,
                );
            }
        }

        return new UpdateDraftData(
            lockVersion: $v['lock_version'],
            body: $v['body'] ?? null,
            limits: array_key_exists('limits', $v) ? $v['limits'] : null,
            technologies: $tech,
        );
    }
}
