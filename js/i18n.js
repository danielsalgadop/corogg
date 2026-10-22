const I18N = {
    "es": {
        "nav_repertorio": "Repertorio",
        "nav_ayuda": "AYUDA",
        "nav_voces": "Elegir voz",
        "brand": "Ecos del Atlántico",
        "tagline": "Una familia, muchas voces",
        "pending_guide": "Guía pendiente de incorporar: {expected}",
        "player_title": "Reproductor Multipista",
        "track_guitar": "🎵 Guitarra",
        "pending_particella": "Particella pendiente de incorporar: {expected}",
        "prev": "Anterior",
        "next": "Siguiente",
        "fichas_title": "Fichas · {label}",
        "ficha": "Ficha {number}",
        "first": "Comenzar",
        "middle": "Continuar",
        "last": "Final",
        "explore_voices": "Explorar voces",
        "sheet_music": "Partitura"
    },
    "en": {
        "nav_repertorio": "Repertoire",
        "nav_ayuda": "HELP",
        "nav_voces": "Choose voice",
        "brand": "Echoes of the Atlantic",
        "tagline": "One family, many voices",
        "pending_guide": "Guide pending to add: {expected}",
        "player_title": "Multitrack Player",
        "track_guitar": "🎵 Guitar",
        "pending_particella": "Particella pending to add: {expected}",
        "prev": "Previous",
        "next": "Next",
        "fichas_title": "Sheets · {label}",
        "ficha": "Sheet {number}",
        "first": "Start",
        "middle": "Continue",
        "last": "End",
        "explore_voices": "Explore voices",
        "sheet_music": "Sheet music"
    }
};

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