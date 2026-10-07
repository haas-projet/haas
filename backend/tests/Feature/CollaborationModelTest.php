<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CollaborationModelTest extends TestCase
{
    /** @param class-string<Model> $model */
    #[DataProvider('protectedFields')]
    public function test_server_owned_fields_cannot_be_mass_assigned(string $model, string $field): void
    {
        $this->expectException(MassAssignmentException::class);
        (new $model)->fill([$field => null]);
    }

    /** @return iterable<string, array{class-string<Model>, string}> */
    public static function protectedFields(): iterable
    {
        $fields = [
            HelpRequest::class => ['author_id', 'state', 'lock_version'],
            Comment::class => ['request_id', 'author_id', 'lock_version', 'edited_at', 'hidden_at'],
            Proposal::class => ['request_id', 'author_id', 'state', 'lock_version'],
            Resolution::class => ['request_id', 'proposal_id', 'accepted_by', 'accepted_at', 'revoked_at'],
        ];

        foreach ($fields as $model => $protected) {
            foreach (['id', 'created_at', 'updated_at', ...$protected] as $field) {
                yield $model.'.'.$field => [$model, $field];
            }
        }
    }
}
