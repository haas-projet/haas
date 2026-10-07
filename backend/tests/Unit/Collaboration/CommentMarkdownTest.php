<?php

namespace Tests\Unit\Collaboration;

use App\Support\Collaboration\CommentMarkdown;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommentMarkdownTest extends TestCase
{
    #[DataProvider('unsafeContent')]
    public function test_untrusted_content_never_produces_active_html_or_external_images(string $source): void
    {
        $html = (new CommentMarkdown)->render($source);
        $this->assertDoesNotMatchRegularExpression('/<(script|iframe|img|svg|object|embed|style|input)\b/i', $html);
        $document = new DOMDocument;
        $document->loadHTML('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $attributes = (new DOMXPath($document))->query('//@*');
        $this->assertNotFalse($attributes);
        foreach ($attributes as $attribute) {
            $this->assertDoesNotMatchRegularExpression('/^(?:on\w+|src|style|href)$/i', $attribute->nodeName);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeContent(): iterable
    {
        foreach (['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '<svg onload=alert(1)>', '[clic](javascript:alert%281%29)', '[clic](java&#x73;cript:alert%281%29)', '[clic](data:text/html,test)', '[clic](http://example.test)', '[clic](https://user:pass@example.test)', '![image](https://example.test/pixel)', '<https://user@example.test>', '[clic](//example.test)', "```html\n<script>texte</script>\n```", '    <iframe src="https://example.test"></iframe>'] as $i => $source) {
            yield (string) $i => [$source];
        }
    }

    public function test_allowed_markdown_and_https_links_have_a_bounded_safe_rendering(): void
    {
        $html = (new CommentMarkdown)->render("**Fort** et *doux*, `code`\n\n- Une liste\n\n[Documentation](https://example.test/page?a=1&b=2)");
        foreach (['<strong>Fort</strong>', '<em>doux</em>', '<code>code</code>', '<li>Une liste</li>', 'href="https://example.test/page?a=1&amp;b=2"', 'rel="nofollow noopener noreferrer"'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->assertNotEmpty((new CommentMarkdown)->render(str_repeat('> ', 200).'Texte '.str_repeat('_', 3000)));
    }
}
