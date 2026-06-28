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


if (!function_exists('stock_text_length')) {
    function stock_text_length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

if (!function_exists('stock_make_code')) {
    function stock_make_code(string $value): string
    {
        $value = trim($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return substr($value !== '' ? $value : 'articulo', 0, 68);
    }
}

if (!function_exists('stock_validate_product')) {
    function stock_validate_product(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $sector = strtolower(trim((string) ($input['sector'] ?? 'interno')));
        $quantity = (int) ($input['quantity'] ?? 0);
        $lowThreshold = (int) ($input['lowThreshold'] ?? 4);

        if (stock_text_length($name) < 2 || stock_text_length($name) > 120) {
            stock_json(['ok' => false, 'error' => 'El nombre debe tener entre 2 y 120 caracteres.'], 422);
        }

        if (stock_text_length($category) < 2 || stock_text_length($category) > 80) {
            stock_json(['ok' => false, 'error' => 'La categoría debe tener entre 2 y 80 caracteres.'], 422);
        }

        if (!in_array($sector, ['interno', 'externo'], true)) {
            stock_json(['ok' => false, 'error' => 'El sector seleccionado no es válido.'], 422);
        }

        if ($quantity < 0 || $quantity > 100000) {
            stock_json(['ok' => false, 'error' => 'La cantidad debe estar entre 0 y 100000.'], 422);
        }

        if ($lowThreshold < 0 || $lowThreshold > 100000) {
            stock_json(['ok' => false, 'error' => 'El límite de stock bajo debe estar entre 0 y 100000.'], 422);
        }

        return [
            'name' => $name,
            'category' => $category,
            'sector' => $sector,
            'quantity' => $quantity,
            'lowThreshold' => $lowThreshold,
        ];
    }
}

if (!function_exists('stock_unique_code')) {
    function stock_unique_code(PDO $pdo, string $name): string
    {
        $baseCode = stock_make_code($name);
        $code = $baseCode;
        $suffix = 2;
        $exists = $pdo->prepare('SELECT COUNT(*) FROM container_stock_items WHERE code = :code');

        while (true) {
            $exists->execute([':code' => $code]);
            if ((int) $exists->fetchColumn() === 0) {
                return $code;
            }

            $suffixText = '_' . $suffix;
            $code = substr($baseCode, 0, 80 - strlen($suffixText)) . $suffixText;
            $suffix++;
        }
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

            case 'add':
                $product = stock_validate_product($input);

                $pdo->beginTransaction();

                $duplicate = $pdo->prepare(<<<'SQL'
SELECT id
FROM container_stock_items
WHERE name = :name
  AND sector = :sector
  AND active = 1
LIMIT 1
FOR UPDATE
SQL);
                $duplicate->execute([
                    ':name' => $product['name'],
                    ':sector' => $product['sector'],
                ]);

                if ($duplicate->fetchColumn()) {
                    $pdo->rollBack();
                    stock_json([
                        'ok' => false,
                        'error' => 'Ya existe un artículo con ese nombre en el sector seleccionado.',
                    ], 409);
                }

                $code = stock_unique_code($pdo, $product['name']);
                $nextOrder = (int) $pdo->query(
                    'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM container_stock_items'
                )->fetchColumn();

                $insert = $pdo->prepare(<<<'SQL'
INSERT INTO container_stock_items
    (code, name, category, sector, quantity, low_threshold, sort_order, active, updated_by)
VALUES
    (:code, :name, :category, :sector, :quantity, :low_threshold, :sort_order, 1, :updated_by)
SQL);
                $insert->execute([
                    ':code' => $code,
                    ':name' => $product['name'],
                    ':category' => $product['category'],
                    ':sector' => $product['sector'],
                    ':quantity' => $product['quantity'],
                    ':low_threshold' => $product['lowThreshold'],
                    ':sort_order' => $nextOrder,
                    ':updated_by' => $userId > 0 ? $userId : null,
                ]);

                $itemId = (int) $pdo->lastInsertId();

                if ($product['quantity'] !== 0) {
                    $movement = $pdo->prepare(<<<'SQL'
INSERT INTO container_stock_movements
    (item_id, user_id, movement_type, previous_quantity, new_quantity, delta)
VALUES
    (:item_id, :user_id, 'set', 0, :new_quantity, :delta)
SQL);
                    $movement->execute([
                        ':item_id' => $itemId,
                        ':user_id' => $userId > 0 ? $userId : null,
                        ':new_quantity' => $product['quantity'],
                        ':delta' => $product['quantity'],
                    ]);
                }

                $pdo->commit();

                $items = stock_fetch_items($pdo);
                stock_json([
                    'ok' => true,
                    'message' => 'Artículo añadido correctamente.',
                    'items' => $items,
                    'summary' => stock_summary($items),
                ], 201);

            case 'edit':
                $itemId = (int) ($input['id'] ?? 0);
                if ($itemId <= 0) {
                    stock_json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
                }

                $product = stock_validate_product($input);
                $pdo->beginTransaction();

                $lock = $pdo->prepare(<<<'SQL'
SELECT id, quantity
FROM container_stock_items
WHERE id = :id
  AND active = 1
FOR UPDATE
SQL);
                $lock->execute([':id' => $itemId]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);

                if (!$current) {
                    $pdo->rollBack();
                    stock_json(['ok' => false, 'error' => 'El artículo no existe.'], 404);
                }

                $duplicate = $pdo->prepare(<<<'SQL'
SELECT id
FROM container_stock_items
WHERE name = :name
  AND sector = :sector
  AND active = 1
  AND id <> :id
LIMIT 1
SQL);
                $duplicate->execute([
                    ':name' => $product['name'],
                    ':sector' => $product['sector'],
                    ':id' => $itemId,
                ]);

                if ($duplicate->fetchColumn()) {
                    $pdo->rollBack();
                    stock_json([
                        'ok' => false,
                        'error' => 'Ya existe otro artículo con ese nombre en el sector seleccionado.',
                    ], 409);
                }

                $previousQuantity = max(0, (int) $current['quantity']);

                $update = $pdo->prepare(<<<'SQL'
UPDATE container_stock_items
SET name = :name,
    category = :category,
    sector = :sector,
    quantity = :quantity,
    low_threshold = :low_threshold,
    updated_by = :updated_by,
    updated_at = NOW()
WHERE id = :id
SQL);
                $update->execute([
                    ':name' => $product['name'],
                    ':category' => $product['category'],
                    ':sector' => $product['sector'],
                    ':quantity' => $product['quantity'],
                    ':low_threshold' => $product['lowThreshold'],
                    ':updated_by' => $userId > 0 ? $userId : null,
                    ':id' => $itemId,
                ]);

                if ($previousQuantity !== $product['quantity']) {
                    $movement = $pdo->prepare(<<<'SQL'
INSERT INTO container_stock_movements
    (item_id, user_id, movement_type, previous_quantity, new_quantity, delta)
VALUES
    (:item_id, :user_id, 'set', :previous_quantity, :new_quantity, :delta)
SQL);
                    $movement->execute([
                        ':item_id' => $itemId,
                        ':user_id' => $userId > 0 ? $userId : null,
                        ':previous_quantity' => $previousQuantity,
                        ':new_quantity' => $product['quantity'],
                        ':delta' => $product['quantity'] - $previousQuantity,
                    ]);
                }

                $pdo->commit();

                $items = stock_fetch_items($pdo);
                stock_json([
                    'ok' => true,
                    'message' => 'Artículo actualizado correctamente.',
                    'items' => $items,
                    'summary' => stock_summary($items),
                ]);

            case 'delete':
                $itemId = (int) ($input['id'] ?? 0);
                if ($itemId <= 0) {
                    stock_json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
                }

                $pdo->beginTransaction();

                $lock = $pdo->prepare(<<<'SQL'
SELECT name
FROM container_stock_items
WHERE id = :id
  AND active = 1
FOR UPDATE
SQL);
                $lock->execute([':id' => $itemId]);
                $itemName = $lock->fetchColumn();

                if ($itemName === false) {
                    $pdo->rollBack();
                    stock_json(['ok' => false, 'error' => 'El artículo no existe.'], 404);
                }

                $delete = $pdo->prepare('DELETE FROM container_stock_items WHERE id = :id');
                $delete->execute([':id' => $itemId]);
                $pdo->commit();

                $items = stock_fetch_items($pdo);
                stock_json([
                    'ok' => true,
                    'message' => 'Artículo eliminado correctamente.',
                    'deletedName' => (string) $itemName,
                    'items' => $items,
                    'summary' => stock_summary($items),
                ]);

            case 'adjust':
            case 'set':
                $itemId = (int) ($input['id'] ?? 0);
                if ($itemId <= 0) {
                    stock_json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
                }

                $pdo->beginTransaction();

                $lock = $pdo->prepare(
                    'SELECT quantity FROM container_stock_items WHERE id = :id AND active = 1 FOR UPDATE'
                );
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
                    $delta = $newQuantity - $previous;
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

                if ($delta !== 0) {
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
                }

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
  <link rel="stylesheet" href="styles/stock-contenedor-crud.css?v=<?= time() ?>">

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

      <div class="stock-hero-actions">
        <div class="stock-management-actions" aria-label="Administrar productos">
          <button class="stock-add-main" type="button" id="stockAddButton">
            <span aria-hidden="true">＋</span>
            <span>Añadir producto</span>
          </button>

          <div class="stock-management-row">
            <button class="stock-manage-button" type="button" id="stockEditButton">
              <span aria-hidden="true">✎</span>
              <span>Editar producto</span>
            </button>

            <button class="stock-manage-button is-danger" type="button" id="stockDeleteButton">
              <span aria-hidden="true">🗑</span>
              <span>Eliminar producto</span>
            </button>
          </div>
        </div>

        <button class="stock-report-main" type="button" id="stockReportButton">
          <span>📋</span>
          <span>Generar lista de faltantes</span>
        </button>
      </div>
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



  <div class="stock-modal" id="stockSelectModal" aria-hidden="true">
    <div class="stock-modal-backdrop" data-close-select></div>

    <section class="stock-modal-card stock-select-modal-card" role="dialog" aria-modal="true" aria-labelledby="stockSelectTitle">
      <div class="stock-modal-head">
        <div>
          <span class="stock-eyebrow" id="stockSelectEyebrow">SELECCIONAR ARTÍCULO</span>
          <h2 id="stockSelectTitle">Elegí un producto</h2>
        </div>
        <button type="button" class="stock-modal-close" data-close-select aria-label="Cerrar">×</button>
      </div>

      <p class="stock-select-help" id="stockSelectHelp">Seleccioná el producto que querés administrar.</p>

      <label class="stock-select-search">
        <span aria-hidden="true">⌕</span>
        <input id="stockSelectSearch" type="search" placeholder="Buscar producto o categoría" autocomplete="off">
      </label>

      <div class="stock-select-list" id="stockSelectList" role="list"></div>
    </section>
  </div>

  <div class="stock-modal" id="stockProductModal" aria-hidden="true">
    <div class="stock-modal-backdrop" data-close-product></div>

    <section class="stock-modal-card stock-product-modal-card" role="dialog" aria-modal="true" aria-labelledby="stockProductTitle">
      <div class="stock-modal-head">
        <div>
          <span class="stock-eyebrow" id="stockProductEyebrow">NUEVO ARTÍCULO</span>
          <h2 id="stockProductTitle">Añadir producto</h2>
        </div>
        <button type="button" class="stock-modal-close" data-close-product aria-label="Cerrar">×</button>
      </div>

      <form id="stockProductForm" class="stock-product-form">
        <input type="hidden" id="stockProductId" name="id" value="">

        <label class="stock-form-field stock-form-field-full">
          <span>Nombre del producto</span>
          <input id="stockProductName" name="name" type="text" minlength="2" maxlength="120" required autocomplete="off" placeholder="Ej.: Caja de vasos">
        </label>

        <label class="stock-form-field stock-form-field-full">
          <span>Categoría</span>
          <input id="stockProductCategory" name="category" type="text" minlength="2" maxlength="80" required autocomplete="off" list="stockCategoryOptions" placeholder="Ej.: Insumos">
          <datalist id="stockCategoryOptions"></datalist>
        </label>

        <label class="stock-form-field">
          <span>Sector</span>
          <select id="stockProductSector" name="sector" required>
            <option value="externo">Externo</option>
            <option value="interno">Interno</option>
          </select>
        </label>

        <label class="stock-form-field">
          <span>Cantidad</span>
          <input id="stockProductQuantity" name="quantity" type="number" min="0" max="100000" step="1" inputmode="numeric" value="0" required>
        </label>

        <label class="stock-form-field stock-form-field-full">
          <span>Avisar como stock bajo desde</span>
          <input id="stockProductThreshold" name="lowThreshold" type="number" min="0" max="100000" step="1" inputmode="numeric" value="4" required>
          <small>El artículo se marcará como bajo cuando tenga esta cantidad o menos.</small>
        </label>

        <div class="stock-form-error" id="stockProductError" role="alert"></div>

        <div class="stock-modal-actions stock-product-actions">
          <button type="button" class="stock-secondary-button" data-close-product>Cancelar</button>
          <button type="submit" class="stock-primary-button" id="stockProductSubmit">Guardar producto</button>
        </div>
      </form>
    </section>
  </div>

  <div class="stock-modal" id="stockDeleteModal" aria-hidden="true">
    <div class="stock-modal-backdrop" data-close-delete></div>

    <section class="stock-modal-card stock-delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="stockDeleteTitle">
      <div class="stock-delete-icon" aria-hidden="true">🗑️</div>
      <div class="stock-delete-copy">
        <span class="stock-eyebrow">ELIMINAR ARTÍCULO</span>
        <h2 id="stockDeleteTitle">¿Eliminar este producto?</h2>
        <p id="stockDeleteDescription">Esta acción también elimina su historial de movimientos y no se puede deshacer.</p>
      </div>

      <div class="stock-modal-actions">
        <button type="button" class="stock-secondary-button" data-close-delete>Cancelar</button>
        <button type="button" class="stock-danger-button" id="stockDeleteConfirm">Eliminar</button>
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
