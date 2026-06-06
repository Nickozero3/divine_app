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

    return (int)$stmt->fetchColumn() > 0;
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

$hasSub = columnExists($pdo, 'products', 'sub');
$subSelect = $hasSub ? ', sub' : ", '' AS sub";

$stmt = $pdo->query("
    SELECT id, cat, name, price {$subSelect}
    FROM products
    WHERE LOWER(TRIM(cat)) NOT IN ('extras', 'extra', 'otros', 'snacks')
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

$leftCategories = ['Vasos', 'Botellas'];
$rightCategories = ['Combos', 'Bebidas'];

$usedCategories = array_merge($leftCategories, $rightCategories);
$extraCategories = array_values(array_filter(array_keys($grouped), function ($cat) use ($usedCategories) {
    return !in_array($cat, $usedCategories, true);
}));

$updatedAt = date('d/m/Y H:i');

function renderCategory(string $category, array $grouped): void
{
    if (empty($grouped[$category])) {
        return;
    }

    $items = $grouped[$category];
    ?>
    <section class="menu-section">
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
                        <div class="product-name"><?= e((string)$item['name']) ?></div>

                        <?php if (!empty($item['sub'])): ?>
                            <div class="product-sub"><?= e((string)$item['sub']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="product-bottom">
                        <div class="product-price">
                            <?= money($item['price']) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($appName) ?> · Menú</title>

<style>
:root {
  --bg: #09060f;
  --bg2: #130b1c;
  --card: #1a1025;
  --card2: #100817;

  --gold: #f0d48d;
  --purple: #8f4cff;

  --text: #fff8ea;
  --text2: #c9bdd7;

  --border: rgba(240, 212, 141, .18);
  --price-bg: #f0d48d;
  --price-text: #130b1c;
}

* {
  box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

body {
  margin: 0;
  min-height: 100vh;
  font-family: Arial, sans-serif;
  background:
    radial-gradient(circle at top, rgba(143, 76, 255, .18), transparent 34%),
    radial-gradient(circle at 90% 15%, rgba(240, 212, 141, .10), transparent 25%),
    linear-gradient(180deg, #120a1a 0%, #07040b 100%);
  color: var(--text);
  padding: 16px 10px 24px;
}

.menu-wrap {
  width: min(1100px, 100%);
  margin: 0 auto;
}

.menu-header {
  text-align: center;
  margin: 2px 0 18px;
}

.menu-logo {
  width: 58px;
  height: 58px;
  margin: 0 auto 8px;
  border-radius: 18px;
  background: var(--gold);
  display: grid;
  place-items: center;
  font-size: 28px;
  font-weight: 900;
  color: #160d20;
  box-shadow: 0 16px 40px rgba(0,0,0,.36);
}

.menu-title {
  margin: 0;
  font-size: clamp(26px, 7vw, 44px);
  line-height: 1;
  color: var(--gold);
  letter-spacing: .4px;
}

.menu-subtitle {
  margin: 7px 0 0;
  color: var(--text2);
  font-size: 13px;
}

.menu-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  align-items: start;
}

.menu-column {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.menu-section {
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  background: rgba(19, 11, 28, .92);
  box-shadow: 0 12px 28px rgba(0,0,0,.22);
}

.section-head {
  background: #170d22;
  padding: 12px 10px;
  border-bottom: 1px solid rgba(240, 212, 141, .12);
}

.section-head h2 {
  margin: 0;
  font-size: 18px;
  color: var(--gold);
  line-height: 1.1;
}

.section-head span {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: var(--text2);
  font-weight: 700;
}

.products-list {
  padding: 7px;
  background: rgba(8, 5, 13, .28);
}

.product-card {
  background: #160d20;
  border: 1px solid rgba(240, 212, 141, .13);
  border-radius: 13px;
  padding: 9px;
  margin-bottom: 8px;
}

.product-card:last-child {
  margin-bottom: 0;
}

.product-info {
  min-width: 0;
}

.product-name {
  font-size: 13px;
  font-weight: 900;
  color: var(--text);
  line-height: 1.15;
  word-break: break-word;
}

.product-sub {
  margin-top: 4px;
  font-size: 11px;
  color: var(--text2);
  line-height: 1.2;
  word-break: break-word;
}

.product-bottom {
  margin-top: 8px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}

.product-price {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 950;
  color: var(--price-text);
  background: var(--price-bg);
  border: 2px solid rgba(255,255,255,.35);
  padding: 7px 10px;
  border-radius: 11px;
  min-width: 74px;
  text-align: center;
  box-shadow: 0 8px 18px rgba(0,0,0,.22);
  letter-spacing: .2px;
}

.extra-grid {
  margin-top: 8px;
}

.empty {
  background: rgba(255,255,255,.05);
  border: 1px dashed var(--border);
  color: var(--text2);
  border-radius: 16px;
  padding: 18px;
  text-align: center;
}

.footer {
  text-align: center;
  color: var(--text2);
  font-size: 11px;
  margin: 18px 0 6px;
  line-height: 1.4;
}

@media(min-width:701px) {
  body {
    padding: 18px 14px 28px;
  }

  .menu-grid {
    gap: 14px;
  }

  .menu-column {
    gap: 14px;
  }

  .menu-section {
    border-radius: 22px;
  }

  .section-head {
    padding: 15px 16px;
  }

  .section-head h2 {
    font-size: 23px;
  }

  .section-head span {
    font-size: 12px;
  }

  .products-list {
    padding: 10px;
  }

  .product-card {
    border-radius: 16px;
    padding: 12px;
    margin-bottom: 9px;
  }

  .product-name {
    font-size: 16px;
  }

  .product-sub {
    font-size: 12px;
  }

  .product-bottom {
    margin-top: 10px;
  }

  .product-price {
    font-size: 18px;
    min-width: 92px;
    padding: 8px 10px;
    border-radius: 13px;
  }

  .menu-logo {
    width: 62px;
    height: 62px;
    font-size: 30px;
  }

  .menu-header {
    margin-bottom: 24px;
  }

  .footer {
    font-size: 12px;
    margin: 24px 0 8px;
  }
}
</style>
</head>

<body>

<div class="menu-wrap">

  <header class="menu-header">
    <div class="menu-logo">★</div>
    <h1 class="menu-title"><?= e($appName) ?></h1>
    <p class="menu-subtitle">Menú de productos</p>
  </header>

  <?php if (empty($grouped)): ?>
    <div class="empty">No hay productos disponibles para mostrar.</div>
  <?php else: ?>

    <div class="menu-grid">
      <div class="menu-column">
        <?php foreach ($leftCategories as $category): ?>
          <?php renderCategory($category, $grouped); ?>
        <?php endforeach; ?>
      </div>

      <div class="menu-column">
        <?php foreach ($rightCategories as $category): ?>
          <?php renderCategory($category, $grouped); ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($extraCategories)): ?>
      <div class="menu-grid extra-grid">
        <?php foreach ($extraCategories as $category): ?>
          <div class="menu-column">
            <?php renderCategory($category, $grouped); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <div class="footer">
    Precios actualizados automáticamente desde el sistema · <?= e($updatedAt) ?>
  </div>

</div>

</body>
</html>