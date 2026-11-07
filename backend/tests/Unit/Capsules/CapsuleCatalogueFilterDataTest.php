<?php

declare(strict_types=1);

namespace Tests\Unit\Capsules;

use App\Data\Capsules\CapsuleCatalogueFilterData;
use App\Data\Common\PageData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CapsuleCatalogueFilterDataTest extends TestCase
{
    public function test_search_limit_counts_unicode_characters(): void
    {
        $search = str_repeat('é', 200);
        $filter = new CapsuleCatalogueFilterData(new PageData, $search);

        $this->assertSame($search, $filter->search);
    }

    public function test_search_above_two_hundred_characters_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CapsuleCatalogueFilterData(new PageData, str_repeat('é', 201));
    }
}
