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
 *
 * No acepta argumentos: genera y sobrescribe siempre todos los archivos de
 * todas las canciones, fichas y voces.
 */

$root = dirname(__DIR__);

require_once $root . '/lib_code.php';

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





    $generated = 0;
    $pngsGenerated = 0;
    $songs = $site['songs'] ?? [];

    if (!is_array($songs) || $songs === []) {
        throw new RuntimeException("No hay canciones definidas en {$sitePath}");
    }

    if (!isPdftoppmAvailable()) {
        throw new RuntimeException('pdftoppm no disponible; no se pueden generar los PNG de particellas.');
    }

    foreach ($songs as $song) {
        if (!is_array($song) || !is_string($song['slug'] ?? null) || $song['slug'] === '') {
            throw new RuntimeException('Cancion sin slug valido en site.json.');
        }

        $songSlug = $song['slug'];
        $songRoot = $root . '/songs/' . $songSlug;

        if (!is_dir($songRoot)) {
            throw new RuntimeException("No existe la carpeta de la cancion: {$songRoot}");
        }

        $songTotalFichas = (int) $song['total_fichas'];
        $songTotalVoces = (int) $song['total_voces'];
        $songHeights = readParticellaHeights($song);

        $particellaSources = [];
        $dropboxPath = $song['partitura_dropbox_path'] ?? ($site['partitura_dropbox_path'] ?? null);
        if (is_string($dropboxPath) && $dropboxPath !== '') {
            $dropboxRoot = isAbsolutePath($dropboxPath) ? $dropboxPath : $root . '/' . $dropboxPath;
            if (!is_dir($dropboxRoot)) {
                throw new RuntimeException("No existe partitura_dropbox_path: {$dropboxRoot}");
            }
            $particellaSources = findParticellaSources($dropboxRoot);
        }

        for ($fichaNumber = 1; $fichaNumber <= $songTotalFichas; $fichaNumber++) {
            for ($voiceNumber = 1; $voiceNumber <= $songTotalVoces; $voiceNumber++) {
                $voiceDirectory = $songRoot . "/ficha{$fichaNumber}/voz{$voiceNumber}";
                $sourcePdf = $particellaSources[$voiceNumber][$fichaNumber];
                $particellaDefaultHeight = (int) ($site['particella_default_height']);
                $particellaHeight = $songHeights[$voiceNumber][$fichaNumber] ?? $particellaDefaultHeight;
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
                $particella = findParticella($voiceDirectory, $fichaNumber, $voiceNumber);
                $guideName = findGuide($voiceDirectory, $fichaNumber, $voiceNumber);
                $outputPath = $voiceDirectory . "/voz{$voiceNumber}.html";

                $relativeGuitar = "../guitarra{$fichaNumber}.mp3";
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
                    $nextFicha
                );

                $result = LibCode::writeIfChanged($outputPath, $html, true, false);
                if ($result === 'generated') {
                    $generated++;
                }
            }
        }

        $generated += generateFichasIndexes($root, $site, $songSlug, $songTotalFichas);
    }

    echo "Finalizado: {$generated} generado(s), {$pngsGenerated} PNG de particella(s).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function createVoicePage(
    int $fichaNumber,
    int $voiceNumber,
    int $totalFichas,
    ?string $particella,
    string $guitarPath,
    string $voicePath,
    ?int $prevFicha,
    ?int $nextFicha
): string {
    $songTitle = "Can't Help Falling in Love";
    $voiceLabel = "voz{$voiceNumber}";
    $particellaMarkup = createParticellaMarkup($particella, $fichaNumber, $voiceNumber);
    $navMarkup = createFichaNav($fichaNumber, $voiceNumber, $prevFicha, $nextFicha);
    $guideTrack = $voicePath === ''
        ? ''
        : "    { id: '{$voiceLabel}', nombre: '🎵 {$voiceLabel}', url: '{$voicePath}' }";
    $tracksSeparator = $guideTrack === '' ? '' : ",\n";
    $guiaEsperada = LibCode::escape("guia_{$fichaNumber}_voz_{$voiceNumber}.m4a");
    $guiaPendiente = $voicePath === ''
        ? "<p class=\"guia-pendiente\">Guía pendiente de incorporar: {$guiaEsperada}</p>\n"
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$songTitle} - Ficha{$fichaNumber}/{$totalFichas} {$voiceLabel}</title>

    <script src="../../../../howler.core.js"></script>
    <link rel="stylesheet" href="../../../../css.css">
