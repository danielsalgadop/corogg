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
 * faltan datos, los indices se omiten con un aviso.
 *
 * Uso:
 *   php tool_generate_fichas.php
 *   php tool_generate_fichas.php --ficha=3 --voz=2
 *   php tool_generate_fichas.php --cancion=cant-help-falling-in-love
 *   php tool_generate_fichas.php --force
 *   php tool_generate_fichas.php --dry-run
 *
 * Nota: los indices solo se generan en ejecuciones completas de la cancion
 * (sin --ficha ni --voz).
 */

$root = dirname(__DIR__);

require_once $root . '/lib_code.php';

$configPath = $root . '/config/app.json';
if (!is_file($configPath)) {
    fwrite(STDERR, "No existe la configuracion: {$configPath}\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
$totalFichas = $config['total_fichas'] ?? 6;
$totalVoces = $config['total_voces'] ?? 3;

try {
    [$args, $force] = LibCode::parseToolArgs(array_slice($argv, 1), [
        'dry-run' => 'bool',
        'cancion' => 'value',
        'ficha'   => 'value',
        'voz'     => 'value',
    ]);

    $dryRun = LibCode::bool($args, 'dry-run');
    $requestedFicha = LibCode::value($args, 'ficha', '/^\d+$/', 'Ficha');
    $requestedVoice = LibCode::value($args, 'voz', '/^\d+$/', 'Voz');
    $songSlug = LibCode::value($args, 'cancion', '/^[a-z0-9-]+$/', 'Cancion');
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$requestedFicha = $requestedFicha === null ? null : (int) $requestedFicha;
$requestedVoice = $requestedVoice === null ? null : (int) $requestedVoice;

if ($requestedVoice !== null && $requestedFicha === null) {
    fwrite(STDERR, "--voz requiere tambien --ficha=N\n");
    exit(1);
}

if ($songSlug === null) {
    $songSlug = selectSongSlug($root . '/songs');
}

$generated = 0;
$skipped = 0;
$songRoot = $root . '/songs/' . $songSlug;

if (!is_dir($songRoot)) {
    fwrite(STDERR, "No existe la carpeta de la cancion: {$songRoot}\n");
    exit(1);
}

for ($fichaNumber = 1; $fichaNumber <= $totalFichas; $fichaNumber++) {
    if ($requestedFicha !== null && $requestedFicha !== $fichaNumber) {
        continue;
    }

    for ($voiceNumber = 1; $voiceNumber <= $totalVoces; $voiceNumber++) {
        if ($requestedVoice !== null && $requestedVoice !== $voiceNumber) {
            continue;
        }

        $voiceDirectory = $songRoot . "/ficha{$fichaNumber}/voz{$voiceNumber}";
        $particella = findParticella($voiceDirectory, $fichaNumber, $voiceNumber);
        $guideName = findGuide($voiceDirectory, $fichaNumber, $voiceNumber);
        $outputPath = $voiceDirectory . "/voz{$voiceNumber}.html";

        $relativeGuitar = "../guitarra{$fichaNumber}.mp3";
        $relativeGuide = $guideName === null ? '' : './' . $guideName;
        $prevFicha = $fichaNumber > 1 ? $fichaNumber - 1 : null;
        $nextFicha = $fichaNumber < $totalFichas ? $fichaNumber + 1 : null;

        $html = createVoicePage(
            $fichaNumber,
            $voiceNumber,
            $totalFichas,
            $particella,
            $relativeGuitar,
            $relativeGuide,
            $prevFicha,
            $nextFicha
        );

        $result = LibCode::writeIfChanged($outputPath, $html, $force, $dryRun);
        if ($result === 'generated') {
            $generated++;
        } elseif ($result === 'skipped') {
            $skipped++;
        }
    }
}

if ($requestedFicha !== null && $generated === 0 && $skipped === 0) {
    fwrite(STDERR, "No se encontro una combinacion ficha/voz compatible.\n");
    exit(1);
}

if ($requestedFicha === null && $requestedVoice === null) {
    [$indexGenerated, $indexSkipped] = generateFichasIndexes($root, $songSlug, $totalFichas, $force, $dryRun);
    $generated += $indexGenerated;
    $skipped += $indexSkipped;
}

echo "Finalizado: {$generated} generado(s), {$skipped} conservado(s).\n";

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
    $particellaMarkup = createParticellaMarkup($particella);
    $navMarkup = createFichaNav($fichaNumber, $voiceNumber, $prevFicha, $nextFicha);
    $guideTrack = $voicePath === ''
        ? ''
        : "    { id: '{$voiceLabel}', nombre: '🎵 {$voiceLabel}', url: '{$voicePath}' }";
    $tracksSeparator = $guideTrack === '' ? '' : ",\n";

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
</div>

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

function selectSongSlug(string $songsRoot): string
{
    $songDirectories = glob($songsRoot . '/*', GLOB_ONLYDIR) ?: [];
    sort($songDirectories, SORT_NATURAL | SORT_FLAG_CASE);

    if ($songDirectories === []) {
        fwrite(STDERR, "No hay carpetas de canciones en {$songsRoot}\n");
        exit(1);
    }

    echo "Selecciona la cancion para generar sus fichas:\n";
    foreach ($songDirectories as $index => $directory) {
        printf("  %d) %s\n", $index + 1, basename($directory));
    }
    echo "Numero: ";

    $answer = fgets(STDIN);
    $selection = $answer === false ? 0 : (int) trim($answer);
    $selectedIndex = $selection - 1;

    if ($selection < 1 || !isset($songDirectories[$selectedIndex])) {
        fwrite(STDERR, "Seleccion no valida. Usa uno de los numeros mostrados.\n");
        exit(1);
    }

    return basename($songDirectories[$selectedIndex]);
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
        $candidateName = strtolower(basename($candidate));
        if (str_contains($candidateName, 'particella') || str_contains($candidateName, 'partticella')) {
            $candidates[] = $candidate;
        }
    }

    return $candidates === [] ? null : basename($candidates[0]);
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

function createParticellaMarkup(?string $particella): string
{
    if ($particella === null) {
        return '<p class="particella-pendiente">Particella pendiente de incorporar.</p>';
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
 * Si site.json no existe, es invalido o no contiene la cancion, los indices
 * se omiten con un aviso sin fallar (las paginas de voz ya estan generadas).
 *
 * @return array{0: int, 1: int} [$generated, $skipped]
 */
function generateFichasIndexes(string $root, string $songSlug, int $fallbackTotal, bool $force, bool $dryRun): array
{
    $generated = 0;
    $skipped = 0;

    $sitePath = $root . '/config/site.json';
    if (!is_file($sitePath)) {
        fwrite(STDERR, "Aviso: no existe {$sitePath}, se omiten los indices de fichas.\n");
        return [$generated, $skipped];
    }

    try {
        $site = json_decode((string) file_get_contents($sitePath), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Aviso: JSON invalido en {$sitePath}: {$exception->getMessage()}\n");
        return [$generated, $skipped];
    }

    $song = null;
    foreach (($site['songs'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['slug'] ?? null) === $songSlug) {
            $song = $candidate;
            break;
        }
    }

    if ($song === null) {
        fwrite(STDERR, "Aviso: cancion '{$songSlug}' no encontrada en site.json, se omiten los indices.\n");
        return [$generated, $skipped];
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
            fwrite(STDERR, "Aviso: voz {$position} sin slug/label/folder/pages.fichas, se omite.\n");
            continue;
        }

        $voiceNumber = $position + 1;
        if (preg_match('/(\d+)$/', $voice['slug'], $matches) === 1) {
            $voiceNumber = (int) $matches[1];
        }

        $html = createFichasIndexPage($brand, $voice['label'], $voiceNumber, $total, $texts);
        $outputPath = $root . '/' . $voice['folder'] . '/' . $voice['pages']['fichas'];

        $result = LibCode::writeIfChanged($outputPath, $html, $force, $dryRun);
        if ($result === 'generated') {
            $generated++;
        } elseif ($result === 'skipped') {
            $skipped++;
        }
    }

    return [$generated, $skipped];
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