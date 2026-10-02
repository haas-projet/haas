<?php

namespace Tests\Integration;

use App\Http\Requests\PaginatedRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\PostgresTestCase;
use Tests\Support\UserSummaryCollection;

final class PaginationDatabaseTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_sql_pagination_uses_validated_limits_and_explicit_resources(): void
    {
        User::factory()->count(23)->create();
        Route::get('/api/v1/test-sql-pagination', function (PaginatedRequest $request): UserSummaryCollection {
            $page = $request->pageData();

            return new UserSummaryCollection(User::query()->orderBy('id')->paginate($page->perPage, ['*'], 'page', $page->page));
        });

        $first = $this->get('/api/v1/test-sql-pagination')->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 20, 'to' => 20, 'total' => 23])
            ->assertExactJsonStructure(['data' => ['*' => ['id', 'handle']], 'meta' => ['current_page', 'per_page', 'last_page', 'total', 'from', 'to']]);
        $second = $this->get('/api/v1/test-sql-pagination?page=2')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.from', 21)->assertJsonPath('meta.to', 23);
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->get('/api/v1/test-sql-pagination?page=3')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null)->assertJsonPath('meta.total', 23);
        User::query()->delete();
        $this->get('/api/v1/test-sql-pagination')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.total', 0);
    }
}
