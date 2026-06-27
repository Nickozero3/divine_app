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
   - puerta  = guardia: puerta, scanner y carta
   - usuario = RRPP/Pública: sus listas y carta
   - kiosko  = Kioskito, Guardarropas y carta
   ========================================================= */

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM('admin', 'usuario', 'puerta', 'kiosko') NOT NULL DEFAULT 'usuario',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_users_username (username),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/*
 * Compatibilidad con instalaciones existentes.
 * Amplía los roles sin eliminar usuarios.
 */
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'usuario', 'puerta', 'kiosko')
    NOT NULL DEFAULT 'usuario';


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
   - qty            = cantidad vendida/stock según el módulo
   - category_order = posición de la categoría
   - sort_order     = posición dentro de la categoría
   - custom         = producto creado manualmente
   - active         = producto visible/activo
   ========================================================= */

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(50) UNIQUE,
    name VARCHAR(100) NOT NULL,
    price INT NOT NULL DEFAULT 0,
    cat VARCHAR(50) NOT NULL DEFAULT 'Otros',
    sub VARCHAR(255) DEFAULT '',
    qty INT NOT NULL DEFAULT 0,

    category_order TINYINT UNSIGNED NOT NULL DEFAULT 99,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,

    custom TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_products_cat (cat),
    INDEX idx_products_category_order (category_order, sort_order),
    INDEX idx_products_active (active),
    INDEX idx_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/*
 * Compatibilidad con bases existentes:
 * añade columnas de orden sin borrar productos.
 */
SET @products_category_order_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'category_order'
);
SET @products_category_order_sql := IF(
    @products_category_order_exists = 0,
    'ALTER TABLE products ADD COLUMN category_order TINYINT UNSIGNED NOT NULL DEFAULT 99 AFTER qty',
    'SELECT 1'
);
PREPARE products_category_order_stmt FROM @products_category_order_sql;
EXECUTE products_category_order_stmt;
DEALLOCATE PREPARE products_category_order_stmt;

SET @products_sort_order_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'sort_order'
);
SET @products_sort_order_sql := IF(
    @products_sort_order_exists = 0,
    'ALTER TABLE products ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER category_order',
    'SELECT 1'
);
PREPARE products_sort_order_stmt FROM @products_sort_order_sql;
EXECUTE products_sort_order_stmt;
DEALLOCATE PREPARE products_sort_order_stmt;

SET @products_order_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'idx_products_category_order'
);
SET @products_order_index_sql := IF(
    @products_order_index_exists = 0,
    'ALTER TABLE products ADD INDEX idx_products_category_order (category_order, sort_order)',
    'SELECT 1'
);
PREPARE products_order_index_stmt FROM @products_order_index_sql;
EXECUTE products_order_index_stmt;
DEALLOCATE PREPARE products_order_index_stmt;


/* =========== ==============================================
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
   client_sale_id:
   - evita ventas duplicadas cuando se reintenta una venta
   ========================================================= */

CREATE TABLE IF NOT EXISTS kiosko_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,

    client_sale_id VARCHAR(80) DEFAULT NULL,
    user_id INT NOT NULL,

    items LONGTEXT NOT NULL,
    total INT NOT NULL DEFAULT 0,

    payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo') 
        NOT NULL DEFAULT 'efectivo',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_kiosko_sales_client_sale_id (client_sale_id),
    INDEX idx_kiosko_sales_user_id (user_id),
    INDEX idx_kiosko_sales_created_at (created_at),

    CONSTRAINT fk_kiosko_sales_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    deleted_at DATETIME NULL DEFAULT NULL,

    INDEX idx_kiosko_closings_user_id (user_id),
    INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id),
    INDEX idx_kiosko_closings_created_at (created_at),
    INDEX idx_kiosko_closings_deleted_at (deleted_at),

    CONSTRAINT fk_kiosko_closings_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
 * Borrado lógico: oculta cierres sin reabrir ventas antiguas.
 */
