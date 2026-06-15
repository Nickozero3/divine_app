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
        'botella', 'botellas' => 'Botellas',
        'combo', 'combos' => 'Combos',
        'bebida', 'bebidas' => 'Bebidas',
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
    WHERE LOWER(TRIM(cat)) NOT IN ('extras', 'extra', 'otros', 'snacks')
      {$activeWhere}
    ORDER BY
      CASE LOWER(TRIM(cat))
        WHEN 'vasos' THEN 1
        WHEN 'vaso' THEN 1
        WHEN 'botellas' THEN 2
        WHEN 'botella' THEN 2
        WHEN 'combos' THEN 3
        WHEN 'combo' THEN 3
        WHEN 'bebidas' THEN 4
        WHEN 'bebida' THEN 4
        ELSE 99
      END,
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

$mainOrder = ['Vasos', 'Botellas', 'Combos', 'Bebidas'];

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
    <p class="menu-subtitle">Menú de productos</p>
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
      Hecho con ❤️ por <?= e(defined('APP_AUTHOR') ? APP_AUTHOR : '@Nickozero3') ?>.
    </a>
  </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabs = Array.from(document.querySelectorAll('.tab-button'));
  const panels = Array.from(document.querySelectorAll('.tab-panel'));

  function activateTab(tab, focus = false) {
    if (!tab) return;

    const target = tab.dataset.target;

    tabs.forEach(item => {
      const active = item === tab;
      item.classList.toggle('active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      item.tabIndex = active ? 0 : -1;
    });

    panels.forEach(panel => {
      const active = panel.dataset.panel === target;
      panel.classList.toggle('active', active);
      panel.hidden = !active;
    });

    if (focus) {
      tab.focus({ preventScroll: true });
    }
  }

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateTab(tab));

    tab.addEventListener('keydown', event => {
      let nextIndex = null;

      if (event.key === 'ArrowRight') {
        nextIndex = (index + 1) % tabs.length;
      }

      if (event.key === 'ArrowLeft') {
        nextIndex = (index - 1 + tabs.length) % tabs.length;
      }

      if (event.key === 'Home') {
        nextIndex = 0;
      }

      if (event.key === 'End') {
        nextIndex = tabs.length - 1;
      }

      if (nextIndex !== null) {
        event.preventDefault();
        activateTab(tabs[nextIndex], true);
      }
    });
  });
});
</script>

</body>
</html>
