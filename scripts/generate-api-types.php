<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../backend/vendor/autoload.php';

function aliasName(string $file, string $name): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '_', pathinfo($file, PATHINFO_FILENAME).'_'.$name);
}

/** @param array<string, mixed>|bool $schema */
function schemaType(array|bool $schema, string $file): string
{
    if (is_bool($schema)) {
        return $schema ? 'unknown' : 'never';
    }
    if (isset($schema['$ref'])) {
        [$target, $pointer] = array_pad(explode('#', $schema['$ref'], 2), 2, '');
        if (! str_starts_with($pointer, '/components/schemas/') || str_contains($target, '://')) {
            throw new RuntimeException('Référence de type non prise en charge.');
        }

        return aliasName($target === '' ? $file : $target, substr($pointer, strlen('/components/schemas/')));
    }
    if (array_key_exists('const', $schema)) {
        return json_encode($schema['const'], JSON_THROW_ON_ERROR);
    }
    if (isset($schema['enum'])) {
        return implode(' | ', array_map(fn ($value) => json_encode($value, JSON_THROW_ON_ERROR), $schema['enum']));
    }
    foreach (['allOf' => ' & ', 'oneOf' => ' | ', 'anyOf' => ' | '] as $keyword => $separator) {
        if (isset($schema[$keyword])) {
            $parts = array_map(fn ($part) => schemaType($part, $file), $schema[$keyword]);
            $composed = '('.implode($separator, $parts).')';
            if (isset($schema['properties'])) {
                unset($schema[$keyword]);

                return $composed.' & '.schemaType($schema, $file);
            }

            return $composed;
        }
    }
    $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : null);
    if (is_array($type)) {
        return implode(' | ', array_map(fn ($part) => schemaType(array_replace($schema, ['type' => $part]), $file), $type));
    }
    if ($type === 'array') {
        return 'ReadonlyArray<'.schemaType($schema['items'] ?? true, $file).'>';
    }
    if ($type === 'object') {
        $fields = [];
        foreach ($schema['properties'] ?? [] as $name => $property) {
            $fields[] = 'readonly '.json_encode($name, JSON_THROW_ON_ERROR).(in_array($name, $schema['required'] ?? [], true) ? '' : '?').': '.schemaType($property, $file).';';
        }
        $additional = $schema['additionalProperties'] ?? true;
        if ($additional !== false) {
            // Un index unknown évite un conflit avec les propriétés nommées plus spécifiques.
            $fields[] = '[key: string]: unknown;';
        }

        return '{ '.implode(' ', $fields).' }';
    }

    return match ($type) {
        'string' => 'string', 'integer', 'number' => 'number', 'boolean' => 'boolean', 'null' => 'null', default => 'unknown'
    };
}

$root = dirname(__DIR__);
$files = [$root.'/docs/OPENAPI.yaml', ...glob($root.'/docs/api/openapi/*.yaml')];
$definitions = [];
foreach ($files as $file) {
    $document = Yaml::parseFile($file);
    foreach ($document['components']['schemas'] ?? [] as $name => $schema) {
        $alias = aliasName($file, $name);
        if (isset($definitions[$alias])) {
            throw new RuntimeException('Nom de type dupliqué.');
        }
        $definitions[$alias] = 'export type '.$alias.' = '.schemaType($schema, $file).';';
    }
}
ksort($definitions);
$content = "// Généré par scripts/generate-api-types.php ; ne pas modifier à la main.\n// Formes JSON uniquement : validation, formats, bornes et droits restent côté serveur.\n\n".implode("\n\n", $definitions)."\n";
$target = $root.'/docs/api/generated/haas-api.d.ts';
if (($argv[1] ?? '') === '--check') {
    if (! is_file($target) || file_get_contents($target) !== $content) {
        fwrite(STDERR, "Types API absents ou périmés.\n");
        exit(1);
    }
    echo count($definitions)." types API à jour.\n";
} else {
    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    file_put_contents($target, $content);
    echo count($definitions)." types API générés sans application frontend.\n";
}
