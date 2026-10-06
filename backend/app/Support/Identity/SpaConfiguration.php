<?php

namespace App\Support\Identity;

use LogicException;

final class SpaConfiguration
{
    public function validate(): void
    {
        $frontend = config('spa.frontend_url');
        $api = config('app.url');
        $origins = config('cors.allowed_origins');
        $domains = config('sanctum.stateful');
        if (! is_string($frontend) || ! is_string($api) || ! is_array($origins) || ! is_array($domains)) {
            throw new LogicException('Configuration des origines SPA invalide.');
        }
        foreach ([$frontend, $api] as $origin) {
            if (! preg_match('~\Ahttps?://[a-z0-9.-]+(?::[0-9]{1,5})?\z~', $origin)) {
                throw new LogicException('Les origines SPA doivent être exactes, sans chemin ni wildcard.');
            }
        }
        $authorities = array_map(fn (string $origin): string => explode('://', $origin, 2)[1], [$frontend, $api]);
        if (! in_array($frontend, $origins, true) || array_diff($origins, [$frontend, $api]) !== []
            || ! in_array($authorities[0], $domains, true) || array_diff($domains, $authorities) !== []
            || config('cors.allowed_origins_patterns') !== [] || config('cors.supports_credentials') !== true) {
            throw new LogicException('CORS et Sanctum doivent désigner uniquement les origines HAAS configurées.');
        }
        if (config('session.http_only') !== true || config('session.same_site') !== 'lax') {
            throw new LogicException('La session HAAS exige HttpOnly et SameSite=Lax.');
        }
        if (app()->isProduction()) {
            if (! str_starts_with($frontend, 'https://') || ! str_starts_with($api, 'https://') || config('session.secure') !== true) {
                throw new LogicException('HTTPS et cookies Secure requis en production.');
            }
            $frontHost = parse_url($frontend, PHP_URL_HOST);
            $apiHost = parse_url($api, PHP_URL_HOST);
            if (! is_string($frontHost) || ! is_string($apiHost)) {
                throw new LogicException('Hôtes SPA invalides.');
            }
            if ($frontHost !== $apiHost) {
                $first = array_reverse(explode('.', $frontHost));
                $second = array_reverse(explode('.', $apiHost));
                $common = [];
                foreach ($first as $index => $label) {
                    if (($second[$index] ?? null) !== $label) {
                        break;
                    }
                    $common[] = $label;
                }
                if (count($common) < 2 || config('session.domain') !== '.'.implode('.', array_reverse($common))) {
                    throw new LogicException('Le cookie doit être borné au parent commun des hôtes HAAS de confiance.');
                }
            }
        }
    }
}
