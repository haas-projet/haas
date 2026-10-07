<?php

namespace App\Data\HelpRequests;

use App\Enums\HelpRequests\HelpIntent;
use App\Enums\HelpRequests\SubmissionMode;
use InvalidArgumentException;

final readonly class CreateHelpRequestData
{
    /**
     * @param  array{title: string, goal: ?string, expected: ?string, observed: ?string, attempts: ?string, environment: ?string, code: ?string, code_language: ?string, primary_language: string, reproduction_url: ?string}  $content
     * @param  list<array{id: string, version_label: ?string}>  $technologies
     */
    public function __construct(public SubmissionMode $mode, public HelpIntent $helpIntent, public array $content, public array $technologies)
    {
        if (array_diff(array_keys($content), ['title', 'goal', 'expected', 'observed', 'attempts', 'environment', 'code', 'code_language', 'primary_language', 'reproduction_url']) !== []
            || count($technologies) > 5 || count(array_unique(array_column($technologies, 'id'))) !== count($technologies)) {
            throw new InvalidArgumentException('Création de demande hors contrat.');
        }
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $technologies = $this->technologies;
        usort($technologies, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return ['mode' => $this->mode->value, 'help_intent' => $this->helpIntent->value, 'content' => $this->content, 'technologies' => $technologies];
    }
}
