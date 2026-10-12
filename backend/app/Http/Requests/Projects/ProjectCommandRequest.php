<?php

namespace App\Http\Requests\Projects;

use App\Data\Idempotency\IdempotencyKey;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;
use stdClass;

abstract class ProjectCommandRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    abstract public function rules(): array;

    private bool $hasJsonObject = false;

    private bool $invalidTechnologyShape = false;

    protected function prepareForValidation(): void
    {
        if (! $this->isJson()) {
            return;
        }
        try {
            $body = json_decode($this->getContent(), flags: JSON_THROW_ON_ERROR);
            $values = json_decode($this->getContent(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }
        if ($body instanceof stdClass) {
            $this->hasJsonObject = true;
            $this->invalidTechnologyShape = property_exists($body, 'technology_ids') && $body->technology_ids instanceof stdClass;
            // Restore the original JSON before validation, including controls and strict integers.
            $this->merge($values);
        }
    }

    public function member(): User
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }

    public function projectId(): string
    {
        return strtolower((string) $this->route('id'));
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasJsonObject) {
                $validator->errors()->add('body', 'Envoyez un objet JSON.');
            }
            if ($this->invalidTechnologyShape) {
                $validator->errors()->add('technology_ids', 'Fournissez une liste JSON, pas un objet.');
            }
            if (! IdempotencyKey::valid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 et conservez-le pour les relances.');
            }
            $allowed = array_filter(array_keys($this->rules()), fn (string $field): bool => ! str_contains($field, '.'));
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni.');
            }
            foreach (array_keys($this->query->all()) as $field) {
                $validator->errors()->add($field, 'Fournissez les champs dans le corps JSON uniquement.');
            }
        });
    }
}
