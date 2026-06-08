<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DIVINE APP - SETUP COMPLETO CON CLAVE WEB
|--------------------------------------------------------------------------
| Este archivo hace TODO en uno:
|
| 1) Pide una clave desde la web.
| 2) Prueba varias conexiones posibles a MySQL.
| 3) Usa automáticamente la primera conexión que funcione.
| 4) Ejecuta db/init.sql.
| 5) Crea o actualiza usuarios base.
|
| Abrir en:
| http://localhost:8080/setup_user.php
|
| Clave por defecto:
| divine-setup-123
|
| En Railway podés crear una variable:
| SETUP_KEY=tu-clave-secreta
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

/*
|--------------------------------------------------------------------------
| PROTECCIÓN CON CLAVE DESDE FORMULARIO WEB
|--------------------------------------------------------------------------
*/

$expectedSetupKey = getenv('SETUP_KEY') ?: 'divine';
$setupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receivedSetupKey = $_POST['setup_key'] ?? '';

    if (hash_equals($expectedSetupKey, $receivedSetupKey)) {
        $_SESSION['setup_authorized'] = true;
    } else {
        http_response_code(403);
        $setupError = 'Clave incorrecta.';
    }
}

if (empty($_SESSION['setup_authorized'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso Setup - Divine App</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: Arial, sans-serif;
                background:
                    radial-gradient(circle at top, rgba(176, 124, 255, 0.16), transparent 30%),
                    linear-gradient(180deg, #120d18 0%, #09070d 100%);
                color: #fff;
                padding: 20px;
            }

            .setup-box {
                width: min(380px, 92vw);
                background: rgba(24, 19, 34, 0.96);
                border: 1px solid rgba(240, 212, 141, 0.18);
                border-radius: 22px;
                padding: 26px;
                box-shadow: 0 20px 70px rgba(0,0,0,.35);
            }

            h1 {
                margin: 0 0 8px;
                text-align: center;
                color: #f0d48d;
                font-size: 26px;
            }

            p {
                text-align: center;
                color: #cfc6dc;
                font-size: 14px;
                margin-bottom: 22px;
            }

            label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
                font-size: 14px;
            }

            input {
                width: 100%;
                box-sizing: border-box;
                padding: 13px 14px;
                border-radius: 14px;
                border: 1px solid rgba(255,255,255,.12);
                background: rgba(0,0,0,.28);
                color: #fff;
                outline: none;
                font-size: 15px;
            }

            input:focus {
                border-color: rgba(240, 212, 141, 0.55);
            }

            button {
                width: 100%;
                margin-top: 16px;
                padding: 13px 14px;
                border: 0;
                border-radius: 14px;
                background: #f0d48d;
                color: #120d18;
                font-weight: bold;
                cursor: pointer;
                font-size: 15px;
            }

            button:hover {
                filter: brightness(1.05);
            }

            .error {
                background: rgba(255, 80, 80, 0.1);
                border: 1px solid rgba(255, 80, 80, 0.25);
                color: #ffb7b7;
                padding: 10px 12px;
                border-radius: 12px;
                margin-bottom: 14px;
                text-align: center;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <form class="setup-box" method="POST" autocomplete="off">
            <h1>Divine Setup</h1>
            <p>Ingresá la clave para ejecutar la configuración de la base.</p>

            <?php if ($setupError !== ''): ?>
                <div class="error">
                    <?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <label for="setup_key">Clave de setup</label>

            <input
                type="password"
                id="setup_key"
                name="setup_key"
                placeholder="Ingresá la clave"
                required
                autofocus
            >

            <button type="submit">Entrar al setup</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CONEXIONES A PROBAR
|--------------------------------------------------------------------------
| Agregué una conexión por variables de entorno para Railway.
| Después quedan las locales de Docker/XAMPP.
|--------------------------------------------------------------------------
*/

$tests = [];

/*
|--------------------------------------------------------------------------
| 1) Railway / Producción por variables de entorno
|--------------------------------------------------------------------------
*/

$envHost = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '';
$envPort = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';
$envDb   = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: '';
$envUser = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: '';
$envPass = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '';

if ($envHost !== '' && $envDb !== '' && $envUser !== '') {
    $tests[] = [
        'nombre' => 'Variables de entorno / Railway',
        'host' => $envHost,
        'port' => $envPort,
        'db' => $envDb,
        'user' => $envUser,
        'pass' => $envPass,
    ];
}

/*
|--------------------------------------------------------------------------
| 2) Docker interno local
|--------------------------------------------------------------------------
*/

$tests[] = [
    'nombre' => 'Docker interno: mysql:3306',
    'host' => 'mysql',
    'port' => '3306',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

/*
|--------------------------------------------------------------------------
| 3) Desde PC / XAMPP hacia Docker
|--------------------------------------------------------------------------
*/

$tests[] = [
    'nombre' => 'Desde PC/XAMPP: 127.0.0.1:3307',
    'host' => '127.0.0.1',
    'port' => '3307',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

/*
|--------------------------------------------------------------------------
| 4) Local clásico
|--------------------------------------------------------------------------
*/

$tests[] = [
    'nombre' => 'Local clásico: localhost:3306',
    'host' => 'localhost',
    'port' => '3306',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

/*
|--------------------------------------------------------------------------
| VARIABLES PRINCIPALES
|--------------------------------------------------------------------------
*/

$pdo = null;
$selectedConnection = null;
$connectionLog = [];

/*
|--------------------------------------------------------------------------
| PROBAR CONEXIONES
|--------------------------------------------------------------------------
*/

foreach ($tests as $test) {
    $connectionLog[] = "Probando: {$test['nombre']}";

    try {
        $dsn = "mysql:host={$test['host']};port={$test['port']};dbname={$test['db']};charset=utf8mb4";

        $testPdo = new PDO($dsn, $test['user'], $test['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $connectionLog[] = "✅ CONEXIÓN OK";

        $stmt = $testPdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($tables) {
            $connectionLog[] = "Tablas encontradas:";

            foreach ($tables as $table) {
                $connectionLog[] = " - {$table}";
            }
        } else {
            $connectionLog[] = "No hay tablas todavía.";
        }

        if ($pdo === null) {
            $pdo = $testPdo;
            $selectedConnection = $test;
        }

    } catch (Throwable $e) {
        $connectionLog[] = "❌ ERROR: " . $e->getMessage();
    }

    $connectionLog[] = str_repeat('-', 60);
}

/*
|--------------------------------------------------------------------------
| HTML PRINCIPAL
|--------------------------------------------------------------------------
*/

echo '<!DOCTYPE html>';
echo '<html lang="es">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Divine App - Setup</title>';

echo '<style>
    body {
        margin: 0;
        min-height: 100vh;
        font-family: Arial, sans-serif;
        background:
            radial-gradient(circle at top, rgba(176, 124, 255, 0.16), transparent 30%),
            linear-gradient(180deg, #120d18 0%, #09070d 100%);
        color: #fff;
        padding: 24px;
    }

    .box {
        max-width: 950px;
        margin: 0 auto;
        background: rgba(24, 19, 34, 0.96);
        border: 1px solid rgba(240, 212, 141, 0.18);
        border-radius: 22px;
        padding: 24px;
        box-shadow: 0 20px 70px rgba(0,0,0,.35);
    }

    h1, h2 {
        color: #f0d48d;
    }

    pre {
        white-space: pre-wrap;
        background: rgba(0,0,0,.35);
        border: 1px solid rgba(255,255,255,.08);
        padding: 16px;
        border-radius: 14px;
        overflow-x: auto;
    }

    .ok {
        background: rgba(0, 255, 150, 0.08);
        border: 1px solid rgba(0, 255, 150, 0.18);
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 10px;
    }

    .error {
        background: rgba(255, 80, 80, 0.08);
        border: 1px solid rgba(255, 80, 80, 0.22);
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 10px;
    }

    .warn {
        background: rgba(255, 190, 80, 0.08);
        border: 1px solid rgba(255, 190, 80, 0.2);
        padding: 12px 14px;
        border-radius: 14px;
        margin-top: 18px;
        color: #ffe2a3;
    }

    a {
        color: #f0d48d;
        text-decoration: none;
        font-weight: bold;
    }

    code {
        background: rgba(0,0,0,.35);
        padding: 3px 6px;
        border-radius: 6px;
        color: #f0d48d;
    }

    ul {
        line-height: 1.7;
    }
</style>';

echo '</head>';
echo '<body>';
echo '<div class="box">';

/*
|--------------------------------------------------------------------------
| SI NINGUNA CONEXIÓN FUNCIONÓ
|--------------------------------------------------------------------------
*/

if (!$pdo || !$selectedConnection) {
    echo '<h1>❌ No se pudo conectar a MySQL</h1>';
    echo '<p>No funcionó ninguna de las conexiones configuradas.</p>';
    echo '<pre>' . h(implode(PHP_EOL, $connectionLog)) . '</pre>';
    echo '</div></body></html>';
    exit;
}

/*
|--------------------------------------------------------------------------
| MOSTRAR CONEXIÓN USADA
|--------------------------------------------------------------------------
*/

echo '<h1>✅ Divine App - Setup completo</h1>';

echo '<h2>1) Resultado de conexiones</h2>';
echo '<pre>' . h(implode(PHP_EOL, $connectionLog)) . '</pre>';

echo '<div class="ok">';
echo '<strong>Conexión usada:</strong> ' . h($selectedConnection['nombre']);
echo '</div>';

try {
    /*
    |--------------------------------------------------------------------------
    | EJECUTAR init.sql
    |--------------------------------------------------------------------------
    */

    echo '<h2>2) Ejecutando init.sql</h2>';

    $initPath = __DIR__ . '/../db/init.sql';

    if (!file_exists($initPath)) {
        throw new RuntimeException('No se encontró init.sql en: ' . $initPath);
    }

    $sql = file_get_contents($initPath);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('init.sql está vacío o no se pudo leer.');
    }

    $pdo->exec($sql);

    echo '<div class="ok">✅ init.sql ejecutado correctamente.</div>';

    /*
    |--------------------------------------------------------------------------
    | USUARIOS BASE
    |--------------------------------------------------------------------------
    */

    echo '<h2>3) Creando / actualizando usuarios</h2>';

    $users = [
        [
            'username' => 'camila',
            'display_name' => 'Camila',
            'password' => 'camila123',
            'role' => 'admin',
        ],
        [
            'username' => 'nicolas',
            'display_name' => 'Nicko',
            'password' => 'nicolas123',
            'role' => 'admin',
        ],
        [
            'username' => 'lopez',
            'display_name' => 'Lopez',
            'password' => 'lopez123',
            'role' => 'admin',
        ],
        [
            'username' => 'publica',
            'display_name' => 'Publica',
            'password' => 'publica123',
            'role' => 'usuario',
        ],
        [
            'username' => 'candelaria',
            'display_name' => 'Candelaria',
            'password' => 'candelaria123',
            'role' => 'usuario',
        ],
    ];

    $check = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $insert = $pdo->prepare("
        INSERT INTO users
        (username, display_name, password_hash, role)
        VALUES
        (:username, :display_name, :password_hash, :role)
    ");

    $update = $pdo->prepare("
        UPDATE users
        SET display_name = :display_name,
            password_hash = :password_hash,
            role = :role
        WHERE username = :username
    ");

    echo '<ul>';

    foreach ($users as $user) {
        $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);

        $check->execute([
            ':username' => $user['username'],
        ]);

        $exists = $check->fetchColumn();

        if ($exists) {
            $update->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $passwordHash,
                ':role' => $user['role'],
            ]);

            echo '<li>✔ Actualizado: <code>' . h($user['username']) . '</code> - rol: <code>' . h($user['role']) . '</code></li>';
        } else {
            $insert->execute([
                ':username' => $user['username'],
                ':display_name' => $user['display_name'],
                ':password_hash' => $passwordHash,
                ':role' => $user['role'],
            ]);

            echo '<li>✅ Creado: <code>' . h($user['username']) . '</code> - rol: <code>' . h($user['role']) . '</code></li>';
        }
    }

    echo '</ul>';

    /*
    |--------------------------------------------------------------------------
    | TABLAS FINALES
    |--------------------------------------------------------------------------
    */

    echo '<h2>4) Tablas finales en la base</h2>';

    $stmt = $pdo->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($finalTables) {
        echo '<ul>';

        foreach ($finalTables as $table) {
            echo '<li><code>' . h($table) . '</code></li>';
        }

        echo '</ul>';
    } else {
        echo '<div class="error">No se encontraron tablas después del setup.</div>';
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL OK
    |--------------------------------------------------------------------------
    */

    echo '<div class="ok">';
    echo '<strong>✅ Base y usuarios creados/actualizados correctamente.</strong>';
    echo '</div>';

    echo '<p><a href="login.php">Ir al login</a></p>';

    echo '<div class="warn">';
    echo '<strong>Importante:</strong> después de usar este archivo, podés borrarlo o dejarlo solo en local. ';
    echo 'No conviene dejar archivos de setup abiertos públicamente en producción.';
    echo '</div>';

} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>❌ Error en setup_user.php</h1>';

    echo '<div class="error">';
    echo '<strong>Mensaje:</strong><br>';
    echo '<code>' . h($e->getMessage()) . '</code>';
    echo '</div>';
}

echo '</div>';
echo '</body>';
echo '</html>';