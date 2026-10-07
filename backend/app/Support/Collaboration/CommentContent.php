<?php

namespace App\Support\Collaboration;

use App\Rules\NoLikelySecret;
use Illuminate\Support\Facades\Validator;

final class CommentContent
{
    /** @return array<mixed> */
    public static function rules(): array
    {
        return ['required', 'string', 'min:1', 'max:4000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];
    }

    public static function validate(string $body): void
    {
        Validator::make(['body' => $body], ['body' => self::rules()])->validate();
    }
}
