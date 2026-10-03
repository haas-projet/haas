<?php

namespace App\Data\Moderation;

use Illuminate\Support\Str;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class ReportData
{
    public const array CATEGORIES = ['secret_exposed', 'abuse', 'uncertain_rights', 'misleading_solution', 'other'];

    public function __construct(public string $resourceId, public string $category, #[SensitiveParameter] public string $detail)
    {
        if (! Str::isUuid($resourceId) || ! in_array($category, self::CATEGORIES, true) || mb_strlen(trim($detail)) < 20 || mb_strlen($detail) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $detail) !== 0) {
            throw new InvalidArgumentException('Signalement invalide.');
        }
    }
}
