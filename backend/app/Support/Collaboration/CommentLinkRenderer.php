<?php

namespace App\Support\Collaboration;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class CommentLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string|HtmlElement
    {
        $label = $childRenderer->renderNodes($node->children());
        if (! $node instanceof Link) {
            return $label;
        }
        $url = $node->getUrl();
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return $label;
        }

        return new HtmlElement('a', ['href' => $url, 'rel' => 'nofollow noopener noreferrer'], $label);
    }
}
