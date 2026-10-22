<?php

declare(strict_types=1);

/**
 * Genera las paginas de voz para fichas con el formato:
 *
 * songs/cancion/fichaN/
 *   guitarraN.mp3
 *   vozV/
 *     guia_N_voz_V.m4a
 *     particella_N_vozV.(png|pdf)
 *     vozV.html
 *
 * Genera {$totalFichas} fichas y {$totalVoces} voces por ficha. Los recursos esperados son:
 *   songs/cancion/fichaN/guitarraN.mp3
 *   songs/cancion/fichaN/vozV/guia_N_voz_V.m4a
 *   songs/cancion/fichaN/vozV/particella_N_vozV.(png|pdf)
 *
 * Ademas genera los indices por voz (fichas_voz*.html) a partir de
 * config/site.json: voices[].slug/label/folder/pages.fichas, total_fichas
 * de la cancion y el bloque fichas_index (kicker, intro con {total},
 * etiquetas first/middle/last). Si la cancion no esta en site.json o le
 * faltan datos, se lanza una excepcion.
 *
 * Convierte a PNG los PDF de particella encontrados recursivamente en
 * partitura_dropbox_path (de la cancion o, en su defecto, del sitio) con
 * nombres tipo Particella3Voz3.pdf o Can'tHelpParticellasVoz3Ficha3.pdf.
 * El PNG se guarda con el nombre canonico particella_{ficha}_voz{voz}.png
 * en songs/cancion/fichaN/vozV/. Si no se encuentra el PDF en esa ruta, se
 * usa el PDF de particella que haya en la carpeta de la voz.
 * La altura de recorte de cada ficha se lee de config/site.json
 * (songs[].voices[].fichas[].particella_height); si una ficha no tiene
 * valor propio se usa particella_default_height. El ancho y la resolucion
 * tambien se leen de site.json. Los PNG se regeneran siempre. Sin pdftoppm
 * se lanza una excepcion.
 *
 * Uso:
 *   php tool_generate_fichas.php
 *   php tool_generate_fichas.php --ignore-if-exists
 *   php tool_generate_fichas.php -iie
 *
 * Con --ignore-if-exists (o -iie) no genera nada: solo lista cada fichero
 * que se crearía seguido de (FLAG=ignored-if-exists). Si a una ficha le
 * falta la particella (sin PDF fuente) se lanza una excepcion.
 */

$root = dirname(__DIR__);

require_once $root . '/tools/libs/lib_code.php';
require_once $root . '/tools/libs/html.php';

$sitePath = $root . '/config/site.json';

