<?php

namespace App\Data\HelpRequests;

use App\Data\Common\PageData;
use App\Enums\HelpRequests\HelpRequestState;
use InvalidArgumentException;

final readonly class HelpRequestFilterData
{
    public const SORTS = ['newest', 'oldest', 'updated'];

    public function __construct(
        public PageData $page,
        public string $scope = 'public',
        public ?string $search = null,
        public ?string $technology = null,
        public ?HelpRequestState $state = null,
        public string $sort = 'newest',
    ) {
        if (! in_array($scope, ['public', 'mine'], true) || ! in_array($sort, self::SORTS, true)) {
            throw new InvalidArgumentException('Filtre de demandes hors limites.');
        }
    }
}
