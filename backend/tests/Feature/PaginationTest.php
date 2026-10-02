<?php

namespace Tests\Feature;

use App\Http\Requests\PaginatedRequest;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/api/v1/test-pagination', function (PaginatedRequest $request): array {
            $page = $request->pageData();

            return ['page' => $page->page, 'per_page' => $page->perPage];
        });
    }

    public function test_defaults_and_maximum_are_accepted(): void
    {
        $this->get('/api/v1/test-pagination')->assertOk()->assertExactJson(['page' => 1, 'per_page' => 20]);
        $this->get('/api/v1/test-pagination?page=2&per_page=50')->assertOk()->assertExactJson(['page' => 2, 'per_page' => 50]);
        $this->get('/api/v1/test-pagination?page=2147483647&per_page=1')->assertOk()
            ->assertExactJson(['page' => 2147483647, 'per_page' => 1]);
    }

    #[DataProvider('invalidPages')]
    public function test_invalid_or_unknown_parameters_are_rejected(string $query, string $field): void
    {
        $this->get('/api/v1/test-pagination?'.$query)->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => [$field]]]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPages(): iterable
    {
        foreach (['0', '-1', '1.5', 'text', '', '2147483648', '999999999999999999999999', '%5B%5D'] as $value) {
            yield 'page '.$value => ['page='.$value, 'page'];
        }
        foreach (['0', '-1', '51', '1.5', 'text', '', 'true'] as $value) {
            yield 'size '.$value => ['per_page='.$value, 'per_page'];
        }
        yield 'array' => ['per_page[]=20', 'per_page'];
        yield 'unlisted sort' => ['sort=email', 'sort'];
        yield 'protected field' => ['author_id=someone-else', 'author_id'];
    }
}
