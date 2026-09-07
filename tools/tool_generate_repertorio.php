<?php

declare(strict_types=1);

/**
 * Genera repertorio.html a partir de config/site.json.
 *
 * Uso:
 *   php tool_generate_repertorio.php
 *   php tool_generate_repertorio.php --force
 */

$root = dirname(__DIR__);
$inputPath = $root . '/config/site.json';
$outputPath = $root . '/repertorio.html';

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

validateSiteData($data);

$html = createRepertoryPage($data);

LibCode::writeIfChanged($outputPath, $html, $force, false);

function validateSiteData(mixed $data): void
{
    if (!is_array($data)) {
        throw new InvalidArgumentException('La raiz del JSON debe ser un objeto.');
    }

    foreach (['site_title', 'repertorio', 'songs'] as $field) {
        if (!array_key_exists($field, $data)) {
            throw new InvalidArgumentException("Falta el campo requerido: {$field}");
        }
    }

    if (!is_string($data['site_title'])) {
        throw new InvalidArgumentException('site_title debe ser un texto.');
    }

    if (!is_array($data['repertorio'])) {
        throw new InvalidArgumentException('repertorio debe ser un objeto.');
    }

    foreach (['title', 'subtitle'] as $field) {
        if (!array_key_exists($field, $data['repertorio'])) {
            throw new InvalidArgumentException("Falta {$field} en repertorio.");
        }
        if (!is_string($data['repertorio'][$field])) {
            throw new InvalidArgumentException("{$field} de repertorio no es un texto.");
        }
    }

    if (!is_array($data['songs'])) {
        throw new InvalidArgumentException('songs debe ser una lista.');
    }

    foreach ($data['songs'] as $index => $song) {
        if (!is_array($song)) {
            throw new InvalidArgumentException("La cancion {$index} debe ser un objeto.");
        }

        foreach (['slug', 'title', 'folder', 'partitura'] as $field) {
            if (!array_key_exists($field, $song)) {
                throw new InvalidArgumentException("Falta {$field} en la cancion {$index}.");
            }

            if (!is_string($song[$field])) {
                throw new InvalidArgumentException("{$field} de la cancion {$index} no es un texto.");
            }
        }
    }
}

function createRepertoryPage(array $data): string
{
    $brand = $data['site_title'];
    $pageTitle = $data['repertorio']['title'];
    $pageSubtitle = $data['repertorio']['subtitle'];
    $cards = '';

    foreach ($data['songs'] as $song) {
        $number = LibCode::escape(str_pad((string) ((int) array_search($song, $data['songs'], true) + 1), 2, '0', STR_PAD_LEFT));
        $title = LibCode::escape($song['title']);
        $vocesUrl = $song['folder'] . '/voces.html';
        $partituraUrl = $song['partitura'];

        $cards .= <<<HTML
            <article class="song-card">
                <a href="{$vocesUrl}" class="song-card-main">
                    <span class="song-card-number">{$number}</span>
                    <strong>{$title}</strong>
                    <span>Explorar voces <span aria-hidden="true">→</span></span>
                </a>
                <a href="{$partituraUrl}" class="song-card-sheet" title="Partitura completa" aria-label="Partitura completa">
                    <span aria-hidden="true">🎼</span>
                    <span>Partitura</span>
                </a>
            </article>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle} - {$brand}</title>
    <link rel="stylesheet" href="css.css">
</head>
<body>
    <nav>
        <a href="index.html" class="logo">
            <span>🎼</span> {$brand}
        </a>
        <ul>
            <li><a href="ayuda.html" class="help-link">AYUDA</a></li>
        </ul>
    </nav>

    <main class="selection-page repertory-page">
        <header class="selection-intro">
            <p class="selection-kicker">{$brand}</p>
            <h1>{$pageTitle}</h1>
            <p>{$pageSubtitle}</p>
        </header>

        <section class="song-grid" aria-label="Canciones disponibles">
{$cards}
        </section>
    </main>
</body>
</html>
HTML;
}