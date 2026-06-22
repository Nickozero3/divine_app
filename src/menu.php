<?php
declare(strict_types=1);

require_once __DIR__ . '/config/conexion.php';

if (file_exists(__DIR__ . '/const.php')) {
    require_once __DIR__ . '/const.php';
}

$appName = defined('APP_NAME') ? APP_NAME : 'Menú';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money(int|float|string|null $value): string
{
    return '$' . number_format((float)($value ?? 0), 0, ',', '.');
}

function columnExists(PDO $pdo, string $table, string $column): bool
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

function normalizeCategory(string $cat): string
{
    $cat = trim($cat);
    $catLower = mb_strtolower($cat, 'UTF-8');

    return match ($catLower) {
        'vaso', 'vasos' => 'Vasos',
        'combo', 'combos' => 'Combos',
        'sin alcohol', 'sinalcohol' => 'Sin alcohol',
        'cerveza', 'cervezas' => 'Cervezas',
        'vino', 'vinos' => 'Vinos',
        'champagne', 'champagnes', 'champaña', 'champañas' => 'Champagnes',
        'shot', 'shots' => 'Shot',

        // Categoría Kiosko
        'kiosko', 'kiosco', 'snack', 'snacks' => 'Kiosko',

        default => $cat !== '' ? $cat : 'Sin categoría',
    };
}

function categorySlug(string $category): string
{
    $slug = mb_strtolower(trim($category), 'UTF-8');

    $slug = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'n'],
        $slug
    );

    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-') ?: 'categoria';
}

function renderProductsPanel(string $category, array $items, bool $active): void
{
    $slug = categorySlug($category);
    ?>
    <section
        id="panel-<?= e($slug) ?>"
        class="tab-panel <?= $active ? 'active' : '' ?>"
        data-panel="<?= e($slug) ?>"
        role="tabpanel"
        aria-labelledby="tab-<?= e($slug) ?>"
        <?= $active ? '' : 'hidden' ?>
    >
        <div class="menu-section">
            <div class="section-head">
                <div>
                    <h2><?= e($category) ?></h2>
                    <span><?= count($items) ?> producto<?= count($items) === 1 ? '' : 's' ?></span>
                </div>
            </div>

            <div class="products-list">
                <?php foreach ($items as $item): ?>
                    <article class="product-card">
                        <div class="product-info">
                            <div class="product-name"><?= e((string)($item['name'] ?? '')) ?></div>

                            <?php if (!empty($item['sub'])): ?>
                                <div class="product-sub"><?= e((string)$item['sub']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="product-price">
                            <?= money($item['price'] ?? 0) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

$hasSub = columnExists($pdo, 'products', 'sub');
$hasActive = columnExists($pdo, 'products', 'active');

$subSelect = $hasSub ? ', sub' : ", '' AS sub";
$activeWhere = $hasActive ? "AND COALESCE(active, 1) = 1" : "";

$stmt = $pdo->query("
    SELECT id, cat, name, price {$subSelect}
    FROM products
    WHERE LOWER(TRIM(cat)) NOT IN ('extras', 'extra', 'otros', 'otro')
      {$activeWhere}
    ORDER BY
      CASE LOWER(TRIM(cat))
        WHEN 'vasos' THEN 1
        WHEN 'vaso' THEN 1

        WHEN 'champagnes' THEN 2
        WHEN 'champagne' THEN 2
        WHEN 'champañas' THEN 2
        WHEN 'champaña' THEN 2

        WHEN 'combos' THEN 3
        WHEN 'combo' THEN 3

        WHEN 'vinos' THEN 4
        WHEN 'vino' THEN 4

        WHEN 'sin alcohol' THEN 5
        WHEN 'sinalcohol' THEN 5

        WHEN 'cervezas' THEN 6
        WHEN 'cerveza' THEN 6

        WHEN 'kiosko' THEN 7
        WHEN 'kiosco' THEN 7
        WHEN 'snacks' THEN 7
        WHEN 'snack' THEN 7

        WHEN 'shot' THEN 8
        WHEN 'shots' THEN 8

        ELSE 99
      END,
      price ASC,
      name ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];

foreach ($products as $product) {
    $category = normalizeCategory((string)($product['cat'] ?? ''));

    if (!isset($grouped[$category])) {
        $grouped[$category] = [];
    }

    $grouped[$category][] = $product;
}

$mainOrder = ['Vasos', 'Champagnes', 'Combos', 'Bebidas', 'Vinos', 'Sin alcohol','Kiosko', 'Shot'];

$categories = [];

foreach ($mainOrder as $category) {
    if (!empty($grouped[$category])) {
        $categories[] = $category;
    }
}

foreach (array_keys($grouped) as $category) {
    if (!in_array($category, $categories, true)) {
        $categories[] = $category;
    }
}

$updatedAt = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($appName) ?> · Menú</title>

<link rel="stylesheet" href="styles/menu.css?v=<?= time() ?>">
</head>

<body>

<div class="menu-wrap">
  <header class="menu-header">
    <h1 class="menu-title">Carta Virtual de <?= e($appName) ?></h1>
    <p class="menu-subtitle">Lista de precios (Pedir en la barra) </p>
  </header>

  <?php if (empty($categories)): ?>

    <div class="empty">No hay productos disponibles para mostrar.</div>

  <?php else: ?>

    <main class="tabs-card">

      <nav class="tabs-bar" role="tablist" aria-label="Categorías del menú">
        <?php foreach ($categories as $index => $category): ?>
          <?php
            $slug = categorySlug($category);
            $active = $index === 0;
          ?>
          <button
            id="tab-<?= e($slug) ?>"
            type="button"
            class="tab-button <?= $active ? 'active' : '' ?>"
            data-target="<?= e($slug) ?>"
            role="tab"
            aria-selected="<?= $active ? 'true' : 'false' ?>"
            aria-controls="panel-<?= e($slug) ?>"
            tabindex="<?= $active ? '0' : '-1' ?>"
          >
            <?= e($category) ?>
          </button>
        <?php endforeach; ?>
      </nav>

      <div class="tabs-content">
        <?php foreach ($categories as $index => $category): ?>
          <?php renderProductsPanel($category, $grouped[$category], $index === 0); ?>
        <?php endforeach; ?>
      </div>

    </main>

  <?php endif; ?>

  <div class="footer">
    Precios actualizados automáticamente desde el sistema · <?= e($updatedAt) ?>
  </div>

  <footer class="site-footer">
    <div>
      &copy; <?= date('Y') ?> <?= e($appName) ?>. Todos los derechos reservados.
    </div>

    <a href="https://instagram.com/Nickozero3">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" style="vertical-align:middle; margin-right:0.35rem;">
        <path d="M7 2C4.243 2 2 4.243 2 7v10c0 2.757 2.243 5 5 5h10c2.757 0 5-2.243 5-5V7c0-2.757-2.243-5-5-5H7zm10 2c1.654 0 3 1.346 3 3v10c0 1.654-1.346 3-3 3H7c-1.654 0-3-1.346-3-3V7c0-1.654 1.346-3 3-3h10zm-5 3a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6zm4.5-2.5a1 1 0 100 2 1 1 0 000-2z" fill="currentColor"/>
      </svg>
      Hecho con ❤️ por el bartender <?= e(defined('APP_AUTHOR') ? APP_AUTHOR : '@Nickozero3') ?>.
    </a>
    <button type="button" class="theme-toggle" id="themeToggle">
  Modo original
</button>
  </footer>

</div>

<script src="js/menu.js?v=<?= time() ?>"></script>

</body>
</html>
