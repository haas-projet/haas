<?php

declare(strict_types=1);

namespace App\Http\Requests\VerificationCases;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\CreateVerificationCaseData;
use App\Data\VerificationCases\VerificationCaseParent;
use App\Support\VerificationCases\VerificationCaseBody;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreVerificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $allowedKinds = implode(',', array_map(static fn (VerificationCaseParent $k): string => $k->value, VerificationCaseParent::cases()));

        return [
            'parent' => ['required', 'array:kind,help_request_id,capsule_version_id'],
            'parent.kind' => ['required', 'string', 'in:'.$allowedKinds],
            'parent.help_request_id' => ['nullable', 'string', 'uuid'],
            'parent.capsule_version_id' => ['nullable', 'string', 'uuid'],
            ...VerificationCaseBody::rules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['parent', 'body']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
            foreach (['id', 'state', 'current_revision', 'lock_version', 'author_id', 'created_at', 'updated_at'] as $forbidden) {
                if ($this->has($forbidden)) {
                    $validator->errors()->add($forbidden, 'Ce champ est fixé par le serveur et ne peut pas être soumis.');
                }
            }
            $body = $this->input('body', []);
            if (is_array($body)) {
                foreach (VerificationCaseBody::FORBIDDEN_FIELDS as $forbidden) {
                    if (array_key_exists($forbidden, $body)) {
                        $validator->errors()->add('body.'.$forbidden, 'Un cas documentaire n’accepte pas ce champ exécutable.');
                    }
                }
                foreach (array_diff(array_keys($body), [...VerificationCaseBody::REQUIRED_FIELDS, ...VerificationCaseBody::OPTIONAL_FIELDS]) as $extra) {
                    if (! in_array($extra, VerificationCaseBody::FORBIDDEN_FIELDS, true)) {
                        $validator->errors()->add('body.'.$extra, 'Ce champ ne peut pas être utilisé.');
                    }
                }
            }
            $kind = $this->input('parent.kind');
            $hasHelp = $this->filled('parent.help_request_id');
            $hasVersion = $this->filled('parent.capsule_version_id');
            if ($kind === VerificationCaseParent::HelpRequest->value && ! $hasHelp) {
                $validator->errors()->add('parent.help_request_id', 'Un cas rattaché à une demande exige help_request_id.');
            }
            if ($kind === VerificationCaseParent::CapsuleVersion->value && ! $hasVersion) {
                $validator->errors()->add('parent.capsule_version_id', 'Un cas rattaché à une version exige capsule_version_id.');
            }
            if ($kind === VerificationCaseParent::HelpRequest->value && $hasVersion) {
                $validator->errors()->add('parent.capsule_version_id', 'capsule_version_id ne doit pas être posé pour une demande.');
            }
            if ($kind === VerificationCaseParent::CapsuleVersion->value && $hasHelp) {
                $validator->errors()->add('parent.help_request_id', 'help_request_id ne doit pas être posé pour une version.');
            }
        });
    }

    public function toCreate(): CreateVerificationCaseData
    {
        /** @var array{parent:array{kind:string,help_request_id?:?string,capsule_version_id?:?string},body:array<string,mixed>} $v */
        $v = $this->validated();
        $kind = VerificationCaseParent::from($v['parent']['kind']);

        return new CreateVerificationCaseData(
            parentKind: $kind,
            helpRequestId: $kind === VerificationCaseParent::HelpRequest ? ($v['parent']['help_request_id'] ?? null) : null,
            capsuleVersionId: $kind === VerificationCaseParent::CapsuleVersion ? ($v['parent']['capsule_version_id'] ?? null) : null,
            body: VerificationCaseBody::normalize($v['body']),
        );
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
