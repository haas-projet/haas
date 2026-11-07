<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\HelpRequests\ReopeningData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Support\HelpRequests\ReopeningContent;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;
use stdClass;

final class ReopenHelpRequestRequest extends FormRequest
{
    /** @var list<string>|null */
    private ?array $bodyFields = null;

    protected function prepareForValidation(): void
    {
        if (! $this->isJson()) {
            return;
        }
        try {
            $body = json_decode($this->getContent(), flags: JSON_THROW_ON_ERROR);
            $values = json_decode($this->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }
        if (! $body instanceof stdClass || ! is_array($values)) {
            return;
        }
        $this->bodyFields = array_keys(get_object_vars($body));
        // Validate raw controls and strict JSON integers before normalization.
        foreach (ReopeningContent::FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $this->merge([$field => $values[$field]]);
            }
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

    public function requestId(): string
    {
        return strtolower((string) $this->route('id'));
    }

    public function authorize(): bool
    {
        $parent = app(FindVisibleHelpRequestQuery::class)->get($this->member(), $this->requestId());

        return $this->member()->can('reopen', $parent);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ReopeningContent::rules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->bodyFields === null || $this->bodyFields === []) {
                $validator->errors()->add('body', 'Envoyez un objet JSON non vide.');
            }
            if (! IdempotencyKey::valid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 et conservez-le pour les relances.');
            }
            foreach (array_diff(array_keys($this->all()), ReopeningContent::FIELDS) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni.');
            }
            foreach (array_keys($this->query->all()) as $field) {
                $validator->errors()->add($field, 'Fournissez les champs dans le corps JSON uniquement.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ReopeningContent::messages();
    }

    public function reopeningData(): ReopeningData
    {
        return new ReopeningData($this->validated());
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
