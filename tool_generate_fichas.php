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
 * Uso:
 *   php tool_generate_fichas.php
 *   php tool_generate_fichas.php --ficha=3 --voz=2
 *   php tool_generate_fichas.php --cancion=cant-help-falling-in-love
 *   php tool_generate_fichas.php --force
 *   php tool_generate_fichas.php --dry-run
 */

$root = __DIR__;

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