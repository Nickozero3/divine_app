/* =========================================================
   DIVINE APP - BASE DE DATOS INICIAL
   ---------------------------------------------------------
   Este archivo crea las tablas principales de la app:
   - Usuarios
   - Productos
   - Ventas de Kioskito
   - Listas de puerta
   - Personas en listas
   - QR de entrada
   - Guardarropas
   - Logs internos
   ========================================================= */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


/* =========================================================
   TABLA: users
   ---------------------------------------------------------
   Guarda los usuarios que pueden iniciar sesión.
   role:
   - admin   = acceso completo
   - puerta  = acceso a scanner y puerta
   - usuario = acceso limitado a sus listas
   ========================================================= */

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM('admin', 'usuario', 'puerta') NOT NULL DEFAULT 'usuario',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_users_username (username),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: products
   ---------------------------------------------------------
   Guarda los productos del Kioskito.
   Campos:
   - code   = código único estable para evitar duplicados
   - name   = nombre visible del producto
   - price  = precio en pesos
   - cat    = categoría: Vasos, Snacks, Combos, Bebidas, etc.
   - sub    = descripción extra del combo/producto
   - qty    = stock o cantidad si luego se usa
   - custom = producto creado manualmente
   - active = producto visible/activo
   ========================================================= */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: door_lists
   ---------------------------------------------------------
   Guarda las listas de puerta.
   Cada lista pertenece a un usuario.
   is_birthday:
   - 0 = lista normal
   - 1 = lista cumpleaños
   price_per_person:
   - normalmente 500
   - cumpleaños puede usar 1000
   ========================================================= */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: door_people
   ---------------------------------------------------------
   Guarda las personas dentro de cada lista.
   status:
   - no_vino = todavía no entró
   - entro   = entró al evento
   - se_fue  = salió/se fue
   QR:
   - qr_token   = token único usado en qr.php
   - qr_enabled = permite activar/desactivar QR
   - qr_used_at = fecha/hora en que fue usado
   ========================================================= */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: kiosko_sales
   ---------------------------------------------------------
   Guarda el historial de ventas del Kioskito.
   items:
   - JSON o texto largo con los productos vendidos
   total:
   - total de la venta en pesos
   ========================================================= */

CREATE TABLE IF NOT EXISTS kiosko_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    items LONGTEXT NOT NULL,
    total INT NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_kiosko_sales_user_id (user_id),
    INDEX idx_kiosko_sales_created_at (created_at),

    CONSTRAINT fk_kiosko_sales_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: guardarropas
   ---------------------------------------------------------
   Guarda los números de guardarropas.
   estado:
   - pendiente = la prenda sigue guardada
   - retirado  = la prenda ya fue entregada
   ========================================================= */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: app_logs
   ---------------------------------------------------------
   Guarda acciones internas de la app.
   Sirve para auditoría:
   - ventas
   - cambios de estado
   - eliminaciones
   - errores
   ========================================================= */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   PRODUCTOS INICIALES
   ---------------------------------------------------------
   Inserta productos base.
   Si el producto ya existe por code, actualiza:
   - name
   - price
   - cat
   - sub
   - active
   ========================================================= */

INSERT INTO products (code, name, price, cat, sub, custom, active)
VALUES
('p1',  'Gancia',                    13000, 'Vasos',     '',                                      0, 1),
('p2',  'Vodka Sernova',             14000, 'Vasos',     '',                                      0, 1),
('p3',  'Campari',                   13000, 'Vasos',     '',                                      0, 1),
('p4',  'Gin Gordon | Blu | Heraclito',14500,'Vasos',    '',                                      0, 1),
('p5',  'Chicles',                    2000, 'Snacks',    '',                                      0, 1),
('p6',  'Etiquetas cigarrillos',      6000, 'Snacks',    '',                                      0, 1),
('p7',  'Papitas',                    2500, 'Snacks',    '',                                      0, 1),
('p8',  'Combo Sernova',             43000, 'Combos',    '1 Sernova + 3 Speed',                   0, 1),
('p9',  'Combo Fernet',              41000, 'Combos',    '1 Fernet + 4 cocas lata',               0, 1),
('p10', 'Combo Smirnoff',            45000, 'Combos',    '1 Smirnoff rojo/verde + 3 Speed',       0, 1),
('p11', 'Combo Skyy',                47000, 'Combos',    '1 Skyy + 3 Speed',                      0, 1),
('p12', 'Combo de Gin',              48000, 'Combos',    '1 Gin Heráclito + 1 botella 1.5 de tónica', 0, 1),
('p13', 'VIP',                        5000, 'Extras',    '',                                      0, 1),
('p14', 'Combo Absolut',             75000, 'Combos',    '1 Absolut + 3 Speed',                   0, 1),
('p15', 'Gaseosa',                    6000, 'Bebidas',   '',                                      0, 1),
('p16', 'Speed',                      6000, 'Bebidas',   '',                                      0, 1),
('p17', 'Agua | Soda',                5000, 'Bebidas',   '',                                      0, 1),
('p18', 'Soda',                       5000, 'Bebidas',   '',                                      0, 1),
('p19', 'Lata Cerveza',               8000, 'Bebidas',   '',                                      0, 1),
('p20', 'Dilema B | R | T',          15000, 'Bebidas',   '',                                      0, 1),
('p21', 'Vodka barato',              12500, 'Vasos',     '',                                      0, 1),
('p22', 'Fernet',                    14000, 'Vasos',     '',                                      0, 1),
('p23', 'Vodka Absolut',             22000, 'Vasos',     '',                                      0, 1),
('p24', 'Beefeater',                 22000, 'Vasos',     '',                                      0, 1),
('p25', 'Malibu',                    14500, 'Vasos',     '',                                      0, 1),
('p26', 'Jaggermeister',             22000, 'Vasos',     '',                                      0, 1),
('p27', 'Mumm',                      24000, 'Botellas',  '',                                      0, 1),
('p28', 'Du',                        16000, 'Botellas',  '',                                      0, 1)

ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    price = VALUES(price),
    cat = VALUES(cat),
    sub = VALUES(sub),
    active = VALUES(active);


/* =========================================================
   FIN DEL ARCHIVO
   ========================================================= */

SET FOREIGN_KEY_CHECKS = 1;