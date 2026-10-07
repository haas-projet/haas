<?php

namespace App\Data\Idempotency;

use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

final readonly class IdempotencyData
{
    private string $canonicalPayload;

    /** @param array<array-key, mixed> $payload */
    public function __construct(public string $routeTarget, public IdempotencyKey $key, #[SensitiveParameter] array $payload)
    {
        if (strlen($routeTarget) > 255 || preg_match('~\A(?:POST|PUT|PATCH|DELETE) /api/v1/(?:[a-z0-9_-]+/)*[a-z0-9_-]+\z~', $routeTarget) !== 1) {
            throw new InvalidArgumentException('Cible idempotente hors contrat.');
        }
        try {
            $json = json_encode(self::normalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new InvalidArgumentException('Charge idempotente hors contrat.');
        }
        if (strlen($json) > 131072) {
            throw new InvalidArgumentException('Charge idempotente trop volumineuse.');
        }
        $this->canonicalPayload = $json;
    }

    public function fingerprint(#[SensitiveParameter] string $secret): string
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Clé serveur d’idempotence invalide.');
        }

        return hash_hmac('sha256', "haas-api-idempotency-v1\0".$this->canonicalPayload, $secret);
    }

    private static function normalize(#[SensitiveParameter] mixed $value, int $depth = 0): mixed
    {
        if ($depth > 16) {
            throw new InvalidArgumentException('Charge idempotente trop profonde.');
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item, $depth + 1);
            }

            return $value;
        }
        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }
        throw new InvalidArgumentException('Type de charge idempotente non accepté.');
    }
}
