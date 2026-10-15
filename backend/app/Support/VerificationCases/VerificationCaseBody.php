<?php

declare(strict_types=1);

namespace App\Support\VerificationCases;

use App\Rules\NoLikelySecret;
use App\Rules\TrimmedMinimumLength;
use Illuminate\Contracts\Validation\ValidationRule;

// BV202 — longueurs tirées littéralement de CAHIER_DES_CHARGES.md:1631.
// Cas documentaire sans exécution : aucun champ code, command, url, chemin
// n'est accepté. Les retours HTML passent par la Resource, pas par ce corps.
final class VerificationCaseBody
{
    public const TITLE_MIN = 15;

    public const TITLE_MAX = 140;

    public const CONTEXT_MIN = 20;

    public const CONTEXT_MAX = 2000;

    public const STEPS_MIN = 20;

    public const STEPS_MAX = 4000;

    public const EXPECTED_MIN = 10;

    public const EXPECTED_MAX = 2000;

    public const OBSERVED_MAX = 4000;

    public const ENVIRONMENT_MAX = 500;

    /** @var list<string> */
    public const REQUIRED_FIELDS = ['title', 'context', 'steps', 'expected'];

    /** @var list<string> */
    public const OPTIONAL_FIELDS = ['observed', 'environment'];

    /** @var list<string> */
    public const FORBIDDEN_FIELDS = ['code', 'command', 'url', 'path'];

    /** @return array<string, array<int, string|ValidationRule>> */
    public static function rules(): array
    {
        $noControl = 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

        // Pas de contrainte `array:...` sur `body` : les clés inconnues et
        // interdites sont levées dans le FormRequest::withValidator() pour que
        // chaque champ fautif reçoive sa propre erreur 422.
        return [
            'body' => ['required', 'array'],
            'body.title' => ['required', 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX, new TrimmedMinimumLength(self::TITLE_MIN), $noControl, new NoLikelySecret],
            'body.context' => ['required', 'string', 'min:'.self::CONTEXT_MIN, 'max:'.self::CONTEXT_MAX, new TrimmedMinimumLength(self::CONTEXT_MIN), $noControl, new NoLikelySecret],
            'body.steps' => ['required', 'string', 'min:'.self::STEPS_MIN, 'max:'.self::STEPS_MAX, new TrimmedMinimumLength(self::STEPS_MIN), $noControl, new NoLikelySecret],
            'body.expected' => ['required', 'string', 'min:'.self::EXPECTED_MIN, 'max:'.self::EXPECTED_MAX, new TrimmedMinimumLength(self::EXPECTED_MIN), $noControl, new NoLikelySecret],
            'body.observed' => ['nullable', 'string', 'max:'.self::OBSERVED_MAX, $noControl, new NoLikelySecret],
            'body.environment' => ['nullable', 'string', 'max:'.self::ENVIRONMENT_MAX, $noControl, new NoLikelySecret],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string}
     */
    public static function normalize(array $raw): array
    {
        return [
            'title' => (string) $raw['title'],
            'context' => (string) $raw['context'],
            'steps' => (string) $raw['steps'],
            'expected' => (string) $raw['expected'],
            'observed' => isset($raw['observed']) && $raw['observed'] !== '' ? (string) $raw['observed'] : null,
            'environment' => isset($raw['environment']) && $raw['environment'] !== '' ? (string) $raw['environment'] : null,
        ];
    }

    /** @param array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string} $body */
    public static function digest(array $body): string
    {
        $canonical = [
            'title' => $body['title'],
            'context' => $body['context'],
            'steps' => $body['steps'],
            'expected' => $body['expected'],
            'observed' => $body['observed'],
            'environment' => $body['environment'],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
