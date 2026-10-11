<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Resources\Capsules\CapsuleCatalogueDetail;
use App\Queries\Capsules\FindVisibleCapsuleQuery;
use Illuminate\Http\Request;

final class ShowVisibleCapsuleController extends Controller
{
    public function __invoke(Request $request, FindVisibleCapsuleQuery $query, string $slug, ?string $version = null): CapsuleCatalogueDetail
    {
        return new CapsuleCatalogueDetail($query->get($slug, $version));
    }
}
