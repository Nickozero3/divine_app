<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DIVINE APP - HEALTH CHECK
|--------------------------------------------------------------------------
| Guardar como:
| src/health.php
|
| Abrir:
| http://localhost:8080/health.php
|
| Si usás HEALTH_TOKEN en .env:
| http://localhost:8080/health.php?token=TU_TOKEN
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
error_reporting(E_ALL);

$startedAt = microtime(true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================================
   HELPERS VISUALES
========================================================= */

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status): string
{
    $labels = [
        'ok' => 'OK',
        'warn' => 'AVISO',
        'bad' => 'ERROR',
    ];

    $label = $labels[$status] ?? strtoupper($status);

    return '<span class="badge badge-' . h($status) . '">' . h($label) . '</span>';
}

function starts_with_any(string $value, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($value, $prefix)) {
            return true;
        }
    }

    return false;
}

function load_env_file_for_health(string $path): array
{
    $env = [];

    if (!file_exists($path) || !is_readable($path)) {
        return $env;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return $env;
    }

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
            $env[$key] = $value;

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }

            $_ENV[$key] = $_ENV[$key] ?? $value;
            $_SERVER[$key] = $_SERVER[$key] ?? $value;
        }
    }

    return $env;
}

function env_read(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function add_result(array &$results, string $section, string $name, string $status, string $message): void
{
    $results[] = [
        'section' => $section,
        'name' => $name,
        'status' => $status,
        'message' => $message,
    ];
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ");

    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");

    $stmt->execute([
        ':table_name' => $table,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = :index_name
    ");

    $stmt->execute([
        ':table_name' => $table,
        ':index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

/* =========================================================
   CARGAR .ENV Y CONST
========================================================= */

$baseDir = __DIR__;
$projectRoot = dirname(__DIR__);

$envPath = $projectRoot . '/.env';
$envValues = load_env_file_for_health($envPath);

$constPath = __DIR__ . '/const.php';

if (file_exists($constPath)) {
    require_once $constPath;
}
/* =========================================================
   SEGURIDAD BÁSICA DEL HEALTH
========================================================= */

function health_user_is_admin(): bool
{
    $possibleRoles = [
        $_SESSION['role'] ?? null,
        $_SESSION['user_role'] ?? null,
        $_SESSION['user']['role'] ?? null,
        $_SESSION['currentUser']['role'] ?? null,
        $_SESSION['auth']['role'] ?? null,
    ];

    foreach ($possibleRoles as $role) {
        if (is_string($role) && strtolower(trim($role)) === 'admin') {
            return true;
        }
    }

    return false;
}

$healthToken = (string) env_read('HEALTH_TOKEN', '');
$requestToken = (string) ($_GET['token'] ?? '');
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

$isAdminLogged = health_user_is_admin();

$isLocalOrPrivate =
    $remoteAddr === '127.0.0.1' ||
    $remoteAddr === '::1' ||
    starts_with_any($remoteAddr, ['10.', '172.', '192.168.']);

if (!$isAdminLogged) {
    if ($healthToken !== '') {
        if (!hash_equals($healthToken, $requestToken)) {
            http_response_code(403);
            echo 'HEALTH_TOKEN inválido. También podés entrar iniciando sesión como admin.';
            exit;
        }
    } elseif (!$isLocalOrPrivate) {
        http_response_code(403);
        echo 'Health bloqueado. Iniciá sesión como admin o configurá HEALTH_TOKEN en .env.';
        exit;
    }
}

/* =========================================================
   RESULTADOS
========================================================= */

$results = [];
$suggestedSql = [];
$pdo = null;
$dbOk = false;

/* =========================================================
   CHECK ARCHIVOS
========================================================= */

add_result(
    $results,
    'Archivos',
    'Carpeta src',
    is_dir($baseDir) ? 'ok' : 'bad',
    $baseDir
);

add_result(
    $results,
    'Archivos',
    '.env',
    file_exists($envPath) ? 'ok' : 'bad',
    file_exists($envPath)
        ? 'Encontrado en: ' . $envPath
        : 'No encontrado en: ' . $envPath
);

add_result(
    $results,
    'Archivos',
    'const.php',
    file_exists($constPath) ? 'ok' : 'warn',
    file_exists($constPath)
        ? 'Encontrado en: ' . $constPath
        : 'No encontrado. APP_NAME puede no cargarse.'
);

add_result(
    $results,
    'Archivos',
    'config/conexion.php',
    file_exists(__DIR__ . '/config/conexion.php') ? 'ok' : 'bad',
    __DIR__ . '/config/conexion.php'
);

add_result(
    $results,
    'Archivos',
    'db/init.sql',
    file_exists($projectRoot . '/db/init.sql') ? 'ok' : 'warn',
    $projectRoot . '/db/init.sql'
);

/* =========================================================
   CHECK ENV
========================================================= */

$envChecks = [
    'NAME_APP',
    'PRECIO_ROPERO',
    'APP_VERSION',
    'APP_AUTHOR',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
];

foreach ($envChecks as $key) {
    $value = env_read($key, null);

    $maskedValue = $value;

    if (str_contains($key, 'PASSWORD')) {
        $maskedValue = $value ? '********' : null;
    }

    add_result(
        $results,
        '.env',
        $key,
        $value !== null ? 'ok' : 'warn',
        $value !== null
            ? 'Valor: ' . $maskedValue
            : 'No definida. Puede usar fallback si tu conexión acepta MYSQLHOST/MYSQLUSER/etc.'
    );
}

$appName = defined('APP_NAME') ? APP_NAME : 'NO DEFINIDO';

add_result(
    $results,
    '.env',
    'APP_NAME constante',
    defined('APP_NAME') ? 'ok' : 'warn',
    'APP_NAME = ' . $appName
);

/* =========================================================
   CHECK DB
========================================================= */

try {
    require_once __DIR__ . '/config/conexion.php';

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('config/conexion.php no dejó disponible la variable $pdo.');
    }

    $dbOk = true;

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

    add_result($results, 'Base de datos', 'Conexión MySQL', 'ok', 'Conectado a base: ' . $dbName);
    add_result($results, 'Base de datos', 'Versión MySQL', 'ok', $dbVersion);
} catch (Throwable $e) {
    add_result($results, 'Base de datos', 'Conexión MySQL', 'bad', $e->getMessage());
}

/* =========================================================
   CHECK TABLAS Y COLUMNAS
========================================================= */

$schema = [
    'users' => [
        'id',
        'username',
        'display_name',
        'password_hash',
        'role',
        'created_at',
    ],

    'products' => [
        'id',
        'code',
        'name',
        'price',
        'cat',
        'sub',
        'qty',
        'custom',
        'active',
        'created_at',
    ],

    'kiosko_sales' => [
        'id',
        'client_sale_id',
        'user_id',
        'items',
        'total',
        'payment_method',
        'created_at',
    ],

    'kiosko_closings' => [
        'id',
        'user_id',
        'from_sale_id',
        'to_sale_id',
        'total',
        'efectivo_total',
        'transferencia_total',
        'tarjeta_total',
        'regalo_total',
        'sales_count',
        'items',
        'note',
        'created_at',
        'closed_at',
    ],

    'door_lists' => [
        'id',
        'user_id',
        'name',
        'is_birthday',
        'price_per_person',
        'created_at',
    ],

    'door_people' => [
        'id',
        'list_id',
        'name',
        'note',
        'email',
        'status',
        'qr_token',
        'qr_enabled',
        'qr_used_at',
        'created_at',
    ],

    'guardarropas' => [
        'id',
        'numero',
        'codigo',
        'nombre',
        'dni',
        'telefono',
        'prendas',
        'precio',
        'estado',
        'user_id',
        'created_by',
        'created_at',
        'hora_ingreso',
        'hora_retirado',
        'retirado_at',
    ],

    'app_logs' => [
        'id',
        'user_id',
        'username',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'meta',
        'ip_address',
        'user_agent',
        'created_at',
    ],
];

$missingTables = [];
$missingColumnsByTable = [];

if ($dbOk && $pdo instanceof PDO) {
    foreach ($schema as $table => $columns) {
        if (!table_exists($pdo, $table)) {
            $missingTables[] = $table;

            add_result(
                $results,
                'Tablas',
                $table,
                'bad',
                'La tabla no existe.'
            );

            continue;
        }

        $count = 0;

        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        } catch (Throwable) {
            $count = -1;
        }

        add_result(
            $results,
            'Tablas',
            $table,
            'ok',
            $count >= 0 ? 'Existe. Registros: ' . $count : 'Existe, pero no se pudo contar.'
        );

        foreach ($columns as $column) {
            if (!column_exists($pdo, $table, $column)) {
                $missingColumnsByTable[$table][] = $column;

                add_result(
                    $results,
                    'Columnas faltantes',
                    $table . '.' . $column,
                    'bad',
                    'Falta esta columna.'
                );
            }
        }
    }
}

/* =========================================================
   SQL SUGERIDO
========================================================= */

if (in_array('kiosko_closings', $missingTables, true)) {
    $suggestedSql[] = <<<SQL
CREATE TABLE IF NOT EXISTS kiosko_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NULL,

    from_sale_id INT NULL,
    to_sale_id INT NULL,

    total INT NOT NULL DEFAULT 0,
    efectivo_total INT NOT NULL DEFAULT 0,
    transferencia_total INT NOT NULL DEFAULT 0,
    tarjeta_total INT NOT NULL DEFAULT 0,
    regalo_total INT NOT NULL DEFAULT 0,

    sales_count INT NOT NULL DEFAULT 0,

    items LONGTEXT NULL,
    note VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_kiosko_closings_user_id (user_id),
    INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id),
    INDEX idx_kiosko_closings_created_at (created_at),

    CONSTRAINT fk_kiosko_closings_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
}

if (isset($missingColumnsByTable['kiosko_sales'])) {
    foreach ($missingColumnsByTable['kiosko_sales'] as $column) {
        if ($column === 'client_sale_id') {
            $suggestedSql[] = "ALTER TABLE kiosko_sales ADD COLUMN client_sale_id VARCHAR(80) NULL AFTER id;";
            $suggestedSql[] = "ALTER TABLE kiosko_sales ADD UNIQUE KEY uq_kiosko_sales_client_sale_id (client_sale_id);";
        }

        if ($column === 'payment_method') {
            $suggestedSql[] = "ALTER TABLE kiosko_sales ADD COLUMN payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo') NOT NULL DEFAULT 'efectivo' AFTER total;";
        }
    }
}

if (isset($missingColumnsByTable['kiosko_closings'])) {
    foreach ($missingColumnsByTable['kiosko_closings'] as $column) {
        if ($column === 'from_sale_id') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN from_sale_id INT NULL AFTER user_id;";
        }

        if ($column === 'to_sale_id') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN to_sale_id INT NULL AFTER from_sale_id;";
        }

        if ($column === 'regalo_total') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN regalo_total INT NOT NULL DEFAULT 0 AFTER tarjeta_total;";
        }

        if ($column === 'items') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN items LONGTEXT NULL AFTER sales_count;";
        }

        if ($column === 'note') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN note VARCHAR(255) NULL AFTER items;";
        }

        if ($column === 'closed_at') {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD COLUMN closed_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at;";
        }
    }

    $suggestedSql[] = "ALTER TABLE kiosko_closings ADD INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id);";
}

