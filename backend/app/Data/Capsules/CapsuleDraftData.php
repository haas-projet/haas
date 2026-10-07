<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use App\Enums\Capsules\CapsuleSourceKind;
use InvalidArgumentException;

final readonly class CapsuleDraftData
{
    public function __construct(
        public string $slug,
        public CapsuleSourceKind $sourceKind,
        public ?string $sourceRequestId,
        public ?string $editorialOrigin,
        public VersionDraftData $version,
    ) {
        if (preg_match('/\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z/', $slug) !== 1) {
            throw new InvalidArgumentException('slug hors contrat.');
        }
        $sourceSet = $sourceRequestId !== null;
        $editorialSet = $editorialOrigin !== null;
        $expectsSource = $sourceKind === CapsuleSourceKind::HelpRequest;
        if ($expectsSource !== $sourceSet || $expectsSource === $editorialSet) {
            throw new InvalidArgumentException('Source : exactement une de source_request_id ou editorial_origin selon kind.');
        }
        if ($sourceRequestId !== null && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $sourceRequestId) !== 1) {
            throw new InvalidArgumentException('source_request_id doit être un UUID.');
        }
        if ($editorialOrigin !== null) {
            $trimmed = trim($editorialOrigin);
            if (mb_strlen($trimmed) < 3 || mb_strlen($editorialOrigin) > 100 || preg_match('/[[:cntrl:]]/u', $editorialOrigin) === 1) {
                throw new InvalidArgumentException('editorial_origin hors contrat.');
            }
        }
    }
}
