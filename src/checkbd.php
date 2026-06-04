<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DIVINE APP - DB CHECK / MIGRADOR LOCAL
|--------------------------------------------------------------------------
| Uso:
| 1) Guardar este archivo como: src/db_check.php
| 2) Abrir en el navegador: http://localhost:8080/db_check.php
| 3) Si todo queda OK, borrar este archivo o dejarlo solo en local.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/conexion.php';

if (!isset($pdo)) {
    die('No se encontró la conexión PDO.');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND index_name = ?
    ");
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function runSql(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

$messages = [];

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    /*
    |--------------------------------------------------------------------------
    | 1) CREAR TABLAS SI NO EXISTEN
    |--------------------------------------------------------------------------
    */

    $tables = [];

    $tables['users'] = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,

            username VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,

            role ENUM('admin', 'usuario', 'puerta') NOT NULL DEFAULT 'usuario',

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_users_username (username),
            INDEX idx_users_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['products'] = "
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,

            code VARCHAR(50) UNIQUE,
            name VARCHAR(100) NOT NULL,
            price INT NOT NULL DEFAULT 0,
            cat VARCHAR(50) NOT NULL DEFAULT 'Otros',
            sub VARCHAR(255) DEFAULT '',
            qty INT NOT NULL DEFAULT 0,

            custom TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_products_cat (cat),
            INDEX idx_products_active (active),
            INDEX idx_products_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['door_lists'] = "
        CREATE TABLE IF NOT EXISTS door_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,

            user_id INT NOT NULL,

            name VARCHAR(100) NOT NULL,
            is_birthday TINYINT(1) NOT NULL DEFAULT 0,
            price_per_person INT NOT NULL DEFAULT 500,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_door_lists_user_id (user_id),
            INDEX idx_door_lists_birthday (is_birthday),
            INDEX idx_door_lists_name (name),

            CONSTRAINT fk_door_lists_user
                FOREIGN KEY (user_id)
                REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['door_people'] = "
        CREATE TABLE IF NOT EXISTS door_people (
            id INT AUTO_INCREMENT PRIMARY KEY,

            list_id INT NOT NULL,

            name VARCHAR(120) NOT NULL,
            note VARCHAR(50) NOT NULL,

            email VARCHAR(150) DEFAULT NULL,

            status ENUM('no_vino', 'entro', 'se_fue') NOT NULL DEFAULT 'no_vino',

            qr_token VARCHAR(120) DEFAULT NULL,
            qr_enabled TINYINT(1) NOT NULL DEFAULT 0,
            qr_used_at DATETIME DEFAULT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_door_people_list_id (list_id),
            INDEX idx_door_people_status (status),
            INDEX idx_door_people_name (name),
            INDEX idx_door_people_qr_enabled (qr_enabled),
            UNIQUE KEY uq_door_people_qr_token (qr_token),

            CONSTRAINT fk_door_people_list
                FOREIGN KEY (list_id)
                REFERENCES door_lists(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['kiosko_sales'] = "
        CREATE TABLE IF NOT EXISTS kiosko_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,

            client_sale_id VARCHAR(80) DEFAULT NULL,
            user_id INT NOT NULL,

            items LONGTEXT NOT NULL,
            total INT NOT NULL DEFAULT 0,
            payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo') NOT NULL DEFAULT 'efectivo',

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_kiosko_sales_client_sale_id (client_sale_id),
            INDEX idx_kiosko_sales_user_id (user_id),
            INDEX idx_kiosko_sales_created_at (created_at),

            CONSTRAINT fk_kiosko_sales_user
                FOREIGN KEY (user_id)
                REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['guardarropas'] = "
        CREATE TABLE IF NOT EXISTS guardarropas (
            id INT AUTO_INCREMENT PRIMARY KEY,

            numero INT NOT NULL UNIQUE,
            codigo VARCHAR(20) NOT NULL,

            nombre VARCHAR(120) NOT NULL,
            dni VARCHAR(50) DEFAULT NULL,
            telefono VARCHAR(50) DEFAULT NULL,

            precio INT NOT NULL DEFAULT 2000,

            estado ENUM('pendiente', 'retirado') NOT NULL DEFAULT 'pendiente',

            hora_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            hora_retirado DATETIME DEFAULT NULL,

            created_by INT DEFAULT NULL,

            INDEX idx_guardarropas_estado (estado),
            INDEX idx_guardarropas_nombre (nombre),
            INDEX idx_guardarropas_numero (numero),
            INDEX idx_guardarropas_codigo (codigo),
            INDEX idx_guardarropas_created_by (created_by),

            CONSTRAINT fk_guardarropas_user
                FOREIGN KEY (created_by)
                REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $tables['app_logs'] = "
        CREATE TABLE IF NOT EXISTS app_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,

            user_id INT DEFAULT NULL,
            username VARCHAR(80) DEFAULT NULL,

            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(60) DEFAULT NULL,
            entity_id INT DEFAULT NULL,

            description TEXT DEFAULT NULL,
            meta LONGTEXT DEFAULT NULL,

            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_app_logs_user_id (user_id),
            INDEX idx_app_logs_action (action),
            INDEX idx_app_logs_entity (entity_type, entity_id),
            INDEX idx_app_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    foreach ($tables as $tableName => $sql) {
        if (!tableExists($pdo, $tableName)) {
            runSql($pdo, $sql);
            $messages[] = "✅ Tabla creada: {$tableName}";
        } else {
            $messages[] = "✔ Tabla existente: {$tableName}";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2) MIGRACIONES DE COLUMNAS FALTANTES
    |--------------------------------------------------------------------------
    */

    if (tableExists($pdo, 'kiosko_sales')) {
        if (!columnExists($pdo, 'kiosko_sales', 'client_sale_id')) {
            runSql($pdo, "
                ALTER TABLE kiosko_sales
                ADD COLUMN client_sale_id VARCHAR(80) DEFAULT NULL AFTER id
            ");
            $messages[] = "✅ Columna agregada: kiosko_sales.client_sale_id";
        } else {
            $messages[] = "✔ Columna existente: kiosko_sales.client_sale_id";
        }

        if (!columnExists($pdo, 'kiosko_sales', 'payment_method')) {
            runSql($pdo, "
                ALTER TABLE kiosko_sales
                ADD COLUMN payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo')
                NOT NULL DEFAULT 'efectivo'
                AFTER total
            ");
            $messages[] = "✅ Columna agregada: kiosko_sales.payment_method";
        } else {
            $messages[] = "✔ Columna existente: kiosko_sales.payment_method";
        }

        if (!indexExists($pdo, 'kiosko_sales', 'uq_kiosko_sales_client_sale_id')) {
            runSql($pdo, "
                ALTER TABLE kiosko_sales
                ADD UNIQUE KEY uq_kiosko_sales_client_sale_id (client_sale_id)
            ");
            $messages[] = "✅ Índice agregado: uq_kiosko_sales_client_sale_id";
        } else {
            $messages[] = "✔ Índice existente: uq_kiosko_sales_client_sale_id";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3) MIGRACIONES DEFENSIVAS POR SI ALGUNA TABLA VIEJA QUEDÓ INCOMPLETA
    |--------------------------------------------------------------------------
    */

    if (tableExists($pdo, 'users')) {
        if (!columnExists($pdo, 'users', 'role')) {
            runSql($pdo, "
                ALTER TABLE users
                ADD COLUMN role ENUM('admin', 'usuario', 'puerta') NOT NULL DEFAULT 'usuario'
                AFTER password_hash
            ");
            $messages[] = "✅ Columna agregada: users.role";
        }

        if (!columnExists($pdo, 'users', 'created_at')) {
            runSql($pdo, "
                ALTER TABLE users
                ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ");
            $messages[] = "✅ Columna agregada: users.created_at";
        }
    }

    if (tableExists($pdo, 'products')) {
        $productColumns = [
            'code' => "ALTER TABLE products ADD COLUMN code VARCHAR(50) UNIQUE AFTER id",
            'sub' => "ALTER TABLE products ADD COLUMN sub VARCHAR(255) DEFAULT '' AFTER cat",
            'qty' => "ALTER TABLE products ADD COLUMN qty INT NOT NULL DEFAULT 0 AFTER sub",
            'custom' => "ALTER TABLE products ADD COLUMN custom TINYINT(1) NOT NULL DEFAULT 0 AFTER qty",
            'active' => "ALTER TABLE products ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER custom",
            'created_at' => "ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];

        foreach ($productColumns as $column => $sql) {
            if (!columnExists($pdo, 'products', $column)) {
                runSql($pdo, $sql);
                $messages[] = "✅ Columna agregada: products.{$column}";
            }
        }
    }

    if (tableExists($pdo, 'door_people')) {
        $doorPeopleColumns = [
            'email' => "ALTER TABLE door_people ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER note",
            'qr_token' => "ALTER TABLE door_people ADD COLUMN qr_token VARCHAR(120) DEFAULT NULL AFTER status",
            'qr_enabled' => "ALTER TABLE door_people ADD COLUMN qr_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER qr_token",
            'qr_used_at' => "ALTER TABLE door_people ADD COLUMN qr_used_at DATETIME DEFAULT NULL AFTER qr_enabled"
        ];

        foreach ($doorPeopleColumns as $column => $sql) {
            if (!columnExists($pdo, 'door_people', $column)) {
                runSql($pdo, $sql);
                $messages[] = "✅ Columna agregada: door_people.{$column}";
            }
        }

        if (!indexExists($pdo, 'door_people', 'uq_door_people_qr_token')) {
            runSql($pdo, "
                ALTER TABLE door_people
                ADD UNIQUE KEY uq_door_people_qr_token (qr_token)
            ");
            $messages[] = "✅ Índice agregado: uq_door_people_qr_token";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

} catch (Throwable $e) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Throwable $ignored) {
    }

    http_response_code(500);

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Error DB Check</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#120d18;color:#fff;padding:24px;}
        .box{max-width:900px;margin:auto;background:#1d1628;border:1px solid #ff5a5a;padding:22px;border-radius:18px;}
        code{background:#000;padding:4px 6px;border-radius:6px;}
    </style>';
    echo '</head><body><div class="box">';
    echo '<h1>❌ Error en DB Check</h1>';
    echo '<p><strong>Mensaje:</strong></p>';
    echo '<code>' . h($e->getMessage()) . '</code>';
    echo '</div></body></html>';
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Divine App - DB Check</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top, rgba(176, 124, 255, 0.14), transparent 30%),
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

        h1 {
            margin-top: 0;
            color: #f0d48d;
        }

        .ok {
            background: rgba(0, 255, 150, 0.08);
            border: 1px solid rgba(0, 255, 150, 0.18);
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

        code {
            background: rgba(0,0,0,.35);
            padding: 3px 6px;
            border-radius: 6px;
            color: #f0d48d;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>✅ Divine App - Base verificada</h1>

        <?php foreach ($messages as $message): ?>
            <div class="ok"><?= h($message) ?></div>
        <?php endforeach; ?>

        <div class="warn">
            <strong>Importante:</strong>
            después de usar este archivo, podés borrarlo o dejarlo solo en local.
            No conviene dejar migradores abiertos públicamente en producción.
        </div>
    </div>
</body>
</html>