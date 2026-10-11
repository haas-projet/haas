<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;

final class ProposalText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $fail('Le texte doit utiliser un encodage UTF-8 valide.');

            return;
        }
        $text = self::trim($value);
        $useful = preg_replace('/\p{Cf}/u', '', $text) ?? $text;
        if (mb_strlen($useful, 'UTF-8') < 20 || mb_strlen($text, 'UTF-8') > 4000 || $this->onlyLinks($text)) {
            $fail('Décrivez votre apport en 20 à 4 000 caractères utiles ; un lien seul ne suffit pas.');
        }
    }

    public static function trim(string $value): string
    {
        // Trim only the descriptions, never the optional code or its indentation.
        return preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $value) ?? $value;
    }

    private function onlyLinks(string $text): bool
    {
        // Parse the same Markdown grammar as the inert renderer, without fetching any URL.
        // Labels, reference definitions and numbered-list markers are not an explanation.
        $environment = new Environment(['max_nesting_level' => 20, 'max_delimiters_per_line' => 100]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $walker = (new MarkdownParser($environment))->parse($text)->walker();
        $remaining = '';
        $links = 0;
        while ($event = $walker->next()) {
            if (! $event->isEntering()) {
                continue;
            }
            $node = $event->getNode();
            if ($node instanceof Link || $node instanceof Image) {
                $links++;
                $walker->resumeAt($node, false);
            } elseif ($node instanceof StringContainerInterface) {
                $remaining .= $node->getLiteral()."\n";
            }
        }
        $remaining = preg_replace('~(?:[a-z][a-z0-9+.-]*://|www\.)[^\s<>]+~iu', '', $remaining, -1, $bareLinks);

        return ($links + $bareLinks > 0 || preg_match('~[a-z][a-z0-9+.-]*://~iu', $text) === 1)
            && $remaining !== null && preg_match('/\A[\s\p{Z}\p{P}\p{S}\p{Cf}]*\z/u', $remaining) === 1;
    }
}
