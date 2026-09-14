# AGENTS.md - Ecos del Atlántico

Sitio web estático para un coro bilingüe (España/EE.UU.) que ayuda a los coristas a aprender sus partes. Páginas generadas a partir de JSON de configuración mediante scripts PHP/Shell.

## Estructura
- `index.html` — Página de inicio con el lema "Una familia, muchas voces"
- `repertorio.html` — Catálogo de canciones (actualmente solo "Can't Help Falling in Love")
- `songs/` — Contenido por canción: HTMLs por voz (1, 2, 3), fichas de estudio por sección (ficha1-6), partituras PDF y guías de audio MP3/M4A
- `elegir_voz/` — App interactiva (HTML+JS vanilla, Web Audio API) que ayuda al corista a descubrir qué voz le corresponde con un piano virtual y análisis de rango vocal por micrófono. Tiene su propio `AGENTS.md`.
- `config/` — JSON con la configuración del sitio, catálogo de canciones/voices/fichas y contenido de la ayuda:
  - `site.json` — título del sitio, catálogo de canciones, voces y fichas
  - `app.json` — totales (fichas y voces)
  - `ayuda.json` — secciones de ayuda
- `tools/` — Scripts PHP/Shell para generar automáticamente los HTML del repertorio, fichas y ayuda a partir de los JSON de config:
  - `tool_generate_repertorio.php`, `tool_generate_fichas.php`, `tool_generate_ayuda.php`
  - `tool_normalizar_nombres.sh`, `tool_estructura_fichas_ok.sh`, `tool_pdf2png_particella_creator.sh`
- `lib_code.php` — Librería compartida para los tools (parseo de args, escape HTML, escritura de archivos respetando la bandera `--force`)

## Cómo ejecutar
Sitio estático. Para verlo localmente:
```bash
python3 -m http.server 8000
# abrir http://localhost:8000
```

## Convenciones
- Idioma UI: español
- Los HTML del sitio se generan con los tools PHP a partir de `config/*.json`; no editar los HTML generados a mano salvo necesidad
- `ayuda.html` se genera desde `config/ayuda.json`