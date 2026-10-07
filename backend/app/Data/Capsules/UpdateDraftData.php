<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use InvalidArgumentException;

/**
 * Charge d'édition d'un brouillon : lock_version obligatoire + champs
 * optionnels. version_label ne figure pas (immuable après création).
 * state/reviewer_id/published_at/owner_id ne figurent pas (serveur seul).
 */
final readonly class UpdateDraftData
{
    public function __construct(
        public int $lockVersion,
        public ?string $body,
        public ?string $limits,
        /** @var ?list<TechnologyAttachmentData> */
        public ?array $technologies,
    ) {
        if ($lockVersion < 1 || $lockVersion > 2147483647) {
            throw new InvalidArgumentException('lock_version hors bornes.');
        }
        if ($body !== null) {
            $bodyTrimmed = trim($body);
            if (strlen($bodyTrimmed) < VersionDraftData::BODY_MIN || strlen($body) > VersionDraftData::BODY_MAX) {
                throw new InvalidArgumentException('body hors bornes de taille.');
            }
        }
        if ($limits !== null) {
            $limitsTrimmed = trim($limits);
            if ($limitsTrimmed === '' || strlen($limits) > VersionDraftData::LIMITS_MAX) {
                throw new InvalidArgumentException('limits hors bornes.');
            }
        }
        if ($technologies !== null) {
            if (count($technologies) > VersionDraftData::TECHS_MAX) {
                throw new InvalidArgumentException('Au plus '.VersionDraftData::TECHS_MAX.' technologies.');
            }
            $ids = [];
            foreach ($technologies as $attachment) {
                if (in_array($attachment->technologyId, $ids, true)) {
                    throw new InvalidArgumentException('Technologie dupliquée.');
                }
                $ids[] = $attachment->technologyId;
            }
        }
    }

    public function hasAnyChange(): bool
    {
        return $this->body !== null || $this->limits !== null || $this->technologies !== null;
    }
}
