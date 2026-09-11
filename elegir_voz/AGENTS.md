# AGENTS.md - Descubre tu Voz

App estática (HTML + CSS + JS) que ayuda a saber tu parte en el coro (Voz 1, Voz 2, Voz 3) escuchando tonos generados con Web Audio API. Sin backend, sin MP3, sin dependencias. Tema visual corogg (dorado/oscuro, Cinzel+Montserrat, nav).

## Estructura
- `index.html` - Estructura: hero + controles timbre/volumen, grid `#voice-grid`, test con piano `#piano`, tabla `#table-body`, resultado `#resultado`
- `styles.css` - Tema oscuro, variables CSS, layout grid + piano con scroll horizontal
- `app.js` - Toda la lógica: datos VOICES, síntesis, piano, análisis

## Cómo ejecutar
```bash
python3 -m http.server 8000
# abrir http://localhost:8000
```
Abrir `index.html` directo con `file://` también funciona (todo es cliente).

## Datos clave (app.js)
`VOICES[]` con `id, nombre, low/high (MIDI), rangoTxt, freqTxt`:
- voz1: 60-84 (Do4-Do6)
- voz2: 50-74 (Re3-Re5)
- voz3: 40-64 (Mi2-Mi4)

Piano: `LOW_MIDI=40 (Mi2)` a `HIGH_MIDI=84 (Do6)`.
`midiToFreq(m) = 440*2^((m-69)/12)`, `midiToName()` devuelve `Do4 (C4)`.

## Audio (Web Audio API)
- `playTone(midi, dur)` - crea AudioContext lazy, envelope gain 0.08s attack / release 0.25s
- Timbres (`#timbre`): `vocal` (2x sawtooth + lowpass + bandpass), `piano` (triangle+sine octava), `flauta` (sine + vibrato 5.5Hz)
- Volumen: `#volumen` 0-100 -> gain 0-0.5
- `playScale(low,high)` - reproduce ascendente con `setTimeout 650ms`, llama a `highlightKey()` + `showNow()`. `stopScale()` cancela.

## Test / Análisis
- Estado: `lastPlayed, myLow, myHigh`, `keyEls[midi]`
- Botones: `#btn-mark-low`, `#btn-mark-high`, `#btn-clear`, `#btn-analyze`
- `refreshMarks()` pinta `.mark-low/.mark-high/.in-range`, actualiza `#low-label/#high-label/#range-label`
- Análisis: overlap Jaccard + distancia de centros: `score = overlap/union*100 - dist*1.5`. Muestra best + second en `#resultado` con barra `%` y botón `#res-play`.
- `consejo(id)` devuelve tip vocal por tipo.

## Convenciones para agentes
- No añadir dependencias npm ni build. Mantener 3 ficheros vanilla.
- Idioma UI: español. Notas: `Do,Re,Mi... + (C,D,E...)`.
- Estilos: usar variables `:root` (`--bg, --card, --accent, --accent2, --green`), clases `.card, .chip, .key.white/.black`.
- Al añadir voz nueva: añadir entrada en `VOICES`, todo (cards + tabla + análisis) se genera solo.
- No usar `alert()`, usar `#resultado` y `#now-playing`.
- Verificar con `node --check app.js`.
