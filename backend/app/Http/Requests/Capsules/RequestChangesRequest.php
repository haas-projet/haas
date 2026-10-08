<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Rules\NoLikelySecret;
use Illuminate\Contracts\Validation\ValidationRule;

final class RequestChangesRequest extends CapsuleReviewCommandRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [...parent::rules(),
            'note' => ['required', 'string', 'min:20', 'max:2000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
        ];
    }

    public function note(): string
    {
        /** @var array{note:string} $v */
        $v = $this->validated();

        return $v['note'];
    }
}
