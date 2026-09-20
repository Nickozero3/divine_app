/* =========================================================
   DIVINE APP - MIGRACIÓN: KIOSKITO VIP
   ---------------------------------------------------------
   Este archivo es idempotente y ADITIVO:
   - No borra ni modifica datos existentes.
   - No toca el ENUM de roles de usuarios.
   - Agrega la columna products.zona para separar catálogo
     Kiosko / VIP (por defecto 'ambos', así ningún producto
     existente deja de mostrarse en el Kioskito normal).
   - Crea vip_sales y vip_closings, espejo exacto de
     kiosko_sales / kiosko_closings, para que la Caja VIP
     sea 100% independiente de la Caja Kiosko.

   Ejecutar una sola vez sobre la base ya existente:
     mysql -u USUARIO -p NOMBRE_BASE < db/02_kiosko_vip.sql
   ========================================================= */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


/* =========================================================
   COMPATIBILIDAD: products.zona
   ---------------------------------------------------------
   'kiosko' -> solo aparece en el Kioskito normal
   'vip'    -> solo aparece en el Kioskito VIP
   'ambos'  -> aparece en los dos (valor por defecto,
               así los productos ya cargados no desaparecen)
   ========================================================= */

SET @products_zona_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'zona'
);
SET @products_zona_sql := IF(
    @products_zona_exists = 0,
    "ALTER TABLE products ADD COLUMN zona ENUM('kiosko','vip','ambos') NOT NULL DEFAULT 'ambos' AFTER cat",
    'DO 0'
);
PREPARE products_zona_stmt FROM @products_zona_sql;
EXECUTE products_zona_stmt;
DEALLOCATE PREPARE products_zona_stmt;

SET @products_zona_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'idx_products_zona'
);
SET @products_zona_index_sql := IF(
    @products_zona_index_exists = 0,
    'ALTER TABLE products ADD INDEX idx_products_zona (zona)',
    'DO 0'
);
PREPARE products_zona_index_stmt FROM @products_zona_index_sql;
EXECUTE products_zona_index_stmt;
DEALLOCATE PREPARE products_zona_index_stmt;


/* =========================================================
   TABLA: vip_sales
   ---------------------------------------------------------
   Espejo exacto de kiosko_sales, pero para la Caja VIP.
   Ninguna venta de acá se mezcla con kiosko_sales.
   ========================================================= */

CREATE TABLE IF NOT EXISTS vip_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,

    client_sale_id VARCHAR(80) DEFAULT NULL,
    user_id INT NOT NULL,

    items LONGTEXT NOT NULL,
    total INT NOT NULL DEFAULT 0,

    payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo')
        NOT NULL DEFAULT 'efectivo',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_vip_sales_client_sale_id (client_sale_id),
    INDEX idx_vip_sales_user_id (user_id),
    INDEX idx_vip_sales_created_at (created_at),

    CONSTRAINT fk_vip_sales_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TABLA: vip_closings
   ---------------------------------------------------------
   Espejo exacto de kiosko_closings, para el cierre de la
   Caja VIP. Independiente del cierre de Caja Kiosko.
   ========================================================= */

CREATE TABLE IF NOT EXISTS vip_closings (
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

    INDEX idx_vip_closings_user_id (user_id),
    INDEX idx_vip_closings_sale_range (from_sale_id, to_sale_id),
    INDEX idx_vip_closings_created_at (created_at),
    INDEX idx_vip_closings_deleted_at (deleted_at),

    CONSTRAINT fk_vip_closings_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
