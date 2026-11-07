<?php

declare(strict_types=1);

namespace App\Data\Lab;

final readonly class LabDefinitionData
{
    public function __construct(
        public string $id,
        public string $versionId,
        public string $release,
        public string $moduleVersion,
        public string $codeDigest,
        public string $suiteDigest,
        public string $versionDigest,
    ) {}
}
