<?php

namespace App\Http\Resources\Moderation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class ModerationReportResource extends ReportReceiptResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), ['category' => $this->category, 'detail' => Crypt::decryptString($this->detail_encrypted),
            'reporter_id' => $this->reporter_id, 'context' => $this->resource->getAttribute('context')]);
    }
}
