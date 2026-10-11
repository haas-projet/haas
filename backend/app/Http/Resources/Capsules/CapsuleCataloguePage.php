<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Http\Resources\PaginatedResourceCollection;

/**
 * Page paginée du catalogue (B26). Réutilise `PaginatedResourceCollection`
 * (B04). Pas d'en-tête personnalisé : la réponse ne mentionne jamais une
 * permission attachée à l'utilisateur courant.
 */
final class CapsuleCataloguePage extends PaginatedResourceCollection
{
    public $collects = CapsuleCataloguePreview::class;
}