if (isset($missingColumnsByTable['guardarropas'])) {
    foreach ($missingColumnsByTable['guardarropas'] as $column) {
        if ($column === 'codigo') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN codigo VARCHAR(30) NULL AFTER numero;";
            $suggestedSql[] = "UPDATE guardarropas SET codigo = CONCAT('GR-', LPAD(numero, 3, '0')) WHERE codigo IS NULL;";
            $suggestedSql[] = "ALTER TABLE guardarropas ADD UNIQUE KEY uq_guardarropas_codigo (codigo);";
        }

        if ($column === 'created_by') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN created_by INT NULL AFTER user_id;";
            $suggestedSql[] = "UPDATE guardarropas SET created_by = user_id WHERE created_by IS NULL AND user_id IS NOT NULL;";
        }

        if ($column === 'hora_ingreso') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN hora_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by;";
        }

        if ($column === 'hora_retirado') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN hora_retirado DATETIME NULL AFTER hora_ingreso;";
        }

        if ($column === 'retirado_at') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN retirado_at DATETIME NULL AFTER hora_retirado;";
        }

        if ($column === 'user_id') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN user_id INT NULL AFTER estado;";
        }

        if ($column === 'prendas') {
            $suggestedSql[] = "ALTER TABLE guardarropas ADD COLUMN prendas INT NOT NULL DEFAULT 1 AFTER telefono;";
        }
    }
}

