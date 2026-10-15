<?php

declare(strict_types=1);

namespace App\Http\Requests\VerificationCases;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\ReviseVerificationCaseData;
use App\Support\VerificationCases\VerificationCaseBody;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateVerificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1', 'max:2147483646'],
            ...VerificationCaseBody::rules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['lock_version', 'body']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
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
            foreach (['id', 'state', 'current_revision', 'author_id', 'help_request_id', 'capsule_version_id'] as $forbidden) {
                if ($this->has($forbidden)) {
                    $validator->errors()->add($forbidden, 'Ce champ est fixé par le serveur et ne peut pas être soumis.');
                }
            }
        });
    }

    public function toRevise(): ReviseVerificationCaseData
    {
        /** @var array{lock_version:int,body:array<string,mixed>} $v */
        $v = $this->validated();

        return new ReviseVerificationCaseData((int) $v['lock_version'], VerificationCaseBody::normalize($v['body']));
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
