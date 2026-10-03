#!/usr/bin/env bash

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

overall_status=0
song_slug="${1:-}"

if [ -z "$song_slug" ]; then
    mapfile -t song_roots < <(find songs -mindepth 1 -maxdepth 1 -type d | sort -f)
    if [ "${#song_roots[@]}" -eq 0 ]; then
        echo "[ERROR] No hay carpetas de canciones en songs" >&2
        exit 1
    fi
else
    song_roots=("songs/$song_slug")
fi

for song_root in "${song_roots[@]}"; do
    if [ ! -d "$song_root" ]; then
        echo "[ERROR] No existe la carpeta de la cancion: $song_root" >&2
        overall_status=1
        continue
    fi

    for ficha_dir in "$song_root"/ficha[0-9]*/; do
    [ -d "$ficha_dir" ] || continue
    
    ficha_name="$(basename "${ficha_dir%/}")"
    [[ "$ficha_name" =~ ^ficha[0-9]+$ ]] || continue
    ficha_num="${ficha_name#ficha}"
    
    # Remove leading/trailing slashes for clean path operations
    ficha_path="${ficha_dir%/}"
    
    ficha_ok=1
    
    echo "--------------------------------------------------"
    echo "Comprobando: $ficha_path"
    
    # 1. Comprobar guitarra*.mp3 donde * coincide con el número del folder
    guitarra_file="$ficha_path/guitarra${ficha_num}.mp3"
    if [ -f "$guitarra_file" ]; then
        echo "  [OK] (ficha${ficha_num}) Archivo encontrado: $guitarra_file"
    else
        echo "  [ERROR] (ficha${ficha_num}) Falta archivo esperado: guitarra${ficha_num}.mp3 en $ficha_path"
        ficha_ok=0
    fi
    
    # 2. Comprobar folders voz1 hasta voz3
    for v in 1 2 3; do
        voz_dir="$ficha_path/voz$v"
        
        if [ ! -d "$voz_dir" ]; then
            echo "  [ERROR] (ficha${ficha_num} voz${v}) Falta el directorio: $voz_dir"
            ficha_ok=0
            continue
        fi
        
        # 2a. Comprobar voz%.html dentro de voz%
        voz_html="$voz_dir/voz${v}.html"
        if [ -f "$voz_html" ]; then
            echo "  [OK] (ficha${ficha_num} voz${v}) Encontrado: $voz_html"
        else
            echo "  [ERROR] (ficha${ficha_num} voz${v}) Falta archivo: voz${v}.html en $voz_dir (hay: $(ls "$voz_dir" 2>/dev/null | tr '\n' ' '))"
            ficha_ok=0
        fi
        
        # 2b. Comprobar particella_*_voz% (PDF fuente: solo AVISO si falta, el HTML usa el PNG)
        particella_file="$voz_dir/particella_${ficha_num}_voz${v}.pdf"
        if [ -f "$particella_file" ]; then
            echo "  [OK] (ficha${ficha_num} voz${v}) Encontrado: $particella_file"
        else
            echo "  [AVISO] (ficha${ficha_num} voz${v}) Falta archivo: particella_${ficha_num}_voz${v}.pdf en $voz_dir (hay: $(ls "$voz_dir" 2>/dev/null | tr '\n' ' '))"
        fi

        # 2c. Comprobar PNG derivado de la particella (lo que muestra el HTML)
        particella_png="$voz_dir/particella_${ficha_num}_voz${v}.png"
        if [ -f "$particella_png" ]; then
            echo "  [OK] (ficha${ficha_num} voz${v}) Encontrado: $particella_png"
        else
            echo "  [ERROR] (ficha${ficha_num} voz${v}) Falta archivo: particella_${ficha_num}_voz${v}.png en $voz_dir (hay: $(ls "$voz_dir" 2>/dev/null | tr '\n' ' '))"
            ficha_ok=0
        fi

        # 2d. Comprobar guia con numero de ficha y voz (pista vocal del reproductor)
        guia_file="$voz_dir/guia_${ficha_num}_voz_${v}.m4a"
        if [ -f "$guia_file" ]; then
            echo "  [OK] (ficha${ficha_num} voz${v}) Encontrado: $guia_file"
        else
            echo "  [ERROR] (ficha${ficha_num} voz${v}) Falta archivo: guia_${ficha_num}_voz_${v}.m4a en $voz_dir (hay: $(ls "$voz_dir" 2>/dev/null | tr '\n' ' '))"
            ficha_ok=0
        fi
    done
    
    if [ "$ficha_ok" -eq 1 ]; then
        echo "OK! $ficha_path está completo y correcto."
    else
        echo "FALLO en $ficha_path."
        overall_status=1
    fi
        echo ""
    done
done

exit "$overall_status"