if ($dbOk && $pdo instanceof PDO) {
    try {
        if (table_exists($pdo, 'kiosko_closings') && !index_exists($pdo, 'kiosko_closings', 'idx_kiosko_closings_sale_range')) {
            $suggestedSql[] = "ALTER TABLE kiosko_closings ADD INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id);";
        }
    } catch (Throwable) {
        // No cortar el health por un índice.
    }
}

/* =========================================================
   RESUMEN GENERAL
========================================================= */

$totalBad = count(array_filter($results, fn($r) => $r['status'] === 'bad'));
$totalWarn = count(array_filter($results, fn($r) => $r['status'] === 'warn'));

$generalStatus = $totalBad > 0 ? 'bad' : ($totalWarn > 0 ? 'warn' : 'ok');

$sections = [];

foreach ($results as $result) {
    $sections[$result['section']][] = $result;
}

$elapsed = round((microtime(true) - $startedAt) * 1000, 2);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Health Check - <?= h($appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top, rgba(176, 124, 255, 0.16), transparent 28%),
                linear-gradient(180deg, #120d18 0%, #09070d 100%);
            color: #f5f0ff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .wrap {
            width: min(1100px, 100%);
            margin: 0 auto;
        }

        .hero {
            border: 1px solid rgba(240, 212, 141, 0.18);
            background: rgba(20, 15, 30, 0.88);
            border-radius: 22px;
            padding: 22px;
            margin-bottom: 18px;
            box-shadow: 0 20px 60px rgba(0,0,0,.22);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .muted {
            color: #b9adc8;
            font-size: 14px;
            line-height: 1.4;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .metric {
            border: 1px solid rgba(240, 212, 141, 0.13);
            background: rgba(255,255,255,.04);
            border-radius: 16px;
            padding: 14px;
        }

        .metric strong {
            display: block;
            font-size: 22px;
            margin-top: 4px;
        }

        .section {
            border: 1px solid rgba(240, 212, 141, 0.14);
            background: rgba(20, 15, 30, 0.82);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .section-title {
            padding: 14px 16px;
            font-weight: 800;
            color: #f0d48d;
            border-bottom: 1px solid rgba(240, 212, 141, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255,255,255,.06);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            color: #b9adc8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
        }

        .badge-ok {
            background: rgba(74, 222, 128, .14);
            color: #86efac;
        }

        .badge-warn {
            background: rgba(250, 204, 21, .14);
            color: #fde68a;
        }

        .badge-bad {
            background: rgba(248, 113, 113, .14);
            color: #fca5a5;
        }

        textarea {
            width: 100%;
            min-height: 260px;
            border: 1px solid rgba(240, 212, 141, 0.14);
            background: rgba(0,0,0,.25);
            color: #fff1c7;
            border-radius: 16px;
            padding: 14px;
            font-family: Consolas, monospace;
            font-size: 13px;
            resize: vertical;
            outline: none;
        }

        .btn {
            display: inline-block;
            margin-top: 10px;
            border: 0;
            border-radius: 12px;
            background: #f0d48d;
            color: #15101d;
            font-weight: 900;
            padding: 11px 14px;
            cursor: pointer;
        }

        .footer {
            color: #b9adc8;
            text-align: center;
            font-size: 12px;
            margin: 22px 0 6px;
        }

        @media (max-width: 760px) {
            body {
                padding: 12px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            table, tbody, tr, td, th {
                display: block;
                width: 100%;
            }

            th {
                display: none;
            }

            td {
                border-bottom: 0;
                padding: 8px 12px;
            }

            tr {
                border-bottom: 1px solid rgba(255,255,255,.08);
                padding: 8px 0;
            }
        }
    </style>
</head>

<body>
<div class="wrap">

    <div class="hero">
        <h1>Health Check - <?= h($appName) ?></h1>

        <div class="muted">
            Diagnóstico de archivos, variables de entorno, conexión MySQL, tablas y columnas necesarias.
        </div>

        <div class="grid">
            <div class="metric">
                Estado general
                <strong><?= status_badge($generalStatus) ?></strong>
            </div>

            <div class="metric">
                Errores
                <strong><?= (int) $totalBad ?></strong>
            </div>

            <div class="metric">
                Avisos
                <strong><?= (int) $totalWarn ?></strong>
            </div>
        </div>

        <div class="muted" style="margin-top:14px;">
            Tiempo de ejecución: <?= h($elapsed) ?> ms · IP: <?= h($remoteAddr) ?>
        </div>
    </div>

    <?php foreach ($sections as $sectionName => $items): ?>
        <div class="section">
            <div class="section-title"><?= h($sectionName) ?></div>

            <table>
                <thead>
                    <tr>
                        <th style="width:190px;">Estado</th>
                        <th style="width:260px;">Chequeo</th>
                        <th>Detalle</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= status_badge($item['status']) ?></td>
                        <td><strong><?= h($item['name']) ?></strong></td>
                        <td><?= h($item['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <div class="section">
        <div class="section-title">SQL sugerido</div>

        <div style="padding:16px;">
            <?php if ($suggestedSql): ?>
                <div class="muted" style="margin-bottom:10px;">
                    Copiá y pegá esto en phpMyAdmin si querés corregir las columnas/tablas faltantes.
                </div>

                <textarea id="sqlBox" readonly><?= h(implode("\n\n", array_unique($suggestedSql))) ?></textarea>

                <button class="btn" onclick="copySql()">Copiar SQL</button>
            <?php else: ?>
                <div class="muted">
                    No hay SQL sugerido. La estructura principal parece estar bien.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        Divine App · health.php
    </div>

</div>

<script>
function copySql() {
    const box = document.getElementById('sqlBox');
    if (!box) return;

    box.select();
    box.setSelectionRange(0, 999999);

    navigator.clipboard.writeText(box.value)
        .then(() => alert('SQL copiado'))
        .catch(() => {
            document.execCommand('copy');
            alert('SQL copiado');
        });
}
</script>

</body>
</html>