<?php

namespace Tests\Support;

use App\Http\Middleware\RequireCsrfToken;

final class EnforcedCsrfToken extends RequireCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