SET @kiosko_deleted_at_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kiosko_closings'
      AND COLUMN_NAME = 'deleted_at'
);
SET @kiosko_deleted_at_sql := IF(
    @kiosko_deleted_at_exists = 0,
    'ALTER TABLE kiosko_closings ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER closed_at',
    'SELECT 1'
);
PREPARE kiosko_deleted_at_stmt FROM @kiosko_deleted_at_sql;
EXECUTE kiosko_deleted_at_stmt;
DEALLOCATE PREPARE kiosko_deleted_at_stmt;

 
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

    numero INT NOT NULL,
    codigo VARCHAR(30) DEFAULT NULL,

    nombre VARCHAR(120) NOT NULL,
    dni VARCHAR(30) DEFAULT NULL,
    telefono VARCHAR(40) DEFAULT NULL,

    prendas INT NOT NULL DEFAULT 1,
    precio INT NOT NULL DEFAULT 2000,

    estado ENUM('pendiente', 'retirado') NOT NULL DEFAULT 'pendiente',

    user_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    hora_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hora_retirado DATETIME DEFAULT NULL,
    retirado_at DATETIME DEFAULT NULL,

    UNIQUE KEY uq_guardarropas_numero (numero),
    UNIQUE KEY uq_guardarropas_codigo (codigo),

    INDEX idx_guardarropas_estado (estado),
    INDEX idx_guardarropas_created_at (created_at),
    INDEX idx_guardarropas_user_id (user_id),
    INDEX idx_guardarropas_created_by (created_by)
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


-- -- =========================================================
--     TABLA: user_remember_tokens
--     ---------------------------------------------------------
--     Guarda los tokens de "recordarme" para sesiones persistentes.
--     Cada token tiene un selector (público) y un token_hash (secreto).
--     ========================================================= */
    

CREATE TABLE IF NOT EXISTS user_remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  selector VARCHAR(64) NOT NULL UNIQUE,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX (user_id),
  INDEX (selector),
  INDEX (expires_at)
);

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

/*
 * ORDEN DE CATEGORÍAS
 *  1 = Vasos
 *  2 = Vinos
 *  3 = Champagnes
 *  4 = Cervezas
 *  5 = Sin alcohol
 *  6 = Combos
 *  7 = Shot
 *  8 = Kiosko
 *  9 = Extras
 * 99 = Otros
 */
