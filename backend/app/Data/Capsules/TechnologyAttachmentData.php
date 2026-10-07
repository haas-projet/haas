<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use InvalidArgumentException;

final readonly class TechnologyAttachmentData
{
    public function __construct(
        public string $technologyId,
        public ?string $versionLabel,
    ) {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $technologyId) !== 1) {
            throw new InvalidArgumentException('UUID technology_id invalide.');
        }
        if ($versionLabel !== null) {
            $trimmed = trim($versionLabel);
            if ($trimmed === '' || $trimmed !== $versionLabel || strlen($versionLabel) > 40 || preg_match('/[[:cntrl:]]/u', $versionLabel) === 1) {
                throw new InvalidArgumentException('version_label de technology hors contrat.');
            }
        }
    }
}
