<?php

declare(strict_types=1);

/**
 * Genera ayuda.html a partir de config/ayuda.json.
 *
 * Uso:
 *   php tool_generate_ayuda.php
 *   php tool_generate_ayuda.php --force
 */

$root = dirname(__DIR__);
$inputPath = $root . '/config/ayuda.json';
$outputPath = $root . '/ayuda.html';

require_once $root . '/tools/libs/lib_code.php';
require_once $root . '/tools/libs/html.php';

try {
    [$args, $force] = LibCode::parseToolArgs(array_slice($argv, 1), ['lang' => 'value']);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if (!is_file($inputPath)) {
    fwrite(STDERR, "No existe el archivo de entrada: {$inputPath}\n");
    exit(1);
}

try {
    $data = json_decode(
        (string) file_get_contents($inputPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $exception) {
    fwrite(STDERR, "JSON invalido en {$inputPath}: {$exception->getMessage()}\n");
    exit(1);
}

validateHelpData($data);

$lang = is_string($args['lang'] ?? null) && $args['lang'] !== '' ? (string) $args['lang'] : 'es';
LibCode::setLanguage($root, $lang);

$html = createHelpPage($data);

LibCode::writeIfChanged($outputPath, $html, $force, false);

function validateHelpData(mixed $data): void
{
    if (!is_array($data)) {
        throw new InvalidArgumentException('La raiz del JSON debe ser un objeto.');
    }

    foreach (['title', 'intro', 'sections'] as $field) {
        if (!array_key_exists($field, $data)) {
            throw new InvalidArgumentException("Falta el campo requerido: {$field}");
        }
    }

    if (!is_string($data['title']) || !is_string($data['intro']) || !is_array($data['sections'])) {
        throw new InvalidArgumentException('title, intro y sections tienen un formato invalido.');
    }

    foreach ($data['sections'] as $index => $section) {
        if (!is_array($section)) {
            throw new InvalidArgumentException("La seccion {$index} debe ser un objeto.");
        }

        foreach (['number', 'title', 'text', 'video'] as $field) {
            if (!array_key_exists($field, $section)) {
                throw new InvalidArgumentException("Falta {$field} en la seccion {$index}.");
            }
        }

        if (!is_array($section['video'])
            || !isset($section['video']['url'], $section['video']['title'])
            || !is_string($section['video']['url'])
            || !is_string($section['video']['title'])) {
            throw new InvalidArgumentException("El video de la seccion {$index} tiene un formato invalido.");
        }

        if (isset($section['cta'])) {
            if (!is_array($section['cta'])
                || !isset($section['cta']['label'], $section['cta']['href'])
                || !is_string($section['cta']['label'])
                || !is_string($section['cta']['href'])
                || $section['cta']['label'] === ''
                || $section['cta']['href'] === '') {
                throw new InvalidArgumentException("El cta de la seccion {$index} tiene un formato invalido.");
            }
        }
    }
}