try {
    if (!is_file($sitePath)) {
        throw new RuntimeException("No existe el archivo de configuracion: {$sitePath}");
    }

    $siteJson = file_get_contents($sitePath);
    if ($siteJson === false) {
        throw new RuntimeException("No se pudo leer {$sitePath}");
    }

    try {
        $site = json_decode($siteJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException("JSON invalido en {$sitePath}: {$exception->getMessage()}", 0, $exception);
    }
    if (!is_array($site)) {
        throw new RuntimeException("La raiz de {$sitePath} debe ser un objeto.");
    }

    [$args, $force] = LibCode::parseToolArgs(
        array_slice($argv, 1),
        ['ignore-if-exists' => 'bool', 'lang' => 'value'],
        ['iie' => 'ignore-if-exists']
    );
    $ignoreIfExists = LibCode::bool($args, 'ignore-if-exists');
    $lang = is_string($args['lang'] ?? null) && $args['lang'] !== '' ? (string) $args['lang'] : 'es';
    LibCode::setLanguage($root, $lang);


    $generated = 0;
    $pngsGenerated = 0;
    $songs = $site['songs'] ?? [];

    if (!is_array($songs) || $songs === []) {
        throw new RuntimeException("No hay canciones definidas en {$sitePath}");
    }

    if (!$ignoreIfExists && !isPdftoppmAvailable()) {
        throw new RuntimeException('pdftoppm no disponible; no se pueden generar los PNG de particellas.');
    }

    foreach ($songs as $song) {
        if (!is_array($song) || !is_string($song['slug'] ?? null) || $song['slug'] === '') {
            throw new RuntimeException('Cancion sin slug valido en site.json.');
        }

        $songSlug = $song['slug'];
        if (!is_string($song['folder'] ?? null) || $song['folder'] === '') {
            throw new RuntimeException("Cancion sin folder valido en site.json.");
        }
        $songRoot = $root . '/' . $song['folder'];

        if (!is_dir($songRoot)) {
            throw new RuntimeException("No existe la carpeta de la cancion: {$songRoot}");
        }

        $songTotalVoces = count($song['voices'] ?? []);
        $songTotalFichas = 0;
        foreach (($song['fichas'] ?? []) as $ficha) {
            $songTotalFichas = max($songTotalFichas, (int) ($ficha['ficha'] ?? 0));
        }

        $particellaSources = [];
        if (!$ignoreIfExists) {
            $dropboxPath = $song['partitura_dropbox_path'] ?? ($site['partitura_dropbox_path'] ?? null);
            if (is_string($dropboxPath) && $dropboxPath !== '') {
                $dropboxRoot = isAbsolutePath($dropboxPath) ? $dropboxPath : $root . '/' . $dropboxPath;

                $needsDropbox = false;
                for ($checkFicha = 1; $checkFicha <= $songTotalFichas; $checkFicha++) {
                    for ($checkVoice = 1; $checkVoice <= $songTotalVoces; $checkVoice++) {
                        $checkDir = $songRoot . "/ficha{$checkFicha}/voz{$checkVoice}";
                        $checkPng = $checkDir . "/particella_{$checkFicha}_voz{$checkVoice}.png";
                        if (is_file($checkPng)) {
                            continue;
                        }

                        $hasLocalSource = false;
                        $canonicalPdf = $checkDir . "/particella_{$checkFicha}_voz{$checkVoice}.pdf";
                        if (is_file($canonicalPdf)) {
                            $hasLocalSource = true;
                        } elseif (is_dir($checkDir)) {
                            foreach (glob($checkDir . '/*.pdf') ?: [] as $candidate) {
                                if (isParticellaFilename(basename($candidate))) {
                                    $hasLocalSource = true;
                                    break;
                                }
                            }
                        }

                        if ($hasLocalSource) {
                            continue;
                        }

                        $hasParticellaFile = false;
                        if (is_dir($checkDir)) {
                            foreach (glob($checkDir . '/*.{png,pdf}', GLOB_BRACE) ?: [] as $candidate) {
                                if (isParticellaFilename(basename($candidate))) {
                                    $hasParticellaFile = true;
                                    break;
                                }
                            }
                        }

                        if (!$hasParticellaFile) {
                            continue;
                        }

                        $needsDropbox = true;
                        break 2;
                    }
                }

                if (!is_dir($dropboxRoot)) {
                    if ($needsDropbox) {
                        throw new RuntimeException("No existe partitura_dropbox_path: {$dropboxRoot}");
                    }
                } else {
                    $particellaSources = findParticellaSources($dropboxRoot);
                }
            }
        }

        for ($fichaNumber = 1; $fichaNumber <= $songTotalFichas; $fichaNumber++) {
            for ($voiceNumber = 1; $voiceNumber <= $songTotalVoces; $voiceNumber++) {
                $voiceDirectory = $songRoot . "/ficha{$fichaNumber}/voz{$voiceNumber}";
                $outputPath = $voiceDirectory . "/voz{$voiceNumber}.html";

                $sourcePdf = $particellaSources[$voiceNumber][$fichaNumber] ?? null;
                $particellaHeight = (int) ($song['particella_default_height'] ?? $site['particella_default_height']);
                foreach (($song['fichas'] ?? []) as $fichaCfg) {
                    if (($fichaCfg['voice'] ?? 0) != $voiceNumber || ($fichaCfg['ficha'] ?? 0) != $fichaNumber) {
                        continue;
                    }
                    $configuredHeight = $fichaCfg['particella_height'] ?? null;
                    if (is_numeric($configuredHeight) && (int) $configuredHeight > 0) {
                        $particellaHeight = (int) $configuredHeight;
                    }
                    break;
                }
                    $particellaCropWidth = (int) ($site['particella_default_crop_width']);
                    $particellaCropDpi = (int) ($site['particell_crop_dpi']);

                $pngsGenerated += renderParticellaPng(
                    $sourcePdf,
                    $voiceDirectory,
                    $fichaNumber,
                    $voiceNumber,
                    $particellaHeight,
                    $particellaCropWidth,
                    $particellaCropDpi
                );

                if ($ignoreIfExists && is_file($outputPath)) {
                    $pngName = "particella_{$fichaNumber}_voz{$voiceNumber}.png";
                    $pngPath = $voiceDirectory . '/' . $pngName;
                    if (!is_file($pngPath)) {
                        echo "{$outputPath} (FLAG=ignored-if-exists) :: FALTA {$pngName}\n";
                        continue;
                    }
                    echo "{$outputPath} (FLAG=ignored-if-exists) :: {$pngName}\n";
                    continue;
                }

                $particella = findParticella($voiceDirectory, $fichaNumber, $voiceNumber);
                $guideName = findGuide($voiceDirectory, $fichaNumber, $voiceNumber);

                $guitarras = is_array($song['guitarras'] ?? null) ? $song['guitarras'] : [];
                $guitarConfigPath = $guitarras[(string) $fichaNumber] ?? ($guitarras[$fichaNumber] ?? null);
                if (is_string($guitarConfigPath) && $guitarConfigPath !== '') {
                    $relativeGuitar = makeRelativePath($voiceDirectory, $songRoot . '/' . ltrim($guitarConfigPath, '/'));
                } else {
                    $relativeGuitar = '';
                }
                $relativeGuide = $guideName === null ? '' : './' . $guideName;
                $prevFicha = $fichaNumber > 1 ? $fichaNumber - 1 : null;
                $nextFicha = $fichaNumber < $songTotalFichas ? $fichaNumber + 1 : null;

                $html = createVoicePage(
                    $fichaNumber,
                    $voiceNumber,
                    $songTotalFichas,
                    $particella,
                    $relativeGuitar,
                    $relativeGuide,
                    $prevFicha,
                    $nextFicha,
                    $song['title']
                );

                $result = LibCode::writeIfChanged($outputPath, $html, true, false);
                if ($result === 'generated') {
                    $generated++;
                }
            }
        }

        if ($ignoreIfExists) {
            $generated += generateFichasIndexes($root, $site, $songSlug, $songTotalFichas, true);
        } else {
            $generated += generateFichasIndexes($root, $site, $songSlug, $songTotalFichas);
        }
    }

    echo "Finalizado: {$generated} generado(s), {$pngsGenerated} PNG de particella(s).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}


function makeRelativePath(string $fromDir, string $toFile): string
{
    $fromParts = explode('/', trim(str_replace('\\', '/', $fromDir), '/'));
    $toParts = explode('/', trim(str_replace('\\', '/', $toFile), '/'));

    $common = 0;
    $limit = min(count($fromParts), count($toParts) - 1);
    while ($common < $limit && $fromParts[$common] === $toParts[$common]) {
        $common++;
    }

    $ups = count($fromParts) - $common;
    $relative = str_repeat('../', $ups);
    $relative .= implode('/', array_slice($toParts, $common));

    return $relative === '' ? '.' : $relative;
}

function findParticella(string $voiceDirectory, int $fichaNumber, int $voiceNumber): ?string
{
    $preferredNames = [
        "particella_{$fichaNumber}_voz{$voiceNumber}.png",
        "particella_{$fichaNumber}_voz{$voiceNumber}.pdf",
    ];

    foreach ($preferredNames as $name) {
        if (is_file($voiceDirectory . '/' . $name)) {
            return $name;
        }
    }

    $candidates = [];
    foreach (glob($voiceDirectory . '/*.{png,pdf}', GLOB_BRACE) ?: [] as $candidate) {
        if (isParticellaFilename(basename($candidate))) {
            $candidates[] = $candidate;
        }
    }

    return $candidates === [] ? null : basename($candidates[0]);
}

/**
 * Indica si el nombre de un archivo corresponde a una particella
 * (mismo criterio para localizar la particella y para convertirla a PNG).
 */
function isParticellaFilename(string $filename): bool
{
    $name = strtolower($filename);

    return str_contains($name, 'particella') || str_contains($name, 'partticella');
}

function findGuide(string $voiceDirectory, int $fichaNumber, int $voiceNumber): ?string
{
    $expected = "guia_{$fichaNumber}_voz_{$voiceNumber}.m4a";
    if (is_file($voiceDirectory . '/' . $expected)) {
        return $expected;
    }

    $candidates = glob($voiceDirectory . '/*.m4a') ?: [];
    return $candidates === [] ? null : basename($candidates[0]);
}

function isPdftoppmAvailable(): bool
{
    $output = [];
    $exitCode = 0;
    exec('command -v pdftoppm 2>/dev/null', $output, $exitCode);

    return $exitCode === 0 && $output !== [];
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/')
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

/**
 * Extrae ficha y voz del nombre de un PDF de particella.
 *
 * Acepta tanto el orden ficha-voz (Particella3Voz3.pdf) como el orden
 * voz-ficha (Can'tHelpParticellasVoz3Ficha3.pdf), con separadores
 * opcionales. Los PDF que cubren un rango de fichas (Fichas1-7) se ignoran.
 *
 * @return array{0: int, 1: int}|null [$ficha, $voz]
 */
function parseParticellaFilename(string $filename): ?array
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $rangeGuard = '(?!\s*[-–]\s*\d)';

    if (preg_match(
        '/particella[s]?[^0-9]*voz[^0-9]*(\d+)' . $rangeGuard . '[^0-9]*ficha[s]?[^0-9]*(\d+)' . $rangeGuard . '/i',
        $name,
        $matches
    ) === 1) {
        return [(int) $matches[2], (int) $matches[1]];
    }

    if (preg_match(
        '/particella[s]?[^0-9]*(\d+)' . $rangeGuard . '[^0-9]*voz[^0-9]*(\d+)' . $rangeGuard . '/i',
        $name,
        $matches
    ) === 1) {
        return [(int) $matches[1], (int) $matches[2]];
    }

    return null;
}

/**
 * Busca recursivamente PDFs de particella y los agrupa por voz y ficha.
 *
 * @return array<int, array<int, string>> [voz][ficha] => ruta del PDF
 */
function findParticellaSources(string $directory): array
{
    $grouped = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'pdf') {
            continue;
        }

        $parsed = parseParticellaFilename($file->getFilename());
        if ($parsed === null) {
            continue;
        }

        [$fichaNumber, $voiceNumber] = $parsed;
        $grouped[$voiceNumber][$fichaNumber][] = $file->getPathname();
    }

    $sources = [];
    foreach ($grouped as $voiceNumber => $fichas) {
        foreach ($fichas as $fichaNumber => $paths) {
            sort($paths);

            if (count($paths) > 1) {
                throw new RuntimeException(
                    "Varias particellas para ficha {$fichaNumber} voz {$voiceNumber}:\n  - "
                    . implode("\n  - ", $paths)
                );
            }

            $sources[$voiceNumber][$fichaNumber] = $paths[0];
        }
    }

    return $sources;
}

