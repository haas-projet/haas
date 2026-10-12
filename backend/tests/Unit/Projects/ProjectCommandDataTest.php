<?php

namespace Tests\Unit\Projects;

use App\Data\Projects\CreateProjectData;
use App\Data\Projects\UpdateProjectData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProjectCommandDataTest extends TestCase
{
    public function test_minimal_draft_and_semantically_identical_input_have_the_same_payload(): void
    {
        $draft = new CreateProjectData;
        $this->assertSame((new CreateProjectData(['name' => null, 'project_stage' => 'idea']))->payload(), $draft->payload());
        $this->assertNull($draft->attributes['description']);
        $this->assertSame('idea', $draft->attributes['project_stage']);
        $this->assertSame([], $draft->technologyIds);
    }

    public function test_canonical_text_and_technology_order_do_not_change_the_intention(): void
    {
        $ids = ['12345678-1234-4234-8234-123456789abc', 'abcdef12-1234-4234-8234-123456789abc'];
        $a = new CreateProjectData(['name' => "\u{00a0}Projet pédagogique\u{200b}"], $ids);
        $b = new CreateProjectData(['name' => 'Projet pédagogique'], array_map(strtoupper(...), array_reverse($ids)));
        $this->assertSame($a->payload(), $b->payload());
        $this->assertSame('Projet pédagogique', $a->attributes['name']);
        $patch = new UpdateProjectData(1, ['help_sought' => null], []);
        $this->assertSame(['help_sought' => null], $patch->attributes);
        $this->assertSame([], $patch->technologyIds);
    }

    /** @param array<string, mixed> $attributes
     * @param  list<string>  $ids
     */
    #[DataProvider('invalidCreation')]
    public function test_dto_rejects_untrusted_fields_and_invalid_content(array $attributes, array $ids = []): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CreateProjectData($attributes, $ids);
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function invalidCreation(): iterable
    {
        foreach (['owner_id', 'id', 'publication_state', 'published_at', 'hidden_at', 'moderation_reason', 'lock_version'] as $field) {
            yield $field => [[$field => 'client'], []];
        }
        yield 'blank name' => [['name' => " \u{200b} "], []];
        yield 'short name' => [['name' => 'AB'], []];
        yield 'long name' => [['name' => str_repeat('é', 81)], []];
        yield 'short summary' => [['summary' => 'Court'], []];
        yield 'short description' => [['description' => 'Description trop courte.'], []];
        yield 'unknown stage' => [['project_stage' => 'certified'], []];
        yield 'null stage' => [['project_stage' => null], []];
        yield 'control before trim' => [['name' => "\0Projet de test"], []];
        yield 'multiline name' => [['name' => "Projet\ndocumentaire"], []];
        yield 'http link' => [['demo_url' => 'http://example.test/demo'], []];
        yield 'credentials link' => [['repository_url' => 'https://member:fictional@example.test/project'], []];
        yield 'url control' => [['demo_url' => "https://example.test/\n"], []];
        yield 'url unicode space' => [['demo_url' => "https://example.test/\u{00a0}"], []];
        yield 'duplicate technology' => [[], ['abcdef12-1234-4234-8234-123456789abc', 'ABCDEF12-1234-4234-8234-123456789ABC']];
        yield 'unknown uuid' => [[], ['not-a-uuid']];
    }

    #[DataProvider('invalidUpdates')]
    public function test_update_requires_a_bounded_version_and_an_actual_field(int $version, bool $withChange): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpdateProjectData($version, $withChange ? ['name' => 'Projet documentaire'] : []);
    }

    /** @return iterable<string, array{int, bool}> */
    public static function invalidUpdates(): iterable
    {
        yield 'zero' => [0, true];
        yield 'last integer' => [2147483647, true];
        yield 'overflow' => [2147483648, true];
        yield 'no change' => [1, false];
    }
}
