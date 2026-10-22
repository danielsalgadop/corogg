<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tools/libs/lib_code.php';

$i18nDir = $root . '/config/i18n';
$outputPath = $root . '/js/i18n.js';

$languages = [];
foreach (['es', 'en'] as $lang) {
    $path = $i18nDir . '/' . $lang . '.json';
    if (!is_file($path)) {
        fwrite(STDERR, "No existe {$path}\n");
        exit(1);
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "JSON invalido en {$path}\n");
        exit(1);
    }
    $languages[$lang] = $data;
}

$js = "const I18N = " . json_encode($languages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ";\n\n";

$js .= <<<'JS'
function safeGetLang() {
  try {
    const p = new URLSearchParams(window.location.search);
    if (p.get('lang')) return p.get('lang');
  } catch (e) {}
  try { return localStorage.getItem('lang') || 'es'; } catch (e) { return 'es'; }
}

function safeSetLangStorage(lang) {
  try { localStorage.setItem('lang', lang); } catch (e) {}
}

function applyI18n(lang) {
  document.documentElement.lang = lang;
  document.querySelectorAll('[data-i18n]').forEach(function (el) {
    const key = el.getAttribute('data-i18n');
    let val = (I18N[lang] || {})[key];
    if (val === undefined) return;
    const expected = el.getAttribute('data-expected');
    if (expected) val = val.replace('{expected}', expected);
    const label = el.getAttribute('data-label');
    if (label) val = val.replace('{label}', label);
    const number = el.getAttribute('data-number');
    if (number) val = val.replace('{number}', number);
    el.textContent = val;
  });
  const btn = document.getElementById('btn-reproductor');
  if (btn) {
    const playing = btn.getAttribute('data-playing') === '1';
    btn.textContent = playing ? '⏸' : '▶';
  }
}

function setLang(lang) {
  safeSetLangStorage(lang);
  try {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    history.replaceState(null, '', url.toString());
  } catch (e) {}
  applyI18n(lang);
  document.querySelectorAll('a').forEach(function (a) {
    try {
      const url = new URL(a.href);
      url.searchParams.set('lang', lang);
      a.href = url.toString();
    } catch (e) {}
  });
  document.querySelectorAll('.lang-switch button').forEach(function (b) {
    b.classList.toggle('active', b.textContent.trim().toLowerCase() === lang);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  setLang(safeGetLang());
});
JS;

[$args, $force] = LibCode::parseToolArgs(array_slice($argv, 1), []);
LibCode::writeIfChanged($outputPath, $js, $force, false);
