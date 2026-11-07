<?php

namespace App\Http\Requests\Collaboration;

use App\Data\Collaboration\ProposalData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\Proposal;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Support\Collaboration\ProposalContent;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;
use stdClass;

class StoreProposalRequest extends FormRequest
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
        } catch (JsonException) {
            return;
        }
        if (! $body instanceof stdClass) {
            return;
        }
        $this->bodyFields = array_keys(get_object_vars($body));
        // Validate the original JSON before text normalization and preserve the inert code bytes.
        foreach (ProposalContent::FIELDS as $field) {
            if (property_exists($body, $field)) {
                $this->merge([$field => $body->{$field}]);
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

    public function targetId(): string
    {
        return strtolower((string) $this->route('id'));
    }

    public function authorize(): bool
    {
        $parent = app(FindVisibleHelpRequestQuery::class)->get($this->member(), $this->targetId());

        return $this->member()->can('create', [Proposal::class, $parent]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ProposalContent::rules();
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
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
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
        $messages = ['code.*' => 'Le code facultatif contient au maximum 12 000 caractères, sans secret ni caractère de contrôle.',
            'lock_version.*' => 'Indiquez une version entière JSON entre 1 et 2147483646.'];
        foreach (array_slice(ProposalContent::FIELDS, 0, 4) as $field) {
            $messages[$field.'.*'] = 'Décrivez cet apport en 20 à 4 000 caractères utiles, sans secret ni caractère de contrôle.';
        }

        return $messages;
    }

    public function proposalData(): ProposalData
    {
        return new ProposalData(array_intersect_key($this->validated(), array_flip(ProposalContent::FIELDS)));
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
