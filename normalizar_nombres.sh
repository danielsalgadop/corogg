#!/bin/bash

# Verificar si se pasó un directorio; si no, usar el actual
TARGET_DIR="${1:-.}"

# Convertir la ruta del objetivo en una ruta absoluta completa
TARGET_DIR=$(cd "$TARGET_DIR" && pwd)

# Archivo temporal para guardar el registro de cambios
LOG_FILE=$(mktemp)
CONTADOR=0

echo "Iniciando normalización de nombres en: $TARGET_DIR"
echo "--------------------------------------------------"

# Usamos -prune para ignorar por completo carpetas ocultas y su contenido
find "$TARGET_DIR" -depth -name ".*" -prune -o \( -name "* *" -o -name "*[áéíóúÁÉÍÓÚ]*" -o -name "*[A-Z]*" \) -print | while read -r elemento; do
    
    # Doble verificación por seguridad: saltar si la ruta absoluta contiene un elemento oculto
    if [[ "$elemento" =~ \/\. ]]; then
        continue
    fi

    # Obtener el directorio y el nombre base del elemento
    dir=$(dirname "$elemento")
    base=$(basename "$elemento")

    # Separar nombre y extensión (funciona incluso con extensiones compuestas como .tar.gz)
    if [[ "$base" == *.* ]] && [ -f "$elemento" ]; then
        # Extraer la extensión (todo lo que va después del primer punto del final)
        ext=".${base#*.}"
        # Extraer el nombre limpio sin extensión
        nombre_sin_ext="${base%%.*}"
    else
        # Si es una carpeta o un archivo sin extensión
        ext=""
        nombre_sin_ext="$base"
    fi

    # 1. Cambiar espacios por guiones bajos en el nombre
    nuevo_nombre=$(echo "$nombre_sin_ext" | sed 's/ /_/g')

    # 2. Reemplazar tildes usando sed (soporta UTF-8)
    nuevo_nombre=$(echo "$nuevo_nombre" | sed \
        -e 's/á/a/g' -e 's/é/e/g' -e 's/í/i/g' -e 's/ó/o/g' -e 's/ú/u/g' \
        -e 's/Á/A/g' -e 's/É/E/g' -e 's/Í/I/g' -e 's/Ó/O/g' -e 's/Ú/U/g')

    # 3. Convertir solo el nombre a minúsculas (\L pasa a minúsculas el resto)
    nuevo_nombre=$(echo "$nuevo_nombre" | sed 's/\(.*\)/\L\1/')

    # Volver a unir el nombre procesado con su extensión original intacta
    nuevo_base="${nuevo_nombre}${ext}"

    # Renombrar si el nombre cambia y registrar el movimiento con ruta completa
    if [ "$base" != "$nuevo_base" ]; then
        if mv "$elemento" "$dir/$nuevo_base" 2>/dev/null; then
            # Guardar en el archivo de registro usando la ruta completa
            echo "De: $elemento" >> "$LOG_FILE"
            echo "A:  $dir/$nuevo_base" >> "$LOG_FILE"
            echo "--------------------------------------------------" >> "$LOG_FILE"
            
            # Incrementar el contador interno del subshell
            ((CONTADOR++))
            echo "$CONTADOR" > /tmp/script_count.tmp
        fi
    fi
done

# Leer el total de cambios desde el archivo temporal del contador
TOTAL_CAMBIOS=$(cat /tmp/script_count.tmp 2>/dev/null || echo 0)
rm -f /tmp/script_count.tmp

# Mostrar el resumen final
echo -e "\n=================================================="
echo "                RESUMEN DE CAMBIOS                "
echo "=================================================="

if [ "$TOTAL_CAMBIOS" -gt 0 ]; then
    echo "Se han modificado un total de $TOTAL_CAMBIOS elemento(s):"
    echo "--------------------------------------------------"
    cat "$LOG_FILE"
else
    echo "No se encontraron archivos o carpetas que requirieran cambios."
    echo "=================================================="
fi

# Limpiar el archivo log temporal
rm -f "$LOG_FILE"