INSERT INTO products (
    code,
    name,
    price,
    cat,
    sub,
    category_order,
    sort_order,
    custom,
    active
)
VALUES
    /* ==============================
       1. VASOS
       ============================== */
    ('p1', 'Gancia', 13000, 'Vasos', '', 1, 1, 0, 1),
    ('p2', 'Vodka Sernova', 14000, 'Vasos', 'Frutos rojos|Manzana verde|Marayuca|Coco con jugo o speed', 1, 2, 0, 1),
    ('p3', 'Campari', 13000, 'Vasos', '', 1, 3, 0, 1),
    ('p4', 'Gin Gordon | Blu | Heraclito', 14500, 'Vasos', '', 1, 4, 0, 1),
    ('p21', 'Vodka Economico', 12500, 'Vasos', 'Uva,Sin gusto, Caramelo', 1, 5, 0, 1),
    ('p22', 'Fernet', 14000, 'Vasos', '', 1, 6, 0, 1),
    ('p23', 'Vodka Absolut', 22000, 'Vasos', 'Mandarina, WildBerry, Raspberry, original', 1, 7, 0, 1),
    ('p24', 'Beefeater', 22000, 'Vasos', '', 1, 8, 0, 1),
    ('p25', 'Malibu', 14500, 'Vasos', '', 1, 9, 0, 1),
    ('p26', 'Jaggermeister', 22000, 'Vasos', 'Medida de jaggermeister con speed', 1, 10, 0, 1),
    ('p35', 'Cuba libre', 16000, 'Vasos', '', 1, 11, 0, 1),

    /* ==============================
       2. VINOS
       ============================== */
    ('p20', 'Otro loco Tinto', 15000, 'Vinos', '', 2, 1, 0, 1),
    ('p29', 'Dilema Blanco', 15000, 'Vinos', '', 2, 2, 0, 1),
    ('p30', 'Dilema Rosado', 15000, 'Vinos', '', 2, 3, 0, 1),
    ('p31', 'Dilema Tinto', 15000, 'Vinos', '', 2, 4, 0, 1),
    ('p32', 'Santa julia', 21000, 'Vinos', '', 2, 5, 0, 1),
    ('p39', 'Santa julia Tinto', 21000, 'Vinos', '', 2, 6, 0, 1),

    /* ==============================
       3. CHAMPAGNES / ESPUMANTES
       ============================== */
    ('p27', 'Mumm', 24000, 'Champagnes', '', 3, 1, 0, 1),
    ('p28', 'Du', 16000, 'Champagnes', '', 3, 2, 0, 1),
    ('p34', 'Chandon', 32000, 'Champagnes', 'Botella de chandon (Consultar disponibilidad entre rose,delice,normal)', 3, 3, 0, 1),
    ('p36', 'Baron B', 0, 'Champagnes', '', 3, 4, 0, 1),

    /* ==============================
       4. CERVEZAS
       ============================== */
    ('p19', 'Heineken', 8000, 'Cervezas', 'Lata de Heineken', 4, 1, 0, 1),
    ('p40', 'Imperial Golden', 8000, 'Cervezas', 'Lata de cerveza imperial rubia', 4, 2, 0, 1),
    ('p45', 'Imperial Ipa', 8000, 'Cervezas', 'Lata de cerveza imperial ipa', 4, 3, 0, 1),

    /* ==============================
       5. SIN ALCOHOL
       ============================== */
    ('p15', 'Lata de Gaseosa', 6000, 'Sin alcohol', 'Coca cola | Sprite', 5, 1, 0, 1),
    ('p16', 'Speed', 6000, 'Sin alcohol', 'lata de speed', 5, 2, 0, 1),
    ('p17', 'Agua', 5000, 'Sin alcohol', 'Botella de agua (natural o fria)', 5, 3, 0, 1),
    ('p37', 'Cerveza Heineken Sin alcohol', 0, 'Sin alcohol', '', 5, 4, 0, 1),
    ('p38', 'Cerveza Imperial Sin alcohol', 0, 'Sin alcohol', '', 5, 5, 0, 1),

    /* ==============================
       6. COMBOS
       ============================== */
    ('p8', 'Combo Sernova', 43000, 'Combos', '1 Sernova + 3 Speed', 6, 1, 0, 1),
    ('p9', 'Combo Fernet', 41000, 'Combos', '1 Fernet + 4 cocas lata', 6, 2, 0, 1),
    ('p10', 'Combo Smirnoff', 45000, 'Combos', '1 Smirnoff rojo/verde + 3 Speed', 6, 3, 0, 1),
    ('p11', 'Combo Skyy', 47000, 'Combos', '1 Skyy + 3 Speed', 6, 4, 0, 1),
    ('p12', 'Combo de Gin', 48000, 'Combos', '1 Gin Heráclito + 1 botella 1.5 de tónica', 6, 5, 0, 1),
    ('p14', 'Combo Absolut', 75000, 'Combos', '1 Absolut + 3 Speed', 6, 6, 0, 1),
    ('p18', 'Combo de Bombarder', 41000, 'Combos', 'Botella de bombarder de caramelo + 3 speeds', 6, 7, 0, 1),
    ('p33', 'Combo Du', 28000, 'Combos', '1 botella de Du + 2 Speed', 6, 8, 0, 1),

    /* ==============================
       7. SHOTS
       ============================== */
    ('p41', 'Shot de Vodka', 6000, 'Shot', 'Medida de vodka', 7, 1, 0, 1),
    ('p42', 'Shot de Ron', 6000, 'Shot', 'Medida de ron', 7, 2, 0, 1),
    ('p43', 'Shot de Tekila', 6000, 'Shot', 'Medida de Tekila', 7, 3, 0, 1),
    ('p44', 'Shot de Jagger', 8000, 'Shot', 'Medida de Jaggermeister', 7, 4, 0, 1),

    /* ==============================
       8. KIOSKO
       ============================== */
    ('p5', 'Chicles', 2000, 'Kiosko', '', 8, 1, 0, 1),
    ('p6', 'Etiquetas cigarrillos', 6000, 'Kiosko', '', 8, 2, 0, 1),
    ('p7', 'Papitas', 2500, 'Kiosko', '', 8, 3, 0, 1),
    ('p46', 'Preservativos', 5000, 'Kiosko', '', 8, 4, 0, 1),
    ('p47', 'Beldem', 2000, 'Kiosko', '', 8, 5, 0, 1),
    ('p48', 'Topline 7', 2500, 'Kiosko', '', 8, 6, 0, 1),
    ('p49', 'Quento', 4000, 'Kiosko', '', 8, 7, 0, 1),
    ('p50', 'Saladix', 2500, 'Kiosko', '', 8, 8, 0, 1),
    ('p51', 'Saladix Caja', 4000, 'Kiosko', '', 8, 9, 0, 1),
    ('p52', 'Chocolate', 3000, 'Kiosko', '', 8, 10, 0, 1),
    ('p53', 'Camel x10', 4500, 'Kiosko', '', 8, 11, 0, 1),

    /* ==============================
       9. EXTRAS
       ============================== */
    ('p13', 'VIP', 5000, 'Extras', '', 9, 1, 0, 1)

ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    price = VALUES(price),
    cat = VALUES(cat),
    sub = VALUES(sub),
    category_order = VALUES(category_order),
    sort_order = VALUES(sort_order),
    active = VALUES(active);

