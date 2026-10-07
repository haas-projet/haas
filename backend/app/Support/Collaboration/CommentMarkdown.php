<?php

namespace App\Support\Collaboration;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\MarkdownConverter;

final class CommentMarkdown
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment(['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20, 'max_delimiters_per_line' => 100]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addRenderer(Link::class, new CommentLinkRenderer, 10);
        $environment->addRenderer(Image::class, new CommentLinkRenderer, 10);
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $body): string
    {
        return (string) $this->converter->convert($body);
    }
}
