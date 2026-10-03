<?php

namespace App\Data\Idempotency;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class StoredCommandResult
{
    /** @var array<string, string> */
    public array $references;

    /** @param array<array-key, mixed> $references */
    public function __construct(public int $status, array $references, public ?int $version = null)
    {
        if (! in_array($status, [200, 201, 202, 204], true) || count($references) < 1 || count($references) > 4
            || ($version !== null && ($version < 0 || $version > 2147483647))) {
            throw new InvalidArgumentException('Résultat idempotent hors contrat.');
        }
        $validated = [];
        foreach ($references as $name => $id) {
            if (! is_string($name) || preg_match('/\A[a-z][a-z_]{0,31}\z/', $name) !== 1 || ! is_string($id) || ! Str::isUuid($id)) {
                throw new InvalidArgumentException('Référence idempotente hors contrat.');
            }
            $validated[$name] = strtolower($id);
        }
        $this->references = $validated;
    }

    /** @return array{references: array<string, string>, version: ?int} */
    public function response(): array
    {
        return ['references' => $this->references, 'version' => $this->version];
    }
}
