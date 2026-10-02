<?php

namespace App\Data\Common;

use InvalidArgumentException;

final readonly class PageData
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public const MAX_PAGE = 2147483647;

    public function __construct(public int $page = 1, public int $perPage = self::DEFAULT_PER_PAGE)
    {
        if ($page < 1 || $page > self::MAX_PAGE || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Pagination hors limites.');
        }
    }
}
