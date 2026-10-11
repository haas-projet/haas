<?php

namespace Tests\Unit\Collaboration;

use App\Support\Collaboration\ProposalContent;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProposalContentTest extends TestCase
{
    #[DataProvider('invalidDescriptions')]
    public function test_each_description_rejects_content_outside_the_contract(string $value): void
    {
        foreach (['diagnosis', 'fix', 'verification', 'limits'] as $field) {
            $content = self::content();
            $content[$field] = $value;
            $validator = $this->factory()->make($content, ProposalContent::rules());
            $this->assertFalse($validator->passes(), $field);
            $this->assertTrue($validator->errors()->has($field), $field);
            $this->assertStringNotContainsString($value, json_encode($validator->errors()->toArray(), JSON_THROW_ON_ERROR));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDescriptions(): iterable
    {
        yield '19 Unicode characters' => [str_repeat('é', 19)];
        yield '4001 Unicode characters' => [str_repeat('é', 4001)];
        yield 'ASCII whitespace' => [str_repeat(' ', 25)];
        yield 'nonbreaking whitespace' => [str_repeat("\u{00A0}", 25)];
        yield 'Unicode separators' => [str_repeat("\u{2003}\u{202F}", 15)];
        yield 'invisible text' => [str_repeat("\u{200B}\u{FEFF}", 15)];
        yield 'word joiners' => [str_repeat("\u{2060}", 20)];
        yield 'invisible padding' => ['Court'.str_repeat("\u{2060}", 20)];
        yield 'bare URL' => ['https://example.test/documentation'];
        yield 'autolink' => ['<https://example.test/documentation>'];
        yield 'Markdown link' => ['[Documentation](https://example.test/documentation)'];
        yield 'URL list' => ["https://example.test/premier\nhttps://example.test/second"];
        yield 'numbered URL list' => ["1. https://example.test/premier\n2. https://example.test/second"];
        yield 'reference Markdown link' => ["[Documentation détaillée][ref]\n\n[ref]: https://example.test/docs"];
        yield 'inline title and parentheses' => ['[Documentation](https://example.test/docs(annexe) "Titre fictif")'];
        yield 'control' => ['Diagnostic fictif avec '."\x00".' contrôle'];
        yield 'invalid UTF-8' => [str_repeat('x', 21)."\xFF"];
        yield 'fictitious key' => ['api_key='.str_repeat('f', 32)];
        yield 'fictitious token' => ['ghp_'.str_repeat('f', 32)];
    }

    public function test_meaningful_unicode_lengths_are_consistent_after_trimming(): void
    {
        foreach ([20, 4000] as $length) {
            $content = array_fill_keys(['diagnosis', 'fix', 'verification', 'limits'], "\u{00A0} ".str_repeat('é', $length)." \u{202F}");
            $this->assertTrue($this->factory()->make($content, ProposalContent::rules())->passes());
        }
        $content = self::content();
        $content['verification'] = 'Lire la documentation et vérifier le résultat : https://example.test/documentation';
        $this->assertTrue($this->factory()->make($content, ProposalContent::rules())->passes());
        $content['diagnosis'] = "Diagnostic avec\u{200D} caractère de format interne conservé.";
        $validator = $this->factory()->make($content, ProposalContent::rules());
        $this->assertTrue($validator->passes());
        $this->assertSame($content['diagnosis'], $validator->validated()['diagnosis']);
    }

    #[DataProvider('invalidCodes')]
    public function test_optional_code_rejects_invalid_types_size_controls_and_known_secret_patterns(mixed $code): void
    {
        $content = self::content() + ['code' => $code];
        $validator = $this->factory()->make($content, ProposalContent::rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('code'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidCodes(): iterable
    {
        yield 'array' => [[]];
        yield 'integer' => [23];
        yield '12001 Unicode characters' => [str_repeat('é', 12001)];
        yield 'control' => ["texte\x00fictif"];
        yield 'invalid UTF-8' => ["texte\xFFfictif"];
        yield 'fictitious secret' => ['client_secret='.str_repeat('f', 32)];
    }

    public function test_optional_code_remains_inert_and_byte_identical(): void
    {
        foreach ([null, '', " \n  <script>texte fictif</script>\n  shell('jamais exécuté');\n ", str_repeat('é', 12000)] as $code) {
            $content = self::content() + ['code' => $code];
            $validator = $this->factory()->make($content, ProposalContent::rules());
            $this->assertTrue($validator->passes());
            $this->assertSame($code, $validator->validated()['code']);
        }
        $this->assertTrue($this->factory()->make(self::content(), ProposalContent::rules())->passes());
    }

    /** @return array<string, string> */
    public static function content(): array
    {
        return array_fill_keys(['diagnosis', 'fix', 'verification', 'limits'], 'Description entièrement fictive.');
    }

    private function factory(): Factory
    {
        return new Factory(new Translator(new ArrayLoader, 'fr'));
    }
}
