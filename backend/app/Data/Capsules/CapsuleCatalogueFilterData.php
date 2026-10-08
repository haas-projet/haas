<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use App\Data\Common\PageData;
use InvalidArgumentException;

/**
 * Filtre du catalogue public des capsules publiées (B26).
 * `sort` est en liste blanche stricte ; `relevance` exige une
 * recherche textuelle, sinon le FormRequest renvoie 422.
 */
final readonly class CapsuleCatalogueFilterData
{
    public const SORTS = ['date', 'relevance'];

    public function __construct(
        public PageData $page,
        public ?string $search = null,
        public ?string $technology = null,
        public string $sort = 'date',
    ) {
        if (! in_array($sort, self::SORTS, true)) {
            throw new InvalidArgumentException('Tri hors liste blanche.');
        }
        if ($sort === 'relevance' && ($search === null || trim($search) === '')) {
            throw new InvalidArgumentException('Le tri par pertinence exige une recherche textuelle.');
        }
        if ($technology !== null && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $technology) !== 1) {
            throw new InvalidArgumentException('technology doit être un UUID.');
        }
        if ($search !== null && strlen($search) > 200) {
            throw new InvalidArgumentException('La recherche doit faire 200 caractères maximum.');
        }
    }
}