</head>
<body>
<nav>
    <a href="../../../../index.html" class="logo">
        <span>🎼</span> Ecos del Atlántico
    </a>
    <ul>
        <li><a href="../../voces.html">Elegir voz</a></li>
        <li><a href="../../../../ayuda.html" class="help-link">AYUDA</a></li>
    </ul>
</nav>

<p class="selection-kicker"><span class="nota-musical">♫</span> {$songTitle} <span class="nota-musical">♫</span></p>
<h1 class="titulo-ficha">Ficha{$fichaNumber}/{$totalFichas} {$voiceLabel}</h1>

{$navMarkup}
{$particellaMarkup}
<div class="contenedor">
    <h1>Reproductor Multipista</h1>

    <div class="controles-globales">
        <button id="btn-reproductor" class="btn-principal">▶ Reproducir Todo</button>
    </div>

    <div id="mezclador-pistas"></div>
    {$guiaPendiente}</div>

<script>
const fuentesPistas = [
    { id: 'guitarra', nombre: '🎵 Guitarra', url: '{$guitarPath}' }{$tracksSeparator}{$guideTrack}
];

const pistas = {};
let enReproduccion = false;

const btnReproductor = document.getElementById('btn-reproductor');
const contenedorPistas = document.getElementById('mezclador-pistas');

fuentesPistas.forEach(pistaInfo => {
    pistas[pistaInfo.id] = new Howl({
        src: [pistaInfo.url],
        loop: true,
        volume: 0.7
    });

    crearFaderInterfaz(pistaInfo);
});

function crearFaderInterfaz(pistaInfo) {
    const divPista = document.createElement('div');
    divPista.className = 'pista';

    const info = document.createElement('div');
    info.className = 'info-pista';
    info.textContent = pistaInfo.nombre;

    const input = document.createElement('input');
    input.type = 'range';
    input.className = 'fader-volumen';
    input.min = '0';
    input.max = '1';
    input.step = '0.01';
    input.value = '0.7';

    const valorTxt = document.createElement('div');
    valorTxt.className = 'valor-volumen';
    valorTxt.textContent = '70%';

    input.addEventListener('input', (e) => {
        const volumen = parseFloat(e.target.value);
        pistas[pistaInfo.id].volume(volumen);
        valorTxt.textContent = Math.round(volumen * 100) + '%';
    });

    divPista.appendChild(info);
    divPista.appendChild(input);
    divPista.appendChild(valorTxt);
    contenedorPistas.appendChild(divPista);
}

btnReproductor.addEventListener('click', () => {
    if (!enReproduccion) {
        Object.keys(pistas).forEach(id => pistas[id].play());
        btnReproductor.textContent = '⏸ Pausar Todo';
        btnReproductor.style.background = '#ff007f';
        enReproduccion = true;
    } else {
        Object.keys(pistas).forEach(id => pistas[id].pause());
        btnReproductor.textContent = '▶ Reproducir Todo';
        btnReproductor.style.background = '#00e5ff';
        enReproduccion = false;
    }
});
</script>
</body>
</html>
HTML;
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
function readParticellaHeights(array $song): array
{
    $heights = [];

    foreach (($song['voices'] ?? []) as $voiceIndex => $voice) {
        foreach (($voice['fichas'] ?? []) as $ficha) {
            if (!is_array($ficha) || !isset($ficha['ficha'])) {
                continue;
            }

            $height = $ficha['particella_height'] ?? null;
            if (is_numeric($height) && (int) $height > 0) {
                $heights[$voiceIndex + 1][(int) $ficha['ficha']] = (int) $height;
            }
        }
    }

    return $heights;
}

