<?php

namespace App\Http\Requests\Moderation;

use App\Data\Moderation\ReportData;
use App\Data\Moderation\ReportFilterData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class ReportListRequest extends ModerationRequest
{
    /** @return array<string, array<mixed>|string|ValidationRule> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'required', Rule::in(['new', 'in_review', 'resolved', 'dismissed'])],
            'category' => ['sometimes', 'required', Rule::in(ReportData::CATEGORIES)],
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => array_merge(['sometimes', 'required', 'date_format:Y-m-d'], $this->exists('from') ? ['after_or_equal:from'] : []),
        ]);
    }

    public function filters(): ReportFilterData
    {
        $data = $this->validated();

        return new ReportFilterData($this->pageData(), $data['status'] ?? null, $data['category'] ?? null, $data['from'] ?? null, $data['to'] ?? null);
    }
}
