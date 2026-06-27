<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/conexion.php';

$stockAction = trim((string) ($_GET['stock_action'] ?? ''));
$isStockRequest = $stockAction !== '';
$stockRole = strtolower(trim((string) (
    $currentRole
    ?? $currentUser['role']
    ?? $_SESSION['user']['role']
    ?? ''
)));

if ($stockRole !== 'admin') {
    if ($isStockRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Solo un administrador puede manejar el stock del contenedor.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Acceso denegado</title>
      <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#09070d;color:#fff;font-family:Arial,sans-serif;padding:24px}
        .box{max-width:460px;padding:28px;border-radius:22px;background:#171320;border:1px solid #43335f;text-align:center}
        a{display:inline-block;margin-top:16px;color:#f0d48d;text-decoration:none;font-weight:700}
      </style>
    </head>
    <body>
      <div class="box">
        <h1>Acceso restringido</h1>
        <p>El stock del contenedor solo puede ser administrado por un usuario con rol admin.</p>
        <a href="index.php">Volver al inicio</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

if (!function_exists('stock_json')) {
    function stock_json(array $payload, int $status = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('stock_input')) {
    function stock_input(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST ?: [];
    }
}

if (!function_exists('stock_seed_items')) {
    function stock_seed_items(): array
    {
        return [
            // EXTERNO: se cuentan cajas de bebidas.
            ['daemong', 'Daemong', 'Bebidas alcohólicas', 'externo'],
            ['sernova_rojo', 'Vodka Sernova rojo', 'Bebidas alcohólicas', 'externo'],
            ['sernova_maracuya', 'Vodka Sernova maracuyá', 'Bebidas alcohólicas', 'externo'],
            ['sernova_verde', 'Vodka Sernova verde', 'Bebidas alcohólicas', 'externo'],
            ['fernet_chico', 'Fernet chico', 'Bebidas alcohólicas', 'externo'],
            ['fernet_grande', 'Fernet grande', 'Bebidas alcohólicas', 'externo'],
            ['vodka_barato', 'Vodka barato', 'Bebidas alcohólicas', 'externo'],
            ['gancia', 'Gancia', 'Bebidas alcohólicas', 'externo'],
            ['campari', 'Campari', 'Bebidas alcohólicas', 'externo'],
            ['dilema_blanco', 'Dilema blanco', 'Vinos y espumantes', 'externo'],
            ['dilema_rosado', 'Dilema rosado', 'Vinos y espumantes', 'externo'],
            ['dilema_tinto', 'Dilema tinto', 'Vinos y espumantes', 'externo'],
            ['santa_julia_blanco', 'Santa Julia blanco', 'Vinos y espumantes', 'externo'],
            ['santa_julia_tinto', 'Santa Julia tinto', 'Vinos y espumantes', 'externo'],
            ['du', 'DU', 'Vinos y espumantes', 'externo'],
            ['baron_b', 'Baron B', 'Vinos y espumantes', 'externo'],

            // INTERNO: insumos, gaseosas, latas, agua y energizantes.
            ['vasos', 'Vasos', 'Insumos', 'interno'],
            ['fraperas', 'Fráperas', 'Insumos', 'interno'],
            ['sorbetes', 'Sorbetes', 'Insumos', 'interno'],
            ['jugos', 'Jugos', 'Insumos', 'interno'],
            ['coca_lata', 'Coca en lata', 'Gaseosas', 'interno'],
            ['sprite', 'Sprite', 'Gaseosas', 'interno'],
            ['coca_zero', 'Coca Zero', 'Gaseosas', 'interno'],
            ['speed', 'Speed', 'Gaseosas', 'interno'],
            ['agua', 'Agua', 'Gaseosas', 'interno'],
            ['sprite_botella', 'Sprite en botella', 'Gaseosas', 'interno'],
            ['coca_botella', 'Coca en botella', 'Gaseosas', 'interno'],
            ['tonicas_botella', 'Tónicas en botella', 'Gaseosas', 'interno'],
        ];
    }
}

if (!function_exists('stock_ensure_schema')) {
    function stock_ensure_schema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
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
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Compatibilidad con instalaciones que ya tenían la tabla creada.
        $sectorColumn = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'container_stock_items'
  AND COLUMN_NAME = 'sector'
SQL);
        $sectorColumn->execute();

        if ((int) $sectorColumn->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE container_stock_items ADD COLUMN sector ENUM('interno', 'externo') NOT NULL DEFAULT 'interno' AFTER category");
        }

        $pdo->exec(<<<'SQL'
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
        FOREIGN KEY (item_id) REFERENCES container_stock_items(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_container_movements_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $seed = $pdo->prepare(<<<'SQL'
INSERT INTO container_stock_items
    (code, name, category, sector, quantity, low_threshold, sort_order, active)
VALUES
    (:code, :name, :category, :sector, 0, 4, :sort_order, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category = VALUES(category),
    sector = VALUES(sector),
    sort_order = VALUES(sort_order),
    active = 1
SQL);

        foreach (stock_seed_items() as $index => [$code, $name, $category, $sector]) {
            $seed->execute([
                ':code' => $code,
                ':name' => $name,
                ':category' => $category,
                ':sector' => $sector,
                ':sort_order' => $index + 1,
            ]);
        }
    }
}

if (!function_exists('stock_fetch_items')) {
    function stock_fetch_items(PDO $pdo): array
    {
        $stmt = $pdo->query(<<<'SQL'
SELECT
    i.id,
    i.code,
    i.name,
    i.category,
    i.sector,
    i.quantity,
    i.low_threshold,
    i.updated_at,
    COALESCE(u.display_name, u.username, '') AS updated_by_name
FROM container_stock_items i
LEFT JOIN users u ON u.id = i.updated_by
WHERE i.active = 1
ORDER BY i.sort_order ASC, i.name ASC
SQL);

        $items = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quantity = max(0, (int) $row['quantity']);
            $threshold = max(0, (int) $row['low_threshold']);

            $status = 'stock';
            if ($quantity === 0) {
                $status = 'empty';
            } elseif ($quantity <= $threshold) {
                $status = 'low';
            }

            $items[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'category' => (string) $row['category'],
                'sector' => (string) ($row['sector'] ?? 'interno'),
                'quantity' => $quantity,
                'lowThreshold' => $threshold,
                'status' => $status,
                'updatedAt' => $row['updated_at'],
                'updatedBy' => (string) ($row['updated_by_name'] ?? ''),
            ];
        }

        return $items;
    }
}

if (!function_exists('stock_summary')) {
    function stock_summary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'empty' => 0,
            'low' => 0,
            'stock' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'stock');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        return $summary;
    }
}

try {
    stock_ensure_schema($pdo);

    if ($isStockRequest) {
        $input = stock_input();
        $userId = (int) ($currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);

        switch ($stockAction) {
            case 'list':
                $items = stock_fetch_items($pdo);
                stock_json([
                    'ok' => true,
                    'items' => $items,
                    'summary' => stock_summary($items),
                    'lowLimit' => 4,
                ]);

            case 'adjust':
            case 'set':
                $itemId = (int) ($input['id'] ?? 0);
                if ($itemId <= 0) {
                    stock_json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
                }

                $pdo->beginTransaction();

                $lock = $pdo->prepare('SELECT quantity FROM container_stock_items WHERE id = :id AND active = 1 FOR UPDATE');
                $lock->execute([':id' => $itemId]);
                $row = $lock->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $pdo->rollBack();
                    stock_json(['ok' => false, 'error' => 'El artículo no existe.'], 404);
                }

                $previous = max(0, (int) $row['quantity']);

                if ($stockAction === 'adjust') {
                    $delta = (int) ($input['delta'] ?? 0);
                    if ($delta === 0 || abs($delta) > 1000) {
                        $pdo->rollBack();
                        stock_json(['ok' => false, 'error' => 'Ajuste de cantidad inválido.'], 422);
                    }
                    $newQuantity = max(0, $previous + $delta);
                } else {
                    $newQuantity = (int) ($input['quantity'] ?? -1);
                    if ($newQuantity < 0 || $newQuantity > 100000) {
                        $pdo->rollBack();
                        stock_json(['ok' => false, 'error' => 'La cantidad debe estar entre 0 y 100000.'], 422);
                    }
                    $delta = $newQuantity - $previous;
                }

                $update = $pdo->prepare(<<<'SQL'
UPDATE container_stock_items
SET quantity = :quantity,
    updated_by = :updated_by,
    updated_at = NOW()
WHERE id = :id
SQL);
                $update->execute([
                    ':quantity' => $newQuantity,
                    ':updated_by' => $userId > 0 ? $userId : null,
                    ':id' => $itemId,
                ]);

                $movement = $pdo->prepare(<<<'SQL'
INSERT INTO container_stock_movements
    (item_id, user_id, movement_type, previous_quantity, new_quantity, delta)
VALUES
    (:item_id, :user_id, :movement_type, :previous_quantity, :new_quantity, :delta)
SQL);
                $movement->execute([
                    ':item_id' => $itemId,
                    ':user_id' => $userId > 0 ? $userId : null,
                    ':movement_type' => $stockAction === 'set' ? 'set' : 'adjust',
                    ':previous_quantity' => $previous,
                    ':new_quantity' => $newQuantity,
                    ':delta' => $delta,
                ]);

                $pdo->commit();

                $items = stock_fetch_items($pdo);
                stock_json([
                    'ok' => true,
                    'items' => $items,
                    'summary' => stock_summary($items),
                ]);

            default:
                stock_json(['ok' => false, 'error' => 'Acción desconocida.'], 404);
        }
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($isStockRequest) {
        stock_json([
            'ok' => false,
            'error' => 'No se pudo procesar el stock del contenedor.',
            'detail' => $error->getMessage(),
        ], 500);
    }

    $stockPageError = $error->getMessage();
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#09070d">
  <title>Stock del contenedor · <?= e((string) APP_NAME) ?></title>

  <link rel="icon" type="image/x-icon" href="./favicon.ico">
  <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
  <link rel="stylesheet" href="styles/theme.css?v=<?= time() ?>">
  <link rel="stylesheet" href="styles/stock-contenedor.css?v=<?= time() ?>">

  <script src="js/theme.js?v=<?= time() ?>" defer></script>
  <script src="js/stock-contenedor.js?v=<?= time() ?>" defer></script>
</head>
<body data-page="stock-contenedor">
  <div class="stars" aria-hidden="true"></div>

  <header class="stock-topbar">
    <a class="stock-back" href="index.php" aria-label="Volver al menú">‹</a>

    <div class="stock-title-wrap">
      <span class="stock-kicker">CONTROL INTERNO</span>
      <h1>Stock del contenedor</h1>
    </div>

    <div class="stock-user" title="Solo administradores">
      <span>🔒</span>
      <span><?= e((string) ($currentUser['display_name'] ?? 'Admin')) ?></span>
    </div>
  </header>

  <main class="stock-shell">
    <?php if (!empty($stockPageError)): ?>
      <section class="stock-error">
        <strong>No se pudo iniciar el módulo.</strong>
        <span><?= e((string) $stockPageError) ?></span>
      </section>
    <?php endif; ?>

    <section class="stock-hero">
      <div>
        <span class="stock-eyebrow">Inventario de la noche</span>
        <h2>Controlá lo que queda y detectá faltantes al instante.</h2>
        <p>Se considera stock bajo cuando quedan 4 unidades o menos.</p>
      </div>

      <button class="stock-report-main" type="button" id="stockReportButton">
        <span>📋</span>
        <span>Generar lista de faltantes</span>
      </button>
    </section>

    <section class="stock-summary" aria-label="Resumen del stock">
      <article class="stock-summary-card">
        <span class="stock-summary-icon">📦</span>
        <div>
          <strong id="summaryTotal">—</strong>
          <span>Artículos</span>
        </div>
      </article>

      <article class="stock-summary-card is-empty">
        <span class="stock-summary-icon">●</span>
        <div>
          <strong id="summaryEmpty">—</strong>
          <span>Agotados</span>
        </div>
      </article>

      <article class="stock-summary-card is-low">
        <span class="stock-summary-icon">●</span>
        <div>
          <strong id="summaryLow">—</strong>
          <span>Stock bajo</span>
        </div>
      </article>

      <article class="stock-summary-card is-ok">
        <span class="stock-summary-icon">●</span>
        <div>
          <strong id="summaryStock">—</strong>
          <span>En stock</span>
        </div>
      </article>
    </section>

    <section class="stock-sector-switch" aria-label="Sector del stock">
      <button type="button" class="stock-sector-filter is-active" data-sector="externo">
        <span class="stock-sector-icon"></span>
        <span>
          <strong>Externo</strong>
          <small>Cajas de bebidas</small>
        </span>
      </button>

      <button type="button" class="stock-sector-filter" data-sector="interno">
        <span class="stock-sector-icon">🏠</span>
        <span>
          <strong>Interno</strong>
          <small>Insumos, gaseosas y latas</small>
        </span>
      </button>
    </section>

    <section class="stock-controls">
      <label class="stock-search">
        <span>⌕</span>
        <input id="stockSearch" type="search" placeholder="Buscar producto o insumo" autocomplete="off">
      </label>

      <div class="stock-filters" role="group" aria-label="Filtrar stock">
        <button type="button" class="stock-filter is-active" data-filter="all">Todos</button>
        <button type="button" class="stock-filter" data-filter="low">Bajo ≤ 4</button>
        <button type="button" class="stock-filter" data-filter="empty">Agotados</button>
        <button type="button" class="stock-filter" data-filter="stock">En stock</button>
      </div>
    </section>

    <section id="stockContent" class="stock-content" aria-live="polite">
      <div class="stock-loading">
        <span class="stock-spinner"></span>
        <span>Cargando inventario…</span>
      </div>
    </section>
  </main>

  <div class="stock-modal" id="stockReportModal" aria-hidden="true">
    <div class="stock-modal-backdrop" data-close-report></div>

    <section class="stock-modal-card" role="dialog" aria-modal="true" aria-labelledby="stockReportTitle">
      <div class="stock-modal-head">
        <div>
          <span class="stock-eyebrow">CIERRE DE LA NOCHE</span>
          <h2 id="stockReportTitle">Lista de faltantes</h2>
        </div>
        <button type="button" class="stock-modal-close" data-close-report aria-label="Cerrar">×</button>
      </div>

      <textarea id="stockReportText" class="stock-report-text" readonly></textarea>

      <div class="stock-modal-actions">
        <button type="button" class="stock-secondary-button" id="stockCopyButton">Copiar lista</button>
        <button type="button" class="stock-primary-button" id="stockShareButton">Compartir</button>
      </div>
    </section>
  </div>

  <div class="stock-toast" id="stockToast" role="status" aria-live="polite"></div>

  <footer class="theme-footer" aria-label="Preferencias visuales">
    <button type="button" class="theme-toggle" id="themeToggle" data-theme-toggle aria-label="Cambiar tema">
      <span class="theme-toggle__icon" aria-hidden="true">◐</span>
      <span class="theme-toggle__copy">
        <span class="theme-toggle__eyebrow">Tema visual</span>
        <span class="theme-toggle__label" data-theme-label>Cambiar tema</span>
      </span>
      <span class="theme-toggle__track" aria-hidden="true">
        <span class="theme-toggle__thumb"></span>
      </span>
    </button>
  </footer>
</body>
</html>
