<?php

namespace App\Data\Moderation;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class ReportDecisionData
{
    public const array ACTIONS = ['review', 'dismiss', 'request_correction', 'hide'];

    public function __construct(public int $version, public string $action, #[SensitiveParameter] public string $reason, public ?int $profileVersion = null)
    {
        if ($version < 0 || $version > 2147483646 || ! in_array($action, self::ACTIONS, true) || mb_strlen(trim($reason)) < 20 || mb_strlen($reason) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $reason) !== 0
            || ($action === 'hide' && ($profileVersion === null || $profileVersion < 0 || $profileVersion > 2147483646))) {
            throw new InvalidArgumentException('Décision de signalement invalide.');
        }
    }
}
