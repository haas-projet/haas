<?php

namespace App\Data\Collaboration;

use InvalidArgumentException;

final readonly class CommentData
{
    public function __construct(public string $body, public ?int $lockVersion = null)
    {
        if ($lockVersion !== null && ($lockVersion < 1 || $lockVersion >= 2147483647)) {
            throw new InvalidArgumentException('Version de commentaire hors contrat.');
        }
    }

    /** @return array{body: string, lock_version: ?int} */
    public function payload(): array
    {
        return ['body' => $this->body, 'lock_version' => $this->lockVersion];
    }
}
