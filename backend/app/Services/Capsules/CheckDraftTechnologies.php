<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\TechnologyAttachmentData;
use App\Models\Technology;
use Illuminate\Validation\ValidationException;

final class CheckDraftTechnologies
{
    /** @param list<TechnologyAttachmentData> $attachments */
    public function check(array $attachments): void
    {
        $ids = array_map(static fn (TechnologyAttachmentData $item): string => strtolower($item->technologyId), $attachments);
        $found = Technology::whereIn('id', $ids)->orderBy('id')->sharedLock()->get(['id']);
        if (count(array_unique($ids)) !== count($ids) || $found->count() !== count($ids)) {
            throw ValidationException::withMessages(['technologies' => ['Les technologies doivent être connues et distinctes.']]);
        }
    }
}
