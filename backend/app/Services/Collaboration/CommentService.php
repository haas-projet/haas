<?php

namespace App\Services\Collaboration;

use App\Data\Collaboration\CommentData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Notifications\NotificationEvent;
use App\Exceptions\Collaboration\CommentVersionConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\HelpRequest;
use App\Models\User;
use App\Queries\Collaboration\VisibleCommentsQuery;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Notifications\NotificationOutbox;
use App\Support\Collaboration\CommentContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CommentService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly AuditWriter $audit,
        private readonly NotificationOutbox $notifications, private readonly VisibleCommentsQuery $visible, private readonly FindVisibleHelpRequestQuery $parents) {}

    public function create(User $actor, string $parentId, CommentData $data, IdempotencyKey $key): Comment
    {
        if ($data->lockVersion !== null) {
            throw new InvalidArgumentException('La création ne reçoit pas de version.');
        }

        return $this->execute($actor, strtolower($parentId), null, $data, $key);
    }

    public function update(User $actor, string $id, CommentData $data, IdempotencyKey $key): Comment
    {
        if ($data->lockVersion === null) {
            throw new InvalidArgumentException('Version requise pour modifier un commentaire.');
        }
        $comment = $this->visible->get($actor, strtolower($id));

        return $this->execute($actor, $comment->request_id, $comment->id, $data, $key);
    }

    private function execute(User $actor, string $parentId, ?string $id, CommentData $data, IdempotencyKey $key): Comment
    {
        $parent = $this->parents->get($actor, $parentId);

        // Verrouiller les deux comptes dans un ordre global avant le socle acteur, puis parent et commentaire.
        return DB::transaction(function () use ($actor, $parent, $id, $data, $key): Comment {
            User::whereIn('id', [$actor->id, $parent->author_id])->orderBy('id')->lockForUpdate()->get();
            $route = $id === null ? 'POST /api/v1/requests/'.$parent->id.'/comments' : 'PATCH /api/v1/comments/'.$id;

            return $this->idempotency->execute($actor, new IdempotencyData($route, $key, $data->payload()),
                function (User $current) use ($parent, $id): void {
                    $this->authorizeLocked($current, $parent->id, $id);
                },
                function (User $current) use ($parent, $id, $data): StoredCommandResult {
                    $this->authorizeLocked($current, $parent->id, $id);
                    CommentContent::validate($data->body);
                    if ($id === null) {
                        $comment = new Comment;
                        $comment->forceFill(['request_id' => $parent->id, 'author_id' => $current->id, 'body' => $data->body])->save();
                    } else {
                        $comment = Comment::whereKey($id)->lockForUpdate()->firstOrFail();
                        if ($comment->lock_version !== $data->lockVersion) {
                            throw new CommentVersionConflict;
                        }
                        if (! CommentRevision::where('comment_id', $id)->exists()) {
                            $this->revision($comment, 'before_edit');
                        }
                        $comment->body = $data->body;
                        $comment->lock_version++;
                        $comment->edited_at = now()->utc()->toImmutable();
                        $comment->save();
                    }
                    $this->revision($comment, $id === null ? 'created' : 'updated');
                    $this->audit->commentChanged($current, $comment, $id === null);
                    if ($id === null && $current->id !== $parent->author_id) {
                        $this->notifications->record(new NotificationEvent($comment->id, $parent->author_id, 'comment.created'));
                    }

                    return new StoredCommandResult($id === null ? 201 : 200, ['comment_id' => $comment->id], $comment->lock_version);
                },
                function (User $current, StoredCommandResult $result) use ($parent, $id): Comment {
                    if ($result->status !== ($id === null ? 201 : 200) || array_keys($result->references) !== ['comment_id'] || ($id !== null && $result->references['comment_id'] !== $id)) {
                        throw new IdempotencyStorageFailed('Résultat de commentaire mémorisé invalide.');
                    }
                    $comment = $this->visible->get($current, $result->references['comment_id']);
                    if ($comment->author_id !== $current->id || $comment->request_id !== $parent->id) {
                        throw new IdempotencyStorageFailed('Référence de commentaire mémorisée invalide.');
                    }
                    Gate::forUser($current)->authorize('update', $comment);
                    if ($comment->lock_version !== $result->version) {
                        throw new CommentVersionConflict;
                    }

                    return $comment;
                });
        });
    }

    private function authorizeLocked(User $actor, string $parentId, ?string $id): void
    {
        $parent = HelpRequest::whereKey($parentId)->lockForUpdate()->firstOrFail();
        $this->parents->get($actor, $parentId);
        Gate::forUser($actor)->authorize('create', [Comment::class, $parent]);
        if ($id !== null) {
            $comment = Comment::whereKey($id)->where('request_id', $parentId)->lockForUpdate()->firstOrFail();
            $this->visible->get($actor, $id);
            $comment->setRelation('request', $parent);
            Gate::forUser($actor)->authorize('update', $comment);
        }
    }

    private function revision(Comment $comment, string $action): void
    {
        $revision = new CommentRevision;
        $revision->forceFill(['comment_id' => $comment->id, 'comment_version' => $comment->lock_version, 'action' => $action, 'body' => $comment->body, 'occurred_at' => now()->utc()])->save();
    }
}
