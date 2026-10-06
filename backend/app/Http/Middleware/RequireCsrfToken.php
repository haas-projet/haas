<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class RequireCsrfToken extends PreventRequestForgery
{
    // Laravel 13 peut accepter Sec-Fetch-Site seul ; HAAS exige toujours le jeton.
    protected function hasValidOrigin($request): bool
    {
        return false;
    }
}
