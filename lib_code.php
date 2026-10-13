<?php

declare(strict_types=1);

/**
 * Librería compartida para las herramientas de generación (tool_*.php).
 *
 * Agrupa lógica reutilizable en métodos estáticos: parseo de argumentos de
 * consola, escape HTML y escritura de archivos respetando la bandera --force.
 */
final class LibCode
{
    /**
     * Parsea argumentos de consola para un tool de generacion.
     *
     * La bandera --force es comun a todos los tools: se reconoce siempre y
     * se devuelve extraida aparte, sin que el tool deba declararla. Acepta
     * tambien el alias corto -f.
     *
     * @param string[] $argv Argumentos (sin el nombre del script).
     * @param array<string, string> $spec Mapa "flag" => 'bool' | 'value' (sin 'force').
     * @return array{0: array<string, bool|string>, 1: bool} [$args, $force]
     */
    public static function parseToolArgs(array $argv, array $spec): array
    {
        $spec['force'] ??= 'bool';

        $normalized = [];
        foreach ($argv as $argument) {
            $normalized[] = $argument === '-f' ? '--force' : $argument;
        }

        $args = self::parseArgs($normalized, $spec);
        $force = (bool) $args['force'];

        return [$args, $force];
    }

    /**
     * Parsea argumentos de consola.
     *
     * @param string[] $argv Argumentos (sin el nombre del script).
     * @param array<string, string> $spec Mapa "flag" => 'bool' | 'value'.
     * @return array<string, bool|string> Flags parseados.
     */
    public static function parseArgs(array $argv, array $spec): array
    {
        $result = [];
        foreach ($spec as $name => $type) {
            $result[$name] = $type === 'value' ? null : false;
        }

        foreach ($argv as $argument) {
            if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $argument, $matches) === 1) {
                $name = $matches[1];
                $hasValue = array_key_exists(2, $matches);
                $value = $hasValue ? $matches[2] : null;

                if (!array_key_exists($name, $spec)) {
                    throw new InvalidArgumentException("Opcion no reconocida: --{$name}");
                }

                if ($spec[$name] === 'bool') {
                    if ($hasValue) {
                        throw new InvalidArgumentException("--{$name} no admite valor.");
                    }
                    $result[$name] = true;
                    continue;
                }

                if (!$hasValue) {
                    throw new InvalidArgumentException("--{$name} requiere un valor (--{$name}=X).");
                }
                $result[$name] = $value;
                continue;
            }

            throw new InvalidArgumentException("Opcion no reconocida: {$argument}");
        }

        return $result;
    }

    /**
     * Lee una opcion de tipo valor y valida que cumpla el patron dado.
     *
     * @param array<string, bool|string> $args
     * @return string|null El valor si esta presente y valido, null si no se paso.
     */
    public static function value(array $args, string $name, string $pattern, string $description): ?string
    {
        $raw = $args[$name];
        if ($raw === null) {
            return null;
        }

        $raw = (string) $raw;
        if (preg_match($pattern, $raw) !== 1) {
            throw new InvalidArgumentException("{$description} invalida: --{$name}={$raw}");
        }

        return $raw;
    }

    public static function bool(array $args, string $name): bool
    {
        return (bool) $args[$name];
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Indica si dos cadenas son suficientemente parecidas.
     *
     * El umbral es una proporción entre 0.0 (cualquier diferencia) y 1.0
     * (coincidencia exacta). La comparación está pensada para nombres de
     * archivo y usa distancia Levenshtein.
     */
    public static function isStringSimilar(string $value, string $candidate, float $threshold = 0.8): bool
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException('El umbral debe estar entre 0.0 y 1.0.');
        }

        $value = strtolower($value);
        $candidate = strtolower($candidate);
        $longestLength = max(strlen($value), strlen($candidate));

        if ($longestLength === 0) {
            return true;
        }

        $similarity = 1 - (levenshtein($value, $candidate) / $longestLength);
        return $similarity >= $threshold;
    }

    /**
     * Detecta la categoría de un fichero según su nombre.
     *
     * @return 'voz'|'particella'|'guitarra'|null
     */
    public static function detectFileType(string $filename, float $threshold = 0.8): ?string
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException('El umbral debe estar entre 0.0 y 1.0.');
        }

        $basename = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        preg_match_all('/[a-z]+/i', $basename, $matches);
        $tokens = $matches[0];
        $fileTypes = ['particella', 'guitarra', 'voz'];

        foreach ($fileTypes as $type) {
            foreach ($tokens as $token) {
                if (self::isStringSimilar($token, $type, $threshold)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Escribe un archivo respetando la bandera --force.
     *
     * @return 'generated'|'skipped'|'dry-run'
     */
    public static function writeIfChanged(string $path, string $content, bool $force, bool $dryRun): string
    {
        if (is_file($path) && !$force) {
            echo "Ya existe, no se modifica: {$path}\n";
            return 'skipped';
        }

        if ($dryRun) {
            echo "Se generaria: {$path}\n";
            return 'dry-run';
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear {$dir}");
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("No se pudo escribir {$path}");
        }

        chmod($path, 0644);

        echo "Generado: {$path}\n";
        return 'generated';
    }
}
