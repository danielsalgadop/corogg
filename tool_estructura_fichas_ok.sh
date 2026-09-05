#!/usr/bin/env bash

set -uo pipefail

overall_status=0

for ficha_dir in ficha*/; do
    [ -d "$ficha_dir" ] || continue
    
    ficha_name="${ficha_dir%/}"
    ficha_num="${ficha_name#ficha}"
    
    # Remove leading/trailing slashes for clean path operations
    ficha_path="${ficha_dir%/}"
    
    ficha_ok=1
    
    echo "--------------------------------------------------"
    echo "Comprobando: $ficha_path"
    
    # 1. Comprobar guitarra*.mp3 donde * coincide con el número del folder
    guitarra_file="$ficha_path/guitarra${ficha_num}.mp3"
    if [ -f "$guitarra_file" ]; then
        echo "  [OK] Archivo encontrado: $guitarra_file"
    else
        echo "  [ERROR] Falta archivo esperado: $guitarra_file"
        ficha_ok=0
    fi
    
    # 2. Comprobar folders voz1 hasta voz3
    for v in 1 2 3; do
        voz_dir="$ficha_path/voz$v"
        
        if [ ! -d "$voz_dir" ]; then
            echo "  [ERROR] Falta el directorio: $voz_dir"
            ficha_ok=0
            continue
        fi
        
        # 2a. Comprobar voz%.html dentro de voz%
        voz_html="$voz_dir/voz${v}.html"
        if [ -f "$voz_html" ]; then
            echo "  [OK] Encontrado: $voz_html"
        else
            echo "  [ERROR] Falta archivo: $voz_html"
            ficha_ok=0
        fi
        
        # 2b. Comprobar particella_*_voz% donde * es número y coincide con ficha_num, y % es v
        particella_file="$voz_dir/particella_${ficha_num}_voz${v}.pdf"
        if [ -f "$particella_file" ]; then
            echo "  [OK] Encontrado: $particella_file"
        else
            echo "  [ERROR] Falta archivo: $particella_file"
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

exit "$overall_status"
