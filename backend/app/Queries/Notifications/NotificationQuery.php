<?php

namespace App\Queries\Notifications;

use App\Data\Common\PageData;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Models\InternalNotification;
use App\Models\User;
use App\Queries\Collaboration\VisibleCommentsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class NotificationQuery
{
    public function __construct(private readonly VisibleCommentsQuery $comments) {}

    /** @return LengthAwarePaginator<int, InternalNotification> */
    public function page(User $actor, PageData $page): LengthAwarePaginator
    {
        return $this->visible($actor)->orderByDesc('created_at')->orderByDesc('id')->paginate($page->perPage, ['*'], 'page', $page->page);
    }

    public function unread(User $actor): int
    {
        return $this->visible($actor)->whereNull('read_at')->count();
    }

    /** @return Builder<InternalNotification> */
    public function visible(User $actor): Builder
    {
        $current = User::findOrFail($actor->id);
        Gate::forUser($current)->authorize('view', $current);

        // Une notification de modération ne copie aucun contenu ni lien public retiré.
        return InternalNotification::where('recipient_id', $current->id)->with(['comment:id,request_id', 'capsuleReview:id,version_id', 'capsuleReview.version:id,capsule_id'])->where(function (Builder $query) use ($current): void {
            $query->where('kind', 'profile.moderated')
                ->orWhere(fn (Builder $comments) => $comments->where('kind', 'comment.created')->whereIn('event_id', $this->comments->forActor($current)->select('comments.id')))
                ->orWhere(fn (Builder $reviews) => $reviews->where('kind', 'capsule.review.changes_requested')->whereIn('event_id',
                    DB::table('capsule_version_reviews')->join('capsule_versions', 'capsule_version_reviews.version_id', '=', 'capsule_versions.id')
                        ->join('capsules', 'capsule_versions.capsule_id', '=', 'capsules.id')
                        ->join('users AS capsule_owner', 'capsules.owner_id', '=', 'capsule_owner.id')
                        ->where('capsules.owner_id', $current->id)->where('capsules.visibility', CapsuleVisibility::Visible->value)
                        ->where('capsule_owner.status', AccountStatus::Active->value)->whereNotNull('capsule_owner.email_verified_at')
                        ->select('capsule_version_reviews.id')));
        });
    }
}
