<?php

declare(strict_types=1);

namespace App\Support\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

final class ArtifactLinks
{
    /** @param array<string, string> $parameters */
    public function signed(string $route, Carbon $expiresAt, array $parameters): string
    {
        $origin = config('app.url');
        if (! is_string($origin) || filter_var($origin, FILTER_VALIDATE_URL) === false) {
            throw new ArtifactUnavailable;
        }
        $parsed = parse_url($origin);
        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])
            || ! in_array($parsed['scheme'], ['https', 'http'], true)
            || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment'])
            || (isset($parsed['path']) && ! in_array($parsed['path'], ['', '/'], true))) {
            throw new ArtifactUnavailable;
        }

        // Relative signatures do not trust the incoming Host header.
        return rtrim($origin, '/').URL::temporarySignedRoute($route, $expiresAt, $parameters, absolute: false);
    }
}
