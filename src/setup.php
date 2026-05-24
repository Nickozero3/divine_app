<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(80) NULL,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(60) NULL,
            entity_id INT NULL,
            description TEXT NULL,
            meta LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app_logs_user_id (user_id),
            INDEX idx_app_logs_action (action),
            INDEX idx_app_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS door_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_birthday TINYINT(1) NOT NULL DEFAULT 0,
            price_per_person INT NOT NULL DEFAULT 500,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS door_people (
            id INT AUTO_INCREMENT PRIMARY KEY,
            list_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            note VARCHAR(50) NOT NULL,
            status ENUM('no_vino', 'entro', 'se_fue') NOT NULL DEFAULT 'no_vino',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (list_id) REFERENCES door_lists(id) ON DELETE CASCADE,
            INDEX idx_door_people_list_id (list_id),
            INDEX idx_door_people_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kiosko_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            items LONGTEXT NOT NULL,
            total INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guardarropas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero INT NOT NULL UNIQUE,
            codigo VARCHAR(20) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            dni VARCHAR(50) DEFAULT NULL,
            telefono VARCHAR(50) DEFAULT NULL,
            precio INT NOT NULL DEFAULT 2000,
            estado ENUM('pendiente','retirado') NOT NULL DEFAULT 'pendiente',
            hora_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            hora_retirado DATETIME DEFAULT NULL,
            created_by INT DEFAULT NULL,
            INDEX idx_estado (estado),
            INDEX idx_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $users = [
        ['camila', 'Camila', 'camila123', 'admin'],
        ['nicolas', 'Nicolas', 'nicolas123', 'admin'],
        ['lopez', 'Lopez', 'lopez123', 'admin'],
        ['publica', 'Publica', 'publica123', 'usuario'],
    ];

    $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");

    $insertUser = $pdo->prepare("
        INSERT INTO users (username, display_name, password_hash, role)
        VALUES (:username, :display_name, :password_hash, :role)
    ");

    $updateUser = $pdo->prepare("
        UPDATE users
        SET display_name = :display_name,
            password_hash = :password_hash,
            role = :role
        WHERE username = :username
    ");

    foreach ($users as [$username, $displayName, $password, $role]) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $checkUser->execute([':username' => $username]);
        $exists = $checkUser->fetchColumn();

        if ($exists) {
            $updateUser->execute([
                ':username' => $username,
                ':display_name' => $displayName,
                ':password_hash' => $hash,
                ':role' => $role,
            ]);
        } else {
            $insertUser->execute([
                ':username' => $username,
                ':display_name' => $displayName,
                ':password_hash' => $hash,
                ':role' => $role,
            ]);
        }
    }

    $products = [
        ['p1', 'Gancia', 14500, 'Vasos', ''],
        ['p2', 'Vodka', 14000, 'Vasos', ''],
        ['p3', 'Campari', 13500, 'Vasos', ''],
        ['p4', 'Gin', 14500, 'Vasos', ''],

        ['p5', 'Chicles', 0, 'Snacks', ''],
        ['p6', 'Etiquetas cigarrillos', 0, 'Snacks', ''],
        ['p7', 'Papitas', 0, 'Snacks', ''],

        ['p8', 'Combo Sernova', 43000, 'Combos', '1 Sernova + 3 Speed'],
        ['p9', 'Combo Fernet', 41000, 'Combos', '1 Fernet + 4 cocas lata'],
        ['p10', 'Combo Smirnoff', 45000, 'Combos', '1 Smirnoff (rojo/verde) + 3 Speed'],
        ['p11', 'Combo Skyy', 47000, 'Combos', '1 Skyy + 3 Speed'],
        ['p12', 'Combo de Gin', 0, 'Combos', '1 Gin Heráclito + 1 botella 1.5 de tónica'],
        ['p14', 'Combo Absolut', 75000, 'Combos', '1 Absolut + 3 Speed'],

        ['p15', 'Gaseosa', 6000, 'Bebidas', ''],
        ['p16', 'Speed', 6000, 'Bebidas', ''],
        ['p17', 'Agua', 5000, 'Bebidas', ''],
        ['p18', 'Soda', 5000, 'Bebidas', ''],

        ['p13', 'VIP', 5000, 'Extras', ''],
        ['p19', 'Guardarropa', 2000, 'Extras', ''],
    ];

    $checkProduct = $pdo->prepare("SELECT id FROM products WHERE code = :code LIMIT 1");

    $insertProduct = $pdo->prepare("
        INSERT INTO products (code, name, price, cat, sub, qty, custom, active)
        VALUES (:code, :name, :price, :cat, :sub, 0, 0, 1)
    ");

    $updateProduct = $pdo->prepare("
        UPDATE products
        SET name = :name,
            price = :price,
            cat = :cat,
            sub = :sub,
            active = 1
        WHERE code = :code
    ");

    foreach ($products as [$code, $name, $price, $cat, $sub]) {
        $checkProduct->execute([':code' => $code]);
        $exists = $checkProduct->fetchColumn();

        if ($exists) {
            $updateProduct->execute([
                ':code' => $code,
                ':name' => $name,
                ':price' => $price,
                ':cat' => $cat,
                ':sub' => $sub,
            ]);
        } else {
            $insertProduct->execute([
                ':code' => $code,
                ':name' => $name,
                ':price' => $price,
                ':cat' => $cat,
                ':sub' => $sub,
            ]);
        }
    }

    echo "<h1>Base inicializada correctamente</h1>";
    echo "<p>Tablas creadas y productos cargados.</p>";
    echo "<p><a href='login.php'>Ir al login</a></p>";

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Error inicializando base</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}