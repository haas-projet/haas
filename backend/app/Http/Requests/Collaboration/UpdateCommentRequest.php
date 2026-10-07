<?php

namespace App\Http\Requests\Collaboration;

use App\Data\Collaboration\CommentData;
use App\Queries\Collaboration\VisibleCommentsQuery;

final class UpdateCommentRequest extends StoreCommentRequest
{
    public function authorize(): bool
    {
        return $this->member()->can('update', app(VisibleCommentsQuery::class)->get($this->member(), $this->targetId()));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'lock_version' => ['required', 'integer', 'min:1', 'max:2147483646']];
    }

    public function commentData(): CommentData
    {
        return new CommentData($this->validated('body'), (int) $this->validated('lock_version'));
    }
}
