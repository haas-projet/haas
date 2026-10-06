<?php

namespace App\Data\Idempotency;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class IdempotencyKey
{
    public string $hash;

    public function __construct(#[SensitiveParameter] string $value)
    {
        if (! self::valid($value)) {
            throw new InvalidArgumentException('Clé d’idempotence invalide.');
        }
        $this->hash = hash('sha256', strtolower($value));
    }

    public static function valid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
    }
}
