<?php

namespace App\Queries\Collaboration;

use App\Data\Common\PageData;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final class CommentQuery
{
    public function __construct(private readonly VisibleCommentsQuery $visible, private readonly FindVisibleHelpRequestQuery $parents) {}

    /** @return LengthAwarePaginator<int, Comment> */
    public function page(?User $actor, string $id, PageData $page): LengthAwarePaginator
    {
        $parent = $this->parents->get($actor, strtolower($id));

        return $this->visible->forActor($actor)->where('request_id', $parent->id)->with('author:id,handle')->orderBy('created_at')->orderBy('id')->paginate($page->perPage, ['*'], 'page', $page->page);
    }

    /** @return LengthAwarePaginator<int, CommentRevision> */
    public function revisions(?User $actor, string $id, PageData $page): LengthAwarePaginator
    {
        $comment = $this->visible->get($actor, $id);

        return CommentRevision::where('comment_id', $comment->id)->orderByDesc('comment_version')->paginate($page->perPage, ['*'], 'page', $page->page);
    }
}
