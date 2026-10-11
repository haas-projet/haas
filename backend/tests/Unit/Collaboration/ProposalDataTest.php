<?php

namespace Tests\Unit\Collaboration;

use App\Data\Collaboration\ProposalData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProposalDataTest extends TestCase
{
    public function test_creation_requires_all_four_descriptions_and_preserves_optional_code(): void
    {
        $code = " \n  <script>texte fictif</script>\n ";
        $content = ProposalContentTest::content() + ['code' => $code];
        $data = new ProposalData($content);
        $this->assertSame(['changes' => $content, 'lock_version' => null], $data->payload());
        $this->assertSame('', (new ProposalData(ProposalContentTest::content() + ['code' => '']))->changes['code']);
        $this->assertNull((new ProposalData(ProposalContentTest::content() + ['code' => null]))->changes['code']);
    }

    public function test_descriptions_are_trimmed_consistently_without_changing_code(): void
    {
        $content = ProposalContentTest::content();
        $padded = array_map(static fn (string $text): string => "\u{00A0} \n".$text."\n \u{202F}", $content);
        $code = " \n code fictif \n ";
        $data = new ProposalData($padded + ['code' => $code]);
        $this->assertSame($content + ['code' => $code], $data->changes);
    }

    public function test_partial_edit_keeps_only_supplied_fields_and_requires_a_current_integer_version(): void
    {
        $data = new ProposalData(['limits' => 'Limites entièrement fictives.'], 7);
        $this->assertSame(['changes' => ['limits' => 'Limites entièrement fictives.'], 'lock_version' => 7], $data->payload());
        $this->assertSame(['code' => null], (new ProposalData(['code' => null], 1))->changes);
    }

    /** @param array<array-key, mixed> $changes */
    #[DataProvider('invalidChanges')]
    public function test_invalid_keys_types_or_content_never_cross_the_dto_boundary(array $changes, mixed $version): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProposalData($changes, $version);
    }

    /** @return iterable<string, array{array<array-key, mixed>, mixed}> */
    public static function invalidChanges(): iterable
    {
        yield 'empty creation' => [[], null];
        yield 'empty edit' => [[], 1];
        yield 'incomplete creation' => [['diagnosis' => 'Diagnostic entièrement fictif.'], null];
        foreach (['author_id', 'request_id', 'state', 'lock_version', 'hidden_at', 'edited_at', 'created_at', 'diagnosis_html'] as $field) {
            yield 'protected '.$field => [ProposalContentTest::content() + [$field => null], null];
        }
        foreach ([null, 7, false, ['texte'], (object) ['texte' => 'fictif']] as $index => $value) {
            yield 'description type '.$index => [array_replace(ProposalContentTest::content(), ['fix' => $value]), null];
        }
        foreach (ProposalContentTest::invalidDescriptions() as $name => [$value]) {
            yield 'description '.$name => [array_replace(ProposalContentTest::content(), ['fix' => $value]), null];
        }
        foreach (ProposalContentTest::invalidCodes() as $name => [$value]) {
            yield 'code '.$name => [ProposalContentTest::content() + ['code' => $value], null];
        }
        foreach ([0, -1, 2147483647, '1', '01', 1.0, true, false, [], 'fictif'] as $index => $version) {
            yield 'version '.$index => [['diagnosis' => 'Diagnostic entièrement fictif.'], $version];
        }
    }
}
