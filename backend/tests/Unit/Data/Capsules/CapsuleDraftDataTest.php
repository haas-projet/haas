<?php

declare(strict_types=1);

namespace Tests\Unit\Data\Capsules;

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\VersionDraftData;
use App\Enums\Capsules\CapsuleSourceKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CapsuleDraftDataTest extends TestCase
{
    public function test_valid_help_request_payload_is_accepted(): void
    {
        $data = new CapsuleDraftData(
            slug: 'contrats-paiement',
            sourceKind: CapsuleSourceKind::HelpRequest,
            sourceRequestId: '11111111-2222-4333-8444-555555555555',
            editorialOrigin: null,
            version: $this->validVersion(),
        );

        $this->assertSame('contrats-paiement', $data->slug);
    }

    public function test_valid_editorial_payload_is_accepted(): void
    {
        $data = new CapsuleDraftData(
            slug: 'demonstration-b1',
            sourceKind: CapsuleSourceKind::Editorial,
            sourceRequestId: null,
            editorialOrigin: 'Démonstration éditoriale B1',
            version: $this->validVersion(),
        );

        $this->assertSame(CapsuleSourceKind::Editorial, $data->sourceKind);
    }

    public function test_slug_hors_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CapsuleDraftData(
            slug: 'Contrats Paiement',
            sourceKind: CapsuleSourceKind::Editorial,
            sourceRequestId: null,
            editorialOrigin: 'origine',
            version: $this->validVersion(),
        );
    }

    public function test_mismatch_source_kind_vs_fields_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CapsuleDraftData(
            slug: 'ok-slug-test',
            sourceKind: CapsuleSourceKind::HelpRequest,
            sourceRequestId: null,
            editorialOrigin: 'origine',
            version: $this->validVersion(),
        );
    }

    public function test_body_too_short_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VersionDraftData(
            versionLabel: '1.0.0',
            body: 'trop court',
            limits: null,
            technologies: [],
        );
    }

    public function test_version_label_without_three_segments_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VersionDraftData(
            versionLabel: '1.0',
            body: str_repeat('a', VersionDraftData::BODY_MIN),
            limits: null,
            technologies: [],
        );
    }

    public function test_duplicate_technology_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VersionDraftData(
            versionLabel: '1.0.0',
            body: str_repeat('a', VersionDraftData::BODY_MIN),
            limits: null,
            technologies: [
                new TechnologyAttachmentData('11111111-2222-4333-8444-555555555555', null),
                new TechnologyAttachmentData('11111111-2222-4333-8444-555555555555', '^1.0'),
            ],
        );
    }

    private function validVersion(): VersionDraftData
    {
        return new VersionDraftData(
            versionLabel: '1.0.0',
            body: str_repeat('a', VersionDraftData::BODY_MIN),
            limits: 'Portée réduite au scénario cité.',
            technologies: [new TechnologyAttachmentData('11111111-2222-4333-8444-555555555555', '^1.0')],
        );
    }
}
