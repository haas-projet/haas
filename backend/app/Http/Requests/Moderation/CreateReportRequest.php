<?php

namespace App\Http\Requests\Moderation;

use App\Data\Moderation\ReportData;
use App\Models\User;
use Illuminate\Validation\Rule;

final class CreateReportRequest extends ModerationRequest
{
    public function authorize(): bool
    {
        return $this->member()->can('participate', User::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'resource_type' => ['required', Rule::in(['profile'])], 'resource_id' => ['required', 'uuid'],
            'category' => ['required', Rule::in(ReportData::CATEGORIES)],
            'detail' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[\x00-\x1F\x7F]/u'],
        ];
    }

    public function reportData(): ReportData
    {
        $data = $this->validated();

        return new ReportData(strtolower($data['resource_id']), $data['category'], $data['detail']);
    }
}
