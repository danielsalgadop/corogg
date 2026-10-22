<?php

declare(strict_types=1);

require_once __DIR__ . '/lib_code.php';

function createVoicePage(
    int $fichaNumber,
    int $voiceNumber,
    int $totalFichas,
    ?string $particella,
    string $guitarPath,
    string $voicePath,
    ?int $prevFicha,
    ?int $nextFicha,
    string $songTitle = "Can't Help Falling in Love"
): string {
    $voiceLabel = "voz{$voiceNumber}";
    $particellaMarkup = createParticellaMarkup($particella, $fichaNumber, $voiceNumber);
    $navMarkup = createFichaNav($fichaNumber, $voiceNumber, $prevFicha, $nextFicha);
    $trackEntries = [];
    if ($guitarPath !== '') {
        $trackEntries[] = "    { id: 'guitarra', nombre: '" . LibCode::t('track_guitar') . "', url: '{$guitarPath}' }";
    }
    if ($voicePath !== '') {
        $trackEntries[] = "    { id: '{$voiceLabel}', nombre: '🎵 {$voiceLabel}', url: '{$voicePath}' }";
    }
    $tracksJs = implode(",\n", $trackEntries);
    $playerTitle = LibCode::t('player_title');
    $guiaEsperada = LibCode::escape("guia_{$fichaNumber}_voz_{$voiceNumber}.m4a");
    $guiaPendiente = $voicePath === ''
        ? "<p class=\"guia-pendiente\" data-i18n=\"pending_guide\" data-expected=\"" . $guiaEsperada . "\">" . LibCode::t('pending_guide', ['expected' => $guiaEsperada]) . "</p>\n"
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$songTitle} - Ficha{$fichaNumber}/{$totalFichas} {$voiceLabel}</title>

    <script src="../../../../js/howler.core.js"></script>
    <script src="../../../../js/i18n.js?v=4"></script>
    <link rel="stylesheet" href="../../../../css.css">
</head>
<body class="voice-{$voiceNumber}">
<nav>
    <a href="../../../../index.html" class="logo">
        <span>🎼</span> <span data-i18n="brand">Ecos del Atlántico</span>
    </a>
    <ul>
        <li><a href="../../voces.html" data-i18n="nav_voces">Elegir voz</a></li>
        <li><a href="../../../../ayuda.html" class="help-link" data-i18n="nav_ayuda">AYUDA</a></li>
        <li class="lang-switch"><button onclick="setLang('es')">ES</button><button onclick="setLang('en')">EN</button></li>
    </ul>
</nav>

<p class="selection-kicker"><span class="nota-musical">♫</span> {$songTitle} <span class="nota-musical">♫</span></p>
<h1 class="titulo-ficha">Ficha{$fichaNumber}/{$totalFichas} {$voiceLabel}</h1>

{$navMarkup}
{$particellaMarkup}
<div class="contenedor">
    <h1 data-i18n="player_title">{$playerTitle}</h1>

    <div class="controles-globales">
        <button id="btn-reproductor" class="btn-principal" data-playing="0">▶</button>
    </div>
    <div class="barra-progreso-contenedor">
        <input type="range" id="barra-progreso" min="0" max="100" value="0" step="0.1">
        <span id="progreso-texto">0%</span>
    </div>

    <div id="mezclador-pistas"></div>
    {$guiaPendiente}</div>

<script>
const fuentesPistas = [
{$tracksJs}
];
</script>
<script src="../../../../js/player.js"></script>
</body>
</html>
HTML;

}

