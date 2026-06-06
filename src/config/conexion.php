<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cargar .env manualmente
|--------------------------------------------------------------------------
| Esto sirve si el .env está en la carpeta padre o en la raíz del proyecto.
*/

function load_env_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

load_env_file(__DIR__ . '/../../.env');
load_env_file(__DIR__ . '/../.env');
load_env_file(__DIR__ . '/.env');

function env_value(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

$appEnv = (string) env_value('APP_ENV', 'development');

/*
|--------------------------------------------------------------------------
| Variables de conexión
|--------------------------------------------------------------------------
*/

$dbHost = (string) env_value(
    'DB_HOST',
    env_value('MYSQLHOST', 'mysql')
);

$dbPort = (string) env_value(
    'DB_PORT',
    env_value('MYSQLPORT', '3306')
);

$dbName = (string) env_value(
    'DB_NAME',
    env_value('MYSQLDATABASE', env_value('MYSQL_DATABASE', 'divine_db'))
);

$dbUser = (string) env_value(
    'DB_USER',
    env_value('MYSQLUSER', env_value('MYSQL_USER', 'usuario'))
);

$dbPass = (string) env_value(
    'DB_PASSWORD',
    env_value('MYSQLPASSWORD', env_value('MYSQL_PASSWORD', env_value('DB_PASS', 'password')))
);

/*
|--------------------------------------------------------------------------
| Pantalla de carga si la DB está dormida
|--------------------------------------------------------------------------
*/

function show_database_loading_screen(): never
{
    http_response_code(503);

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    if (str_contains($accept, 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'ok' => false,
            'loading' => true,
            'message' => 'La base de datos está iniciando. Reintentá en unos segundos.'
        ]);

        exit;
    }

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Iniciando sistema</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 20px;
                background:
                    radial-gradient(circle at top, rgba(176, 124, 255, 0.18), transparent 34%),
                    radial-gradient(circle at 80% 10%, rgba(240, 212, 141, 0.12), transparent 26%),
                    linear-gradient(180deg, #120d18 0%, #0c0a12 55%, #09070d 100%);
                font-family: Arial, sans-serif;
                color: white;
            }

            .loading-box {
                width: min(380px, 92vw);
                padding: 30px 26px;
                border-radius: 24px;
                text-align: center;
                background: linear-gradient(
                    135deg,
                    rgba(24, 19, 34, 0.98),
                    rgba(18, 14, 26, 0.98)
                );
                border: 1px solid rgba(240, 212, 141, 0.16);
                box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
            }

            .spinner {
                width: 48px;
                height: 48px;
                margin: 0 auto 20px;
                border-radius: 50%;
                border: 4px solid rgba(255, 255, 255, 0.16);
                border-top-color: #f0d48d;
                animation: spin 0.9s linear infinite;
            }

            h1 {
                margin: 0 0 10px;
                font-size: 25px;
            }

            p {
                margin: 0;
                color: #cfc7d8;
                line-height: 1.45;
                font-size: 15px;
            }

            .small {
                margin-top: 14px;
                font-size: 13px;
                color: #9f95ad;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>

        <script>
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        </script>
    </head>

    <body>
        <div class="loading-box">
            <div class="spinner"></div>
            <h1>Iniciando sistema</h1>
            <p>La base de datos se está despertando. La página se va a recargar automáticamente.</p>
            <div class="small">No cierres la pestaña.</div>
        </div>
    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| Reintento de conexión
|--------------------------------------------------------------------------
| Intenta conectarse durante unos segundos.
| Si no puede, muestra pantalla de carga y recarga sola.
|--------------------------------------------------------------------------
*/

$maxRetrySeconds = 1;
$retryDelayMicroseconds = 250000; // 0.5 segundos

$startTime = microtime(true);
$lastError = null;

while (true) {
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 1,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);

        break;

    } catch (PDOException $e) {
        $lastError = $e;

        $elapsed = microtime(true) - $startTime;

        if ($elapsed >= $maxRetrySeconds) {
            show_database_loading_screen();
        }

        usleep($retryDelayMicroseconds);
    }
}