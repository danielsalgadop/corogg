#!/bin/bash

cd "$(dirname "$0")/.." || exit 1

# Inicializar variable por defecto (falso significa que ejecuta los cambios reales)
DRY_RUN=false
TARGET_DIR="."

# Procesar los argumentos pasados por comando
while [[ $# -gt 0 ]]; do
    case "$1" in
        -d|--dry-run)
            DRY_RUN=true
            shift # Mover al siguiente argumento
            ;;
        -*)
            echo "Opción inválida: $1" >&2
            echo "Uso: $0 [-d|--dry-run] [directorio]" >&2
            exit 1
            ;;
        *)
            # Si no empieza por "-", asumimos que es el directorio objetivo
            TARGET_DIR="$1"
            shift
            ;;
    esac
done

# Convertir la ruta del objetivo en una ruta absoluta completa
TARGET_DIR=$(cd "$TARGET_DIR" && pwd)

# Archivo temporal para guardar el registro de cambios
LOG_FILE=$(mktemp)
CONTADOR=0

if [ "$DRY_RUN" = true ]; then
    echo "⚠️ MODO SIMULACIÓN ACTIVADO (No se aplicará ningún cambio real) ⚠️"
fi
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
        ext=".${base#*.}"
        nombre_sin_ext="${base%%.*}"
    else
        ext=""
        nombre_sin_ext="$base"
    fi

    # 1. Cambiar espacios por guiones bajos en el nombre
    nuevo_nombre=$(echo "$nombre_sin_ext" | sed 's/ /_/g')

    # 2. Reemplazar tildes usando sed (soporta UTF-8)
    nuevo_nombre=$(echo "$nuevo_nombre" | sed \
        -e 's/á/a/g' -e 's/é/e/g' -e 's/í/i/g' -e 's/ó/o/g' -e 's/ú/u/g' \
        -e 's/Á/A/g' -e 's/É/E/g' -e 's/Í/I/g' -e 's/Ó/O/g' -e 's/Ú/U/g')

    # 3. Convertir solo el nombre a minúsculas
    nuevo_nombre=$(echo "$nuevo_nombre" | sed 's/\(.*\)/\L\1/')

    # Volver a unir el nombre procesado con su extensión original intacta
    nuevo_base="${nuevo_nombre}${ext}"

    # Si el nombre cambia, procedemos a simular o renombrar
    if [ "$base" != "$nuevo_base" ]; then
        if [ "$DRY_RUN" = true ] || mv "$elemento" "$dir/$nuevo_base" 2>/dev/null; then
            # Guardar en el archivo de registro usando la ruta completa
            echo "De: $elemento" >> "$LOG_FILE"
            echo "A:  $dir/$nuevo_base" >> "$LOG_FILE"
            echo "--------------------------------------------------" >> "$LOG_FILE"
            
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
if [ "$DRY_RUN" = true ]; then
    echo "       RESUMEN DE CAMBIOS (SIMULADOS)             "
else
    echo "                RESUMEN DE CAMBIOS                "
fi
echo "=================================================="

if [ "$TOTAL_CAMBIOS" -gt 0 ]; then
    if [ "$DRY_RUN" = true ]; then
        echo "Se habrían modificado un total de $TOTAL_CAMBIOS elemento(s):"
    else
        echo "Se han modificado un total de $TOTAL_CAMBIOS elemento(s):"
    fi
    echo "--------------------------------------------------"
    cat "$LOG_FILE"
else
    echo "No se encontraron archivos o carpetas que requirieran cambios."
    echo "=================================================="
fi

# Limpiar el archivo log temporal
rm -f "$LOG_FILE"
