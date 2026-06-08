<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cargar .env manualmente
|--------------------------------------------------------------------------
*/

$envPath = dirname(__DIR__) . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '') {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Constantes de la app
|--------------------------------------------------------------------------
*/

define('APP_NAME', getenv('NAME_APP') ?: 'Divine');
define('PRECIO_ROPERO', getenv('PRECIO_ROPERO') ?: 'NO DEFINIDO');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');
define('APP_AUTHOR', getenv('APP_AUTHOR') ?: 'Nicko');