function createParticellaMarkup(?string $particella, int $fichaNumber, int $voiceNumber): string
{
    if ($particella === null) {
        $esperada = htmlspecialchars("particella_{$fichaNumber}_voz{$voiceNumber}.png", ENT_QUOTES, 'UTF-8');
        return "<p class=\"particella-pendiente\" data-i18n=\"pending_particella\" data-expected=\"" . $esperada . "\">" . LibCode::t('pending_particella', ['expected' => $esperada]) . "</p>";
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
        $prevLink = "<a href=\"../../ficha{$prevFicha}/{$voiceLabel}/{$voiceLabel}.html\" data-i18n=\"prev\"><span class=\"nav-arrow\">&lt;</span> " . LibCode::t('prev') . "</a>";
    } else {
        $prevLink = '<span class="nav-placeholder"></span>';
    }

    if ($nextFicha !== null) {
        $nextLink = "<a href=\"../../ficha{$nextFicha}/{$voiceLabel}/{$voiceLabel}.html\" data-i18n=\"next\">" . LibCode::t('next') . " <span class=\"nav-arrow\">&gt;</span></a>";
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


function generateFichasIndexes(string $root, array $site, string $songSlug, int $fallbackTotal, bool $ignoreIfExists = false): int
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
    $totalFichas = 0;
    foreach (($song['fichas'] ?? []) as $ficha) {
        $totalFichas = max($totalFichas, (int) ($ficha['ficha'] ?? 0));
    }
    $total = $totalFichas > 0 ? $totalFichas : $fallbackTotal;

    foreach (($song['voices'] ?? []) as $position => $voice) {
        if (!is_array($voice)
            || !isset($voice['slug'], $voice['label'])
            || !is_string($voice['slug'])
            || !is_string($voice['label'])
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
        $outputPath = $root . '/' . $song['folder'] . '/' . $voice['pages']['fichas'];

        if ($ignoreIfExists && is_file($outputPath)) {
            echo "{$outputPath} (FLAG=ignored-if-exists)\n";
            continue;
        }

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
        $cards .= "            <a href=\"ficha{$fichaNumber}/voz{$voiceNumber}/voz{$voiceNumber}.html\" class=\"ficha-card\"><span>{$number}</span><strong>" . LibCode::t('ficha', ['number' => $fichaNumber]) . "</strong><small>{$safeTag}</small></a>";
    }

    $fichasTitle = LibCode::t('fichas_title', ['label' => $safeLabel]);
    $fichaLabel = LibCode::t('ficha', ['number' => $fichaNumber]);
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$fichasTitle} - {$safeBrand}</title>
    <link rel="stylesheet" href="../../css.css">
    <script src="../../js/i18n.js?v=4"></script>
</head>
<body>

    <nav>
        <a href="../../index.html" class="logo">
            <span>🎼</span>{$safeBrand}
        </a>
        <ul>
            <li><a href="../../ayuda.html" class="help-link" data-i18n="nav_ayuda">AYUDA</a></li>
            <li class="lang-switch"><button onclick="setLang('es')">ES</button><button onclick="setLang('en')">EN</button></li>
        </ul>
    </nav>

    <main class="selection-page ficha-selection">
        <header class="selection-intro">
            <p class="selection-kicker">{$safeKicker}</p>
            <h1 data-i18n="fichas_title" data-label="{$safeLabel}">{$fichasTitle}</h1>
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
            $sectionNumber = (string) $section['number'];
            $ctaHtml = <<<HTML
            <div style="margin-top:1rem;">
                <a href="{$ctaHref}" class="btn-principal" style="display:inline-block;text-decoration:none;" data-i18n="help_section_{$sectionNumber}_cta">{$ctaLabel}</a>
            </div>
HTML;
        }

        $sectionNumber = (string) $section['number'];
        $sections .= <<<HTML


        <section class="help-section">
            <h2><span data-i18n="help_section_{$sectionNumber}_title">{$sectionTitle}</span></h2>
            <p data-i18n="help_section_{$sectionNumber}_text">{$text}</p>{$ctaHtml}
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
    <script src="js/i18n.js?v=4"></script>
</head>
<body>
    <nav>
        <a href="index.html" class="logo">
            <span>🎼</span> <span data-i18n="brand">Ecos del Atlántico</span>
        </a>
        <ul>
            <li><a href="songs/cant-help-falling-in-love/voces.html" data-i18n="nav_voces">Elegir voz</a></li>
            <li><a href="ayuda.html" class="help-link" data-i18n="nav_ayuda">AYUDA</a></li>
            <li class="lang-switch"><button onclick="setLang('es')">ES</button><button onclick="setLang('en')">EN</button></li>
        </ul>
    </nav>

    <main class="help-content">
        <header class="help-intro">
            <h1 data-i18n="help_title">{$title}</h1>
            <p data-i18n="help_intro">{$intro}</p>
        </header>{$sections}
    </main>
</body>
</html>
HTML;
}
