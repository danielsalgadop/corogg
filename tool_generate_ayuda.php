<?php

declare(strict_types=1);

/**
 * Genera ayuda.html a partir de ayuda.json.
 *
 * Uso:
 *   php tool_generate_ayuda.php
 *   php tool_generate_ayuda.php --force
 */

$root = __DIR__;
$inputPath = $root . '/ayuda.json';
$outputPath = $root . '/ayuda.html';
$force = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--force') {
        $force = true;
        continue;
    }

    fwrite(STDERR, "Opcion no reconocida: {$argument}\n");
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

if (is_file($outputPath) && !$force) {
    echo "Ya existe, no se modifica: {$outputPath}\n";
    echo "Usa --force para regenerarlo.\n";
    exit(0);
}

$html = createHelpPage($data);

if (file_put_contents($outputPath, $html) === false) {
    fwrite(STDERR, "No se pudo escribir {$outputPath}\n");
    exit(1);
}

echo "Generado: {$outputPath}\n";

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
    }
}

function createHelpPage(array $data): string
{
    $title = escapeHtml($data['title']);
    $intro = escapeHtml($data['intro']);
    $sections = '';

    foreach ($data['sections'] as $section) {
        $number = escapeHtml((string) $section['number']);
        $sectionTitle = escapeHtml($section['title']);
        $text = escapeHtml($section['text']);
        $videoUrl = escapeHtml($section['video']['url']);
        $videoTitle = escapeHtml($section['video']['title']);

        $sections .= <<<HTML


        <section class="help-section">
            <h2>{$number}. {$sectionTitle}</h2>
            <p>{$text}</p>
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

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