/*
 * Usar este ORDER BY en Kioskito, Carta y Administración:
 *
 * ORDER BY category_order ASC, sort_order ASC, price ASC, name ASC
 */




/* =========================================================
   DIVINE APP - STOCK DEL CONTENEDOR
   Sectores: EXTERNO / INTERNO
   Categorías conservadas: Bebidas alcohólicas, Vinos y
   espumantes, Insumos y Gaseosas.
   Stock bajo: cantidad <= 4
   ========================================================= */

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS container_stock_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(80) NOT NULL,
    sector ENUM('interno', 'externo') NOT NULL DEFAULT 'interno',
    quantity INT NOT NULL DEFAULT 0,
    low_threshold INT NOT NULL DEFAULT 4,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_container_stock_category (category),
    INDEX idx_container_stock_sector (sector),
    INDEX idx_container_stock_quantity (quantity),
    INDEX idx_container_stock_active (active),
    INDEX idx_container_stock_updated_by (updated_by),

    CONSTRAINT fk_container_stock_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Añade sector sin borrar cantidades si la tabla ya existía. */
SET @sector_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'container_stock_items'
      AND COLUMN_NAME = 'sector'
);
SET @sector_sql := IF(
    @sector_exists = 0,
    "ALTER TABLE container_stock_items ADD COLUMN sector ENUM('interno', 'externo') NOT NULL DEFAULT 'interno' AFTER category",
    'SELECT 1'
);
PREPARE stock_sector_stmt FROM @sector_sql;
EXECUTE stock_sector_stmt;
DEALLOCATE PREPARE stock_sector_stmt;

