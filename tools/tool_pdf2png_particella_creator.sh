#!/bin/bash

cd "$(dirname "$0")/.." || exit 1

# Busca recursivamente carpetas ficha dentro de cada cancion en songs
find songs -mindepth 2 -type d -name "ficha*" | while read -r carpeta; do
    echo "Procesando carpeta: $carpeta"
    
    # Busca los PDFs dentro de la carpeta encontrada
    find "$carpeta" -type f -name "*.pdf" | while read -r pdf; do
        # Obtiene la ruta y el nombre sin la extensión .pdf
        base_name="${pdf%.pdf}"
        output_png="${base_name}.png"
        
        if [ -f "$output_png" ]; then
            echo " -> Omitiendo, ya existe: $output_png"
            continue
        fi

        echo " -> Recortando: $pdf"

        # Ejecuta el comando usando -singlefile para que la salida sea exactamente "nombre.png"
        pdftoppm -png -singlefile -x 0 -y 0 -W 2000 -H 400 -r 150 "$pdf" "$base_name"
    done
done

echo "¡Proceso finalizado!"

