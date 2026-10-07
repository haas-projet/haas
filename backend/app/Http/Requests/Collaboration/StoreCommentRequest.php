<?php

namespace App\Http\Requests\Collaboration;

use App\Data\Collaboration\CommentData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\Comment;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Support\Collaboration\CommentContent;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCommentRequest extends FormRequest
{
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

        return $this->member()->can('create', [Comment::class, $parent]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['body' => CommentContent::rules()];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! IdempotencyKey::valid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 et conservez-le pour les relances.');
            }
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['body.*' => 'Écrivez un commentaire de 1 à 4 000 caractères sans secret ni caractère de contrôle.', 'lock_version.*' => 'Indiquez une version entière entre 1 et 2147483646.'];
    }

    public function commentData(): CommentData
    {
        return new CommentData($this->validated('body'));
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