CREATE TABLE IF NOT EXISTS container_stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    movement_type ENUM('adjust', 'set') NOT NULL DEFAULT 'adjust',
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    delta INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_container_movements_item (item_id),
    INDEX idx_container_movements_user (user_id),
    INDEX idx_container_movements_date (created_at),

    CONSTRAINT fk_container_movements_item
        FOREIGN KEY (item_id)
        REFERENCES container_stock_items(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_container_movements_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO container_stock_items (
    code,
    name,
    category,
    sector,
    quantity,
    low_threshold,
    sort_order,
    active
)
VALUES
    /* ==============================
       EXTERNO — BEBIDAS ALCOHÓLICAS
       ============================== */

    ('daemong', 'Daemong', 'Bebidas alcohólicas', 'externo', 0, 4, 1, 1),
    ('sernova_rojo', 'Vodka Sernova rojo', 'Bebidas alcohólicas', 'externo', 0, 4, 2, 1),
    ('sernova_maracuya', 'Vodka Sernova maracuyá', 'Bebidas alcohólicas', 'externo', 0, 4, 3, 1),
    ('sernova_verde', 'Vodka Sernova verde', 'Bebidas alcohólicas', 'externo', 0, 4, 4, 1),
    ('fernet_chico', 'Fernet chico', 'Bebidas alcohólicas', 'externo', 0, 4, 5, 1),
    ('fernet_grande', 'Fernet grande', 'Bebidas alcohólicas', 'externo', 0, 4, 6, 1),
    ('vodka_barato', 'Vodka barato', 'Bebidas alcohólicas', 'externo', 0, 4, 7, 1),
    ('gancia', 'Gancia', 'Bebidas alcohólicas', 'externo', 0, 4, 8, 1),
    ('campari', 'Campari', 'Bebidas alcohólicas', 'externo', 0, 4, 9, 1),
    ('malibu', 'Malibu', 'Bebidas alcohólicas', 'externo', 0, 4, 10, 1),
    ('gin_gordon', 'Gin Gordon', 'Bebidas alcohólicas', 'externo', 0, 4, 11, 1),

    /* ==============================
       EXTERNO — VINOS Y ESPUMANTES
       ============================== */

    ('dilema_blanco', 'Dilema blanco', 'Vinos y espumantes', 'externo', 0, 4, 12, 1),
    ('dilema_rosado', 'Dilema rosado', 'Vinos y espumantes', 'externo', 0, 4, 13, 1),
    ('dilema_tinto', 'Dilema tinto', 'Vinos y espumantes', 'externo', 0, 4, 14, 1),
    ('santa_julia_blanco', 'Santa Julia blanco', 'Vinos y espumantes', 'externo', 0, 4, 15, 1),
    ('santa_julia_tinto', 'Santa Julia tinto', 'Vinos y espumantes', 'externo', 0, 4, 16, 1),
    ('du', 'DU', 'Vinos y espumantes', 'externo', 0, 4, 17, 1),
    ('baron_b', 'Baron B', 'Vinos y espumantes', 'externo', 0, 4, 18, 1),
    ('mumm', 'Mumm', 'Vinos y espumantes', 'externo', 0, 4, 19, 1),
    ('chandon', 'Chandon', 'Vinos y espumantes', 'externo', 0, 4, 20, 1),

    /* ==============================
       INTERNO — INSUMOS
       ============================== */

    ('caja_vasos', 'Caja de vasos', 'Insumos', 'interno', 0, 4, 21, 1),
    ('caja_fraperas', 'Caja de fráperas', 'Insumos', 'interno', 0, 4, 22, 1),
    ('caja_sorbetes', 'Caja de sorbetes', 'Insumos', 'interno', 0, 4, 23, 1),
    ('jugos', 'Jugos', 'Insumos', 'interno', 0, 4, 24, 1),

    /* ==============================
       INTERNO — GASEOSAS Y BEBIDAS
       ============================== */

    ('coca_lata', 'Coca en lata', 'Gaseosas', 'interno', 0, 4, 25, 1),
    ('sprite_lata', 'Sprite en lata', 'Gaseosas', 'interno', 0, 4, 26, 1),
    ('coca_zero_lata', 'Coca Zero en lata', 'Gaseosas', 'interno', 0, 4, 27, 1),
    ('speed', 'Speed', 'Gaseosas', 'interno', 0, 4, 28, 1),
    ('agua', 'Agua', 'Gaseosas', 'interno', 0, 4, 29, 1),
    ('sprite_botella', 'Sprite en botella', 'Gaseosas', 'interno', 0, 4, 30, 1),
    ('coca_botella', 'Coca en botella', 'Gaseosas', 'interno', 0, 4, 31, 1),
    ('tonica_botella', 'Tónica en botella', 'Gaseosas', 'interno', 0, 4, 32, 1)

ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category = VALUES(category),
    sector = VALUES(sector),
    low_threshold = VALUES(low_threshold),
    sort_order = VALUES(sort_order),
    active = VALUES(active);

/* =========================================================
    FIN DEL ARCHIVO
   ========================================================= */

SET FOREIGN_KEY_CHECKS = 1;
