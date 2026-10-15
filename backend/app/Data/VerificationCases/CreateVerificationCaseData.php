<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

final readonly class CreateVerificationCaseData
{
    /** @param array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string} $body */
    public function __construct(
        public VerificationCaseParent $parentKind,
        public ?string $helpRequestId,
        public ?string $capsuleVersionId,
        public array $body,
    ) {}

    /** @return array<array-key, mixed> */
    public function payload(): array
    {
        return [
            'parent_kind' => $this->parentKind->value,
            'help_request_id' => $this->helpRequestId,
            'capsule_version_id' => $this->capsuleVersionId,
            'body' => $this->body,
        ];
    }
}
