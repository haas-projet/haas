<?php

namespace App\Data\Identity;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class ManageAccountData
{
    public function __construct(public int $version, public string $field, public string $value, #[SensitiveParameter] public string $reason)
    {
        if ($version < 0 || $version > 2147483646 || ! in_array($field, ['status', 'role'], true)
            || ($field === 'status' ? AccountStatus::tryFrom($value) === null : Role::tryFrom($value) === null)
            || mb_strlen(trim($reason)) < 20 || mb_strlen($reason) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $reason) !== 0) {
            throw new InvalidArgumentException('Décision de compte invalide.');
        }
    }
}
