<?php

namespace App\Http\Requests\Moderation;

use App\Data\Moderation\ReportDecisionData;
use Illuminate\Validation\Rule;

final class DecideReportRequest extends ModerationRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer:strict', 'min:0', 'max:2147483646'],
            'action' => ['required', Rule::in(ReportDecisionData::ACTIONS)],
            'reason' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'profile_lock_version' => ['required_if:action,hide', 'integer:strict', 'min:0', 'max:2147483646'],
        ];
    }

    public function decision(): ReportDecisionData
    {
        $data = $this->validated();

        return new ReportDecisionData($data['lock_version'], $data['action'], $data['reason'], $data['profile_lock_version'] ?? null);
    }
}
