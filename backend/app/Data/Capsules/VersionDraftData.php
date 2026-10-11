<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use InvalidArgumentException;

final readonly class VersionDraftData
{
    public const BODY_MIN = 20;

    public const BODY_MAX = 24000;

    public const LIMITS_MAX = 4000;

    public const TECHS_MAX = 8;

    /** @param list<TechnologyAttachmentData> $technologies */
    public function __construct(
        public string $versionLabel,
        public string $body,
        public ?string $limits,
        public array $technologies,
    ) {
        if (preg_match('/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\z/', $versionLabel) !== 1 || strlen($versionLabel) > 40) {
            throw new InvalidArgumentException('version_label doit être MAJOR.MINOR.PATCH, 40 car. max.');
        }
        $bodyTrimmed = trim($body);
        if (mb_strlen($bodyTrimmed) < self::BODY_MIN || mb_strlen($body) > self::BODY_MAX) {
            throw new InvalidArgumentException('body hors bornes de taille.');
        }
        if ($limits !== null) {
            $limitsTrimmed = trim($limits);
            if ($limitsTrimmed === '' || mb_strlen($limits) > self::LIMITS_MAX) {
                throw new InvalidArgumentException('limits hors bornes de taille.');
            }
        }
        if (count($technologies) > self::TECHS_MAX) {
            throw new InvalidArgumentException('Au plus '.self::TECHS_MAX.' technologies déclarées.');
        }
        $ids = [];
        foreach ($technologies as $attachment) {
            if (in_array($attachment->technologyId, $ids, true)) {
                throw new InvalidArgumentException('Technologie dupliquée dans le brouillon.');
            }
            $ids[] = $attachment->technologyId;
        }
    }
}