/**
 * Lee las alturas de recorte propias de cada ficha de una cancion.
 *
 * Devuelve solo las fichas con particella_height definido, indexadas por
 * [voz][ficha]. Las fichas sin altura propia usan el valor por defecto que
 * indique quien llama (particella_default_height).
 *
 * @param array<string, mixed> $song
 * @return array<int, array<int, int>> [voz][ficha] => altura
 */
function renderParticellaPng(
    ?string $sourcePdf,
    string $voiceDirectory,
    int $fichaNumber,
    int $voiceNumber,
    int $cropHeight,
    int $cropWidth,
    int $cropDpi
): int {
    $canonicalBase = "particella_{$fichaNumber}_voz{$voiceNumber}";
    $canonicalPdf = $voiceDirectory . '/' . $canonicalBase . '.pdf';
    $pngPath = $voiceDirectory . '/' . $canonicalBase . '.png';

    if ($sourcePdf !== null) {
        if (!is_file($sourcePdf)) {
            throw new RuntimeException("No existe el PDF de particella: {$sourcePdf}");
        }

        if (!is_dir($voiceDirectory)
            && !mkdir($voiceDirectory, 0775, true)
            && !is_dir($voiceDirectory)
        ) {
            throw new RuntimeException("No se pudo crear la carpeta: {$voiceDirectory}");
        }

        $pdf = $sourcePdf;
    } elseif (is_file($canonicalPdf)) {
        $pdf = $canonicalPdf;
    } else {
        if (!is_dir($voiceDirectory)) {
            return 0;
        }

        $candidates = [];
        foreach (glob($voiceDirectory . '/*.pdf') ?: [] as $candidate) {
            if (isParticellaFilename(basename($candidate))) {
                $candidates[] = $candidate;
            }
        }

        if (count($candidates) > 1) {
            throw new RuntimeException(
                "Varios PDF de particella en {$voiceDirectory}: " . implode(', ', $candidates)
            );
        }

        if ($candidates === []) {
            return 0;
        }

        $pdf = $candidates[0];
    }

    $outputBase = $voiceDirectory . '/' . $canonicalBase;
    $command = sprintf(
        'pdftoppm -png -singlefile -x 0 -y 0 -W %d -H %d -r %d %s %s 2>&1',
        $cropWidth,
        $cropHeight,
        $cropDpi,
        escapeshellarg($pdf),
        escapeshellarg($outputBase)
    );
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file($pngPath)) {
        throw new RuntimeException(
            "Fallo al convertir {$pdf} a PNG: " . implode("\n", $output)
        );
    }

    echo "Generado: {$pngPath}\n";
    return 1;
}
