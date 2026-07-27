<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DIVINE APP - SETUP IDEMPOTENTE
|--------------------------------------------------------------------------
| - Busca una conexión válida.
| - Ejecuta db/init.sql sentencia por sentencia.
| - Crea tablas/columnas faltantes.
| - Inserta únicamente productos y usuarios faltantes.
| - Conserva los datos existentes.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

$expectedSetupKey = getenv('SETUP_KEY') ?: 'divine';
$setupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receivedSetupKey = (string) ($_POST['setup_key'] ?? '');

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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Setup - Divine App</title>
        <style>
            *{box-sizing:border-box}
            body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;font-family:Arial,sans-serif;background:radial-gradient(circle at top,rgba(176,124,255,.16),transparent 30%),linear-gradient(180deg,#120d18 0%,#09070d 100%);color:#fff}
            .setup-box{width:min(390px,94vw);padding:26px;border:1px solid rgba(240,212,141,.18);border-radius:22px;background:rgba(24,19,34,.96);box-shadow:0 20px 70px rgba(0,0,0,.35)}
            h1{margin:0 0 8px;text-align:center;color:#f0d48d;font-size:26px}
            p{text-align:center;color:#cfc6dc;font-size:14px;line-height:1.5;margin:0 0 22px}
            label{display:block;margin-bottom:8px;font-weight:700;font-size:14px}
            input{width:100%;padding:13px 14px;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(0,0,0,.28);color:#fff;outline:none;font-size:15px}
            input:focus{border-color:rgba(240,212,141,.55)}
            button{width:100%;margin-top:16px;padding:13px 14px;border:0;border-radius:14px;background:#f0d48d;color:#120d18;font-size:15px;font-weight:800;cursor:pointer}
            .error{margin-bottom:14px;padding:10px 12px;border:1px solid rgba(255,80,80,.25);border-radius:12px;background:rgba(255,80,80,.1);color:#ffb7b7;text-align:center;font-size:14px}
        </style>
    </head>
    <body>
        <form class="setup-box" method="POST" autocomplete="off">
            <h1>Divine Setup</h1>
            <p>Ingresá la clave para crear o completar la base sin borrar los datos existentes.</p>

            <?php if ($setupError !== ''): ?>
                <div class="error"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <label for="setup_key">Clave de setup</label>
            <input type="password" id="setup_key" name="setup_key" required autofocus>
            <button type="submit">Ejecutar configuración</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Divide un archivo SQL por punto y coma, respetando strings, backticks
 * y comentarios. El setup ejecuta cada sentencia por separado para poder
 * informar exactamente cuál falló.
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $state = 'normal';

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($state === 'line_comment') {
            if ($char === "\n") {
                $state = 'normal';
                $buffer .= "\n";
            }
            continue;
        }

        if ($state === 'block_comment') {
            if ($char === '*' && $next === '/') {
                $state = 'normal';
                $i++;
                $buffer .= ' ';
            }
            continue;
        }

        if ($state === 'single_quote' || $state === 'double_quote' || $state === 'backtick') {
            $buffer .= $char;

            if ($char === '\\' && $state !== 'backtick' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }

            $closing = match ($state) {
                'single_quote' => "'",
                'double_quote' => '"',
                default => '`',
            };

            if ($char === $closing) {
                if ($next === $closing) {
                    $buffer .= $sql[++$i];
                } else {
                    $state = 'normal';
                }
            }

            continue;
        }

        if ($char === '/' && $next === '*') {
            $state = 'block_comment';
            $i++;
            continue;
        }

        if ($char === '#') {
            $state = 'line_comment';
            continue;
        }

        if ($char === '-' && $next === '-') {
            $after = $i + 2 < $length ? $sql[$i + 2] : '';
            if ($after === '' || ctype_space($after)) {
                $state = 'line_comment';
                $i++;
                continue;
            }
        }

        if ($char === "'") {
            $state = 'single_quote';
            $buffer .= $char;
            continue;
        }

        if ($char === '"') {
            $state = 'double_quote';
            $buffer .= $char;
            continue;
        }

        if ($char === '`') {
            $state = 'backtick';
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function statementPreview(string $statement, int $limit = 220): string
{
    $preview = preg_replace('/\s+/', ' ', trim($statement)) ?: trim($statement);

    if (function_exists('mb_strlen') && mb_strlen($preview, 'UTF-8') > $limit) {
        return mb_substr($preview, 0, $limit, 'UTF-8') . '…';
    }

    return strlen($preview) > $limit
        ? substr($preview, 0, $limit) . '…'
        : $preview;
}


/**
 * Ejecuta una sentencia SQL y consume todos los resultados que pueda dejar.
 * Esto es importante para EXECUTE de sentencias preparadas dinámicamente:
 * si la sentencia devuelve filas, MySQL no permite continuar hasta cerrarlas.
 */
function executeSqlStatement(PDO $pdo, string $statement): void
{
    $result = $pdo->query($statement);

    if (!$result instanceof PDOStatement) {
        return;
    }

    try {
        do {
            if ($result->columnCount() > 0) {
                $result->fetchAll(PDO::FETCH_NUM);
            }
        } while ($result->nextRowset());
    } finally {
        $result->closeCursor();
    }
}

/** @return array<string, string> */
function parseDatabaseUrl(string $url): array
{
    if ($url === '') {
        return [];
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || empty($parts['user'])) {
        return [];
    }

    return [
        'host' => (string) $parts['host'],
        'port' => (string) ($parts['port'] ?? 3306),
        'db' => ltrim((string) ($parts['path'] ?? ''), '/'),
        'user' => urldecode((string) $parts['user']),
        'pass' => urldecode((string) ($parts['pass'] ?? '')),
    ];
}

/** @param array<string, string> $test */
function connectDatabase(array $test): PDO
{
    $host = $test['host'];
    $port = $test['port'];
    $db = $test['db'];
    $user = $test['user'];
    $pass = $test['pass'];

    if ($db === '' || !preg_match('/^[A-Za-z0-9_]+$/', $db)) {
        throw new RuntimeException('El nombre de la base es inválido o está vacío.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    // Evita el error 2014 cuando una sentencia devuelve filas y luego
    // se intenta ejecutar otra antes de cerrar el resultado anterior.
    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $error) {
        $unknownDatabase = (string) $error->getCode() === '1049'
            || str_contains($error->getMessage(), 'Unknown database');

        if (!$unknownDatabase) {
            throw $error;
        }

        $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($serverDsn, $user, $pass, $options);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$db}` " .
            "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $pdo->exec("USE `{$db}`");

        return $pdo;
    }
}

$tests = [];

$urlConfig = parseDatabaseUrl(
    getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: ''
);

if ($urlConfig !== []) {
    $tests[] = ['nombre' => 'URL de base / Railway'] + $urlConfig;
}

$envHost = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '';
$envPort = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';
$envDb = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: '';
$envUser = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: '';
$envPass = getenv('DB_PASSWORD')
    ?: getenv('DB_PASS')
    ?: getenv('MYSQLPASSWORD')
    ?: '';

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

$tests[] = [
    'nombre' => 'Docker interno: mysql:3306',
    'host' => 'mysql',
    'port' => '3306',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

$tests[] = [
    'nombre' => 'PC/XAMPP hacia Docker: 127.0.0.1:3307',
    'host' => '127.0.0.1',
    'port' => '3307',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

$tests[] = [
    'nombre' => 'Local clásico: localhost:3306',
    'host' => 'localhost',
    'port' => '3306',
    'db' => 'divine_db',
    'user' => 'usuario',
    'pass' => 'password',
];

// Evita probar dos veces exactamente la misma conexión.
$uniqueTests = [];
foreach ($tests as $test) {
    $key = implode('|', [
        $test['host'],
        $test['port'],
        $test['db'],
        $test['user'],
    ]);
    $uniqueTests[$key] ??= $test;
}
$tests = array_values($uniqueTests);

$pdo = null;
$selectedConnection = null;
$connectionLog = [];

foreach ($tests as $test) {
    $connectionLog[] = "Probando: {$test['nombre']}";

    try {
        $pdo = connectDatabase($test);
        $selectedConnection = $test;
        $connectionLog[] = '✅ CONEXIÓN OK';
        break;
    } catch (Throwable $error) {
        $connectionLog[] = '❌ ERROR: ' . $error->getMessage();
        $connectionLog[] = str_repeat('-', 60);
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divine App - Setup</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;padding:24px;font-family:Arial,sans-serif;background:radial-gradient(circle at top,rgba(176,124,255,.16),transparent 30%),linear-gradient(180deg,#120d18 0%,#09070d 100%);color:#fff}
        .box{max-width:980px;margin:0 auto;padding:24px;border:1px solid rgba(240,212,141,.18);border-radius:22px;background:rgba(24,19,34,.96);box-shadow:0 20px 70px rgba(0,0,0,.35)}
        h1,h2{color:#f0d48d} h1{margin-top:0}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(0,0,0,.35)}
        .ok,.error,.warn{padding:12px 14px;border-radius:14px;margin:12px 0}
        .ok{border:1px solid rgba(0,255,150,.18);background:rgba(0,255,150,.08)}
        .error{border:1px solid rgba(255,80,80,.22);background:rgba(255,80,80,.08);color:#ffb7b7}
        .warn{border:1px solid rgba(255,190,80,.2);background:rgba(255,190,80,.08);color:#ffe2a3}
        code{padding:3px 6px;border-radius:6px;background:rgba(0,0,0,.35);color:#f0d48d}
        a{color:#f0d48d;font-weight:700;text-decoration:none}
        ul{line-height:1.75}
    </style>
</head>
<body>
<div class="box">
<?php

if (!$pdo instanceof PDO || !is_array($selectedConnection)) {
    http_response_code(500);
    echo '<h1>❌ No se pudo conectar a MySQL</h1>';
    echo '<pre>' . h(implode(PHP_EOL, $connectionLog)) . '</pre>';
    echo '</div></body></html>';
    exit;
}

echo '<h1>✅ Divine App - Setup</h1>';
echo '<h2>1) Conexión</h2>';
echo '<pre>' . h(implode(PHP_EOL, $connectionLog)) . '</pre>';
echo '<div class="ok"><strong>Conexión usada:</strong> ' . h($selectedConnection['nombre']) . '</div>';

try {
    echo '<h2>2) Ejecutando db/init.sql</h2>';

    $initPath = realpath(__DIR__ . '/../db/init.sql');

    if ($initPath === false || !is_file($initPath)) {
        throw new RuntimeException(
            'No se encontró init.sql. Ruta esperada: ' . __DIR__ . '/../db/init.sql'
        );
    }

    $sql = file_get_contents($initPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('init.sql está vacío o no se pudo leer.');
    }

    $statements = splitSqlStatements($sql);
    if ($statements === []) {
        throw new RuntimeException('init.sql no contiene sentencias ejecutables.');
    }

    echo '<pre>';
    echo 'Archivo: ' . h($initPath) . PHP_EOL;
    echo 'SHA-1: ' . h((string) sha1_file($initPath)) . PHP_EOL;
    echo 'Sentencias detectadas: ' . count($statements);
    echo '</pre>';

    foreach ($statements as $index => $statement) {
        try {
            executeSqlStatement($pdo, $statement);
        } catch (Throwable $error) {
            $number = $index + 1;
            throw new RuntimeException(
                "Falló la sentencia SQL #{$number}: " .
                statementPreview($statement) . "\n\n" .
                $error->getMessage(),
                0,
                $error
            );
        }
    }

    // Asegura que esta sesión vuelva a validar claves foráneas.
    executeSqlStatement($pdo, 'SET FOREIGN_KEY_CHECKS = 1');

    echo '<div class="ok">✅ init.sql ejecutado completamente.</div>';

    echo '<h2>3) Usuarios base</h2>';

    $users = [
        ['username' => 'lopez', 'display_name' => 'Lopez', 'password' => 'lopez123', 'role' => 'admin'],
        ['username' => 'camila', 'display_name' => 'Camila', 'password' => 'camila123', 'role' => 'admin'],
        ['username' => 'nicolas', 'display_name' => 'Nicko', 'password' => 'nicolas123', 'role' => 'admin'],
        ['username' => 'publica', 'display_name' => 'Publica', 'password' => 'publica123', 'role' => 'usuario']
    ];

    $checkUser = $pdo->prepare(
        'SELECT id FROM users WHERE username = :username LIMIT 1'
    );
    $insertUser = $pdo->prepare(
        'INSERT INTO users (username, display_name, password_hash, role)
         VALUES (:username, :display_name, :password_hash, :role)'
    );

    echo '<ul>';
    foreach ($users as $user) {
        $checkUser->execute([':username' => $user['username']]);

        $userExists = (bool) $checkUser->fetchColumn();
        $checkUser->closeCursor();

        if ($userExists) {
            echo '<li>↷ Ya existía, se conservó: <code>' . h($user['username']) . '</code></li>';
            continue;
        }

        $insertUser->execute([
            ':username' => $user['username'],
            ':display_name' => $user['display_name'],
            ':password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
            ':role' => $user['role'],
        ]);

        echo '<li>✅ Creado: <code>' . h($user['username']) . '</code></li>';
    }
    echo '</ul>';

    echo '<h2>4) Verificación final</h2>';

    $expectedTables = [
        'users',
        'products',
        'door_lists',
        'door_people',
        'kiosko_sales',
        'kiosko_closings',
        'guardarropas',
        'app_logs',
        'user_remember_tokens',
        'container_stock_items',
        'container_stock_movements',
    ];

    $tableStatement = $pdo->query(
        'SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()'
    );
    $existingTables = $tableStatement->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_values(array_diff($expectedTables, $existingTables));

    $requiredColumns = [
        'products' => ['category_order', 'sort_order', 'custom', 'active'],
        'kiosko_sales' => ['client_sale_id'],
        'kiosko_closings' => ['deleted_at'],
        'container_stock_items' => ['sector', 'low_threshold', 'max_quantity'],
    ];

    $missingColumns = [];
    $columnCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );

    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $columnCheck->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);

            $columnExists = (int) $columnCheck->fetchColumn() > 0;
            $columnCheck->closeCursor();

            if (!$columnExists) {
                $missingColumns[] = "{$table}.{$column}";
            }
        }
    }

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stockCount = (int) $pdo->query('SELECT COUNT(*) FROM container_stock_items')->fetchColumn();

    echo '<ul>';
    foreach ($expectedTables as $table) {
        $exists = in_array($table, $existingTables, true);
        echo '<li>' . ($exists ? '✅' : '❌') . ' <code>' . h($table) . '</code></li>';
    }
    echo '</ul>';

    echo '<div class="ok">';
    echo '<strong>Productos:</strong> ' . $productCount . '<br>';
    echo '<strong>Artículos de stock:</strong> ' . $stockCount;
    echo '</div>';

    if ($missingTables !== [] || $missingColumns !== []) {
        $details = [];
        if ($missingTables !== []) {
            $details[] = 'Tablas faltantes: ' . implode(', ', $missingTables);
        }
        if ($missingColumns !== []) {
            $details[] = 'Columnas faltantes: ' . implode(', ', $missingColumns);
        }
        throw new RuntimeException(implode(' | ', $details));
    }

    echo '<div class="ok"><strong>✅ Base completa y verificada.</strong></div>';
    echo '<p><a href="login.php">Ir al login</a></p>';
    echo '<div class="warn"><strong>Seguridad:</strong> eliminá o protegé este archivo cuando termines el setup en producción.</div>';
} catch (Throwable $error) {
    try {
        executeSqlStatement($pdo, 'SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable) {
        // No reemplaza el error original.
    }

    http_response_code(500);
    echo '<h1>❌ Error en setup.php</h1>';
    echo '<div class="error"><strong>Mensaje:</strong><pre>' . h($error->getMessage()) . '</pre></div>';
}

?>
</div>
</body>
</html>
