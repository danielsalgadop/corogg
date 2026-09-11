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

require_once $root . '/lib_code.php';

try {
    [$args, $force] = LibCode::parseToolArgs(array_slice($argv, 1), []);
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

function createHelpPage(array $data): string
{
    $title = LibCode::escape($data['title']);
    $intro = LibCode::escape($data['intro']);
    $sections = '';

    foreach ($data['sections'] as $section) {
        $number = LibCode::escape((string) $section['number']);
        $sectionTitle = LibCode::escape($section['title']);
        $text = LibCode::escape($section['text']);
        $videoUrl = LibCode::escape($section['video']['url']);
        $videoTitle = LibCode::escape($section['video']['title']);
        $ctaHtml = '';
        if (isset($section['cta'])) {
            $ctaLabel = LibCode::escape($section['cta']['label']);
            $ctaHref = LibCode::escape($section['cta']['href']);
            $ctaHtml = <<<HTML
            <div style="margin-top:1rem;">
                <a href="{$ctaHref}" class="btn-principal" style="display:inline-block;text-decoration:none;">{$ctaLabel}</a>
            </div>
HTML;
        }

        $sections .= <<<HTML


        <section class="help-section">
            <h2>{$number}. {$sectionTitle}</h2>
            <p>{$text}</p>{$ctaHtml}
            <div class="help-video">
                <div class="video-frame">
                    <iframe src="{$videoUrl}" title="{$videoTitle}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
            </div>
        </section>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Ecos del Atlántico</title>
    <link rel="stylesheet" href="css.css">
</head>
<body>
    <nav>
        <a href="index.html" class="logo">
            <span>🎼</span> Ecos del Atlántico
        </a>
    </nav>

    <main class="help-content">
        <header class="help-intro">
            <h1>{$title}</h1>
            <p>{$intro}</p>
        </header>{$sections}
    </main>
</body>
</html>
HTML;
}