/**
 * Genera el PNG de la particella de una voz con pdftoppm a partir de su PDF.
 *
 * Si se recibe $sourcePdf se usa ese PDF (por ejemplo, el encontrado en
 * partitura_dropbox_path) y se crea la carpeta de destino si hace falta.
 * Si no, se busca en la carpeta de la voz: primero el PDF canonico
 * particella_{ficha}_voz{voz}.pdf y despues el unico PDF detectado como
 * particella. Si hay varios PDF de origen no canonicos se lanza una
 * excepcion, para no elegir a ciegas.
 *
 * El PNG siempre se escribe con el nombre canonico
 * particella_{ficha}_voz{voz}.png y se regenera siempre.
 *
 * @param int $cropHeight Altura de recorte en px, leida de site.json.
 * @return int 1 si se genera el PNG, 0 si no hay PDF de origen.
 */
function renderParticellaPng(
    string $sourcePdf,
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

function createParticellaMarkup(?string $particella, int $fichaNumber, int $voiceNumber): string
{
    if ($particella === null) {
        $esperada = htmlspecialchars("particella_{$fichaNumber}_voz{$voiceNumber}.png", ENT_QUOTES, 'UTF-8');
        return "<p class=\"particella-pendiente\">Particella pendiente de incorporar: {$esperada}</p>";
    }

    $safeName = htmlspecialchars($particella, ENT_QUOTES, 'UTF-8');
    $safeUrl = './' . rawurlencode($particella);
    $extension = strtolower(pathinfo($particella, PATHINFO_EXTENSION));

    if ($extension === 'png') {
        return "<img src=\"{$safeUrl}\" alt=\"Particella\">";
    }

    return <<<HTML
<object class="particella-pdf" data="{$safeUrl}" type="application/pdf">
    <a href="{$safeUrl}">Abrir particella {$safeName}</a>
</object>
HTML;
}

function createFichaNav(
    int $fichaNumber,
    int $voiceNumber,
    ?int $prevFicha,
    ?int $nextFicha
): string {
    $voiceLabel = "voz{$voiceNumber}";

    if ($prevFicha !== null) {
        $prevLink = "<a href=\"../../ficha{$prevFicha}/{$voiceLabel}/{$voiceLabel}.html\"><span class=\"nav-arrow\">&lt;</span> Anterior</a>";
    } else {
        $prevLink = '<span class="nav-placeholder"></span>';
    }

    if ($nextFicha !== null) {
        $nextLink = "<a href=\"../../ficha{$nextFicha}/{$voiceLabel}/{$voiceLabel}.html\">Siguiente <span class=\"nav-arrow\">&gt;</span></a>";
    } else {
        $nextLink = '<span class="nav-placeholder"></span>';
    }

    return "<nav class=\"ficha-nav\">{$prevLink}{$nextLink}</nav>";
}

/**
 * Genera las paginas indice fichas_voz*.html de cada voz a partir de
 * config/site.json (voices[].slug/label/folder/pages.fichas, total_fichas
 * de la cancion y bloque fichas_index con kicker, intro y first/middle/last).
 *
 * Si la cancion no esta en site.json o le faltan datos, se lanza una
 * excepcion.
 *
 * @param array<string, mixed> $site
 * @return int Numero de indices generados.
 */
function generateFichasIndexes(string $root, array $site, string $songSlug, int $fallbackTotal): int
{
    $generated = 0;

    $song = null;
    foreach (($site['songs'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['slug'] ?? null) === $songSlug) {
            $song = $candidate;
            break;
        }
    }

    if ($song === null) {
        throw new RuntimeException("Cancion '{$songSlug}' no encontrada en site.json.");
    }

    $brand = isset($site['site_title']) && is_string($site['site_title'])
        ? $site['site_title']
        : 'Ecos del Atlántico';
    $texts = array_merge(
        [
            'kicker' => 'Tu recorrido de estudio',
            'intro' => 'Avanza de la ficha 1 a la {total} y prepara tu parte paso a paso.',
            'first' => 'Comenzar',
            'middle' => 'Continuar',
            'last' => 'Final',
        ],
        isset($site['fichas_index']) && is_array($site['fichas_index']) ? $site['fichas_index'] : []
    );
    $total = isset($song['total_fichas']) ? (int) $song['total_fichas'] : $fallbackTotal;

    foreach (($song['voices'] ?? []) as $position => $voice) {
        if (!is_array($voice)
            || !isset($voice['slug'], $voice['label'], $voice['folder'])
            || !is_string($voice['slug'])
            || !is_string($voice['label'])
            || !is_string($voice['folder'])
            || !isset($voice['pages']['fichas'])
            || !is_string($voice['pages']['fichas'])
        ) {
            throw new RuntimeException("Voz {$position} sin slug/label/folder/pages.fichas en site.json.");
        }

        $voiceNumber = $position + 1;
        if (preg_match('/(\d+)$/', $voice['slug'], $matches) === 1) {
            $voiceNumber = (int) $matches[1];
        }

        $html = createFichasIndexPage($brand, $voice['label'], $voiceNumber, $total, $texts);
        $outputPath = $root . '/' . $voice['folder'] . '/' . $voice['pages']['fichas'];

        $result = LibCode::writeIfChanged($outputPath, $html, true, false);
        if ($result === 'generated') {
            $generated++;
        }
    }

    return $generated;
}

function createFichasIndexPage(
    string $brand,
    string $voiceLabel,
    int $voiceNumber,
    int $totalFichas,
    array $texts
): string {
    $safeBrand = LibCode::escape($brand);
    $safeLabel = LibCode::escape($voiceLabel);
    $safeKicker = LibCode::escape((string) ($texts['kicker'] ?? ''));
    $safeIntro = LibCode::escape(
        str_replace('{total}', (string) $totalFichas, (string) ($texts['intro'] ?? ''))
    );

    $cards = '';
    for ($fichaNumber = 1; $fichaNumber <= $totalFichas; $fichaNumber++) {
        $tag = (string) ($texts['middle'] ?? '');
        if ($fichaNumber === 1) {
            $tag = (string) ($texts['first'] ?? '');
        } elseif ($fichaNumber === $totalFichas) {
            $tag = (string) ($texts['last'] ?? '');
        }
        $number = str_pad((string) $fichaNumber, 2, '0', STR_PAD_LEFT);
        $safeTag = LibCode::escape($tag);
        if ($cards !== '') {
            $cards .= "\n";
        }
        $cards .= "            <a href=\"ficha{$fichaNumber}/voz{$voiceNumber}/voz{$voiceNumber}.html\" class=\"ficha-card\"><span>{$number}</span><strong>Ficha {$fichaNumber}</strong><small>{$safeTag}</small></a>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichas {$safeLabel} - {$safeBrand}</title>
    <link rel="stylesheet" href="../../css.css">
</head>
<body>

    <nav>
        <a href="../../index.html" class="logo">
            <span>🎼</span>{$safeBrand}
        </a>
        <ul>
            <li><a href="../../ayuda.html" class="help-link">AYUDA</a></li>
        </ul>
    </nav>

    <main class="selection-page ficha-selection">
        <header class="selection-intro">
            <p class="selection-kicker">{$safeKicker}</p>
            <h1>Fichas · {$safeLabel}</h1>
            <p>{$safeIntro}</p>
        </header>
        <section class="ficha-grid" aria-label="Fichas de {$safeLabel}">
{$cards}
        </section>
    </main>
</body>
</html>
HTML;
}