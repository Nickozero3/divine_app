<?php

declare(strict_types=1);

/**
 * Devuelve una versión estable basada en la fecha de modificación del recurso.
 * El navegador puede cachear el archivo hasta que realmente cambie.
 */
function asset_version(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');

    return is_file($path) ? (string) filemtime($path) : '1';
}
