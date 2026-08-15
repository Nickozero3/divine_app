<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/assets.php';

if (file_exists(__DIR__ . '/const.php')) {
    require_once __DIR__ . '/const.php';
}

$appName = defined('APP_NAME') ? APP_NAME : 'Divine App';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];

if (($currentUser['role'] ?? '') !== 'admin') {
    die('Acceso denegado');
}

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
        'combo', 'combos' => 'Combos',
        'sin alcohol', 'sinalcohol' => 'Sin alcohol',
        'cerveza', 'cervezas' => 'Cervezas',
        'vino', 'vinos' => 'Vinos',
        'champagne', 'champagnes', 'champaña', 'champañas' => 'Champagnes',
        'shot', 'shots' => 'Shot',
        'snack', 'snacks' => 'Snacks',
        'extra', 'extras' => 'Extras',
        'otro', 'otros' => 'Otros',
        default => $cat !== '' ? $cat : 'Otros',
    };
}

function redirectWithMessage(string $type, string $message): void
{
    $_SESSION['flash_products'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: productos_admin.php');
    exit;
}

$hasSub = columnExists($pdo, 'products', 'sub');
$hasQty = columnExists($pdo, 'products', 'qty');
$hasActive = columnExists($pdo, 'products', 'active');
$hasCustom = columnExists($pdo, 'products', 'custom');

$allowedCategories = [
    'Vasos',
    'Combos',
    'Sin alcohol',
    'Cervezas',
    'Vinos',
    'Champagnes',
    'Shot',
    'Snacks',
    'Extras',
    'Otros',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_product') {
            $cat = normalizeCategory((string)($_POST['cat'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $sub = trim((string)($_POST['sub'] ?? ''));
            $price = (int)($_POST['price'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;

            if ($name === '') {
                redirectWithMessage('error', 'El nombre del producto es obligatorio.');
            }

            if ($price < 0) {
                redirectWithMessage('error', 'El precio no puede ser negativo.');
            }

            if (!in_array($cat, $allowedCategories, true)) {
                $cat = 'Otros';
            }

            $columns = ['cat', 'name', 'price'];
            $params = [
                ':cat' => $cat,
                ':name' => $name,
                ':price' => $price,
            ];

            if ($hasSub) {
                $columns[] = 'sub';
                $params[':sub'] = $sub;
            }

            if ($hasQty) {
                $columns[] = 'qty';
                $params[':qty'] = 0;
            }

            if ($hasCustom) {
                $columns[] = 'custom';
                $params[':custom'] = 1;
            }

            if ($hasActive) {
                $columns[] = 'active';
                $params[':active'] = $active;
            }

            $placeholders = array_map(fn($column) => ':' . $column, $columns);

            $stmt = $pdo->prepare("
                INSERT INTO products (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
            ");

            $stmt->execute($params);

            redirectWithMessage('success', 'Producto añadido correctamente.');
        }

        if ($action === 'update_product') {
            $id = (int)($_POST['id'] ?? 0);
            $cat = normalizeCategory((string)($_POST['cat'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $sub = trim((string)($_POST['sub'] ?? ''));
            $price = (int)($_POST['price'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;

            if ($id <= 0) {
                redirectWithMessage('error', 'Producto inválido.');
            }

            if ($name === '') {
                redirectWithMessage('error', 'El nombre no puede estar vacío.');
            }

            if ($price < 0) {
                redirectWithMessage('error', 'El precio no puede ser negativo.');
            }

            if (!in_array($cat, $allowedCategories, true)) {
                $cat = 'Otros';
            }

            $sets = [
                'cat = :cat',
                'name = :name',
                'price = :price',
            ];

            $params = [
                ':id' => $id,
                ':cat' => $cat,
                ':name' => $name,
                ':price' => $price,
            ];

            if ($hasSub) {
                $sets[] = 'sub = :sub';
                $params[':sub'] = $sub;
            }

            if ($hasActive) {
                $sets[] = 'active = :active';
                $params[':active'] = $active;
            }

            $stmt = $pdo->prepare("
                UPDATE products
                SET " . implode(', ', $sets) . "
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute($params);

            redirectWithMessage('success', 'Producto actualizado correctamente.');
        }

        if ($action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                redirectWithMessage('error', 'Producto inválido.');
            }

            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);

            redirectWithMessage('success', 'Producto eliminado correctamente.');
        }

        redirectWithMessage('error', 'Acción no válida.');

    } catch (Throwable $e) {
        redirectWithMessage('error', 'Error: ' . $e->getMessage());
    }
}

$subSelect = $hasSub ? ', sub' : ", '' AS sub";
$activeSelect = $hasActive ? ', active' : ", 1 AS active";
$qtySelect = $hasQty ? ', qty' : ", 0 AS qty";

$stmt = $pdo->query("
    SELECT id, cat, name, price {$subSelect} {$activeSelect} {$qtySelect}
    FROM products
    ORDER BY
      CASE LOWER(TRIM(cat))
        WHEN 'vasos' THEN 1
        WHEN 'vaso' THEN 1
        WHEN 'combos' THEN 2
        WHEN 'combo' THEN 2
        WHEN 'sin alcohol' THEN 3
        WHEN 'cervezas' THEN 4
        WHEN 'cerveza' THEN 4
        WHEN 'vinos' THEN 5
        WHEN 'vino' THEN 5
        WHEN 'champagnes' THEN 6
        WHEN 'champagne' THEN 6
        WHEN 'shot' THEN 7
        WHEN 'shots' THEN 7
        WHEN 'snacks' THEN 8
        WHEN 'snack' THEN 8
        WHEN 'extras' THEN 9
        WHEN 'extra' THEN 9
        WHEN 'otros' THEN 10
        WHEN 'otro' THEN 10
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

$flash = $_SESSION['flash_products'] ?? null;
unset($_SESSION['flash_products']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Productos · <?= APP_NAME ?></title>
<link rel="stylesheet" href="styles.css">

<style>
:root {
  --bg: #09060f;
  --bg2: #130b1c;
  --card: #1a1025;
  --gold: #f0d48d;
  --purple: #8f4cff;
  --text: #fff8ea;
  --text2: #c9bdd7;
  --border: rgba(240, 212, 141, .18);
  --green: #4ade80;
  --red: #fb7185;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  background:
    radial-gradient(circle at top, rgba(143, 76, 255, .18), transparent 34%),
    linear-gradient(180deg, #120a1a 0%, #07040b 100%);
  color: var(--text);
  font-family: Arial, sans-serif;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  background: color-mix(in srgb, var(--bg) 78%, transparent);
  backdrop-filter: blur(18px) saturate(135%);
  -webkit-backdrop-filter: blur(18px) saturate(135%);
  border-bottom: 1px solid rgba(255, 255, 255, .07);
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.topbar-title {
  font-family: "Cinzel", serif;
  color: var(--text);
  font-weight: 700;
  font-size: 18px;
  letter-spacing: .04em;
  background: linear-gradient(110deg, var(--gold-2), var(--purple-2));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.topbar-back {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  border: 1px solid rgba(255, 255, 255, .08);
  background: rgba(255, 255, 255, .035);
  color: var(--text2);
  border-radius: 999px;
  padding: 0 16px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
}

.topbar-back:active {
  transform: scale(.96);
}

.wrap {
  width: min(1100px, 100%);
  margin: 0 auto;
  padding: 16px;
}

.page-title {
  color: var(--gold);
  font-size: 28px;
  font-weight: 900;
  margin: 0 0 12px;
}

.card {
  background: rgba(19, 11, 28, .94);
  border: 1px solid var(--border);
  border-radius: 22px;
  padding: 14px;
  margin-bottom: 14px;
  box-shadow: 0 14px 34px rgba(0,0,0,.24);
}

.card-title {
  color: var(--gold);
  font-size: 20px;
  font-weight: 900;
  margin-bottom: 12px;
}

.flash {
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 800;
  margin-bottom: 14px;
}

.flash.success {
  background: rgba(74, 222, 128, .14);
  border: 1px solid rgba(74, 222, 128, .35);
  color: var(--green);
}

.flash.error {
  background: rgba(251, 113, 133, .14);
  border: 1px solid rgba(251, 113, 133, .35);
  color: var(--red);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1.4fr 1.4fr 120px auto;
  gap: 10px;
  align-items: end;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: var(--text2);
  font-size: 12px;
  font-weight: 800;
}

.field input,
.field select {
  width: 100%;
  border: 1px solid var(--border);
  background: #120a1a;
  color: var(--text);
  border-radius: 13px;
  padding: 11px;
  outline: none;
  font-weight: 700;
}

.field input:focus,
.field select:focus {
  border-color: var(--gold);
}

.btn {
  border: 0;
  border-radius: 13px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
  background: linear-gradient(135deg, var(--gold), var(--purple));
  color: white;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
}

.btn.secondary {
  background: rgba(255,255,255,.08);
  border: 1px solid var(--border);
  color: var(--text);
}

.btn.danger {
  background: rgba(251, 113, 133, .16);
  border: 1px solid rgba(251, 113, 133, .35);
  color: #fecdd3;
}

.check-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text2);
  font-size: 13px;
  font-weight: 800;
  padding-bottom: 10px;
}

.check-inline input {
  width: 18px;
  height: 18px;
}

.search-box {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  margin-bottom: 14px;
}

.search-input {
  width: 100%;
  border: 1px solid var(--border);
  background: #120a1a;
  color: var(--text);
  border-radius: 15px;
  padding: 13px 14px;
  outline: none;
  font-weight: 800;
}

.search-input:focus {
  border-color: var(--gold);
}

.search-info {
  color: var(--text2);
  font-size: 12px;
  margin-top: -6px;
  margin-bottom: 12px;
}

.product-section {
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 12px;
  background: rgba(255,255,255,.035);
}

.product-section.is-collapsed .section-body {
  display: none;
}

.section-toggle {
  width: 100%;
  border: 0;
  background: #170d22;
  color: var(--text);
  padding: 14px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  text-align: left;
}

.section-main {
  min-width: 0;
}

.section-title {
  color: var(--gold);
  font-weight: 900;
  font-size: 20px;
  line-height: 1.1;
}

.section-sub {
  margin-top: 4px;
  color: var(--text2);
  font-size: 12px;
  font-weight: 700;
}

.section-pill {
  flex-shrink: 0;
  border-radius: 999px;
  background: rgba(240, 212, 141, .12);
  border: 1px solid rgba(240, 212, 141, .25);
  color: var(--gold);
  padding: 8px 11px;
  font-size: 12px;
  font-weight: 900;
  min-width: 80px;
  text-align: center;
}

.section-body {
  padding: 10px;
}

.product-row {
  background: #120a1a;
  border: 1px solid rgba(240, 212, 141, .13);
  border-radius: 18px;
  padding: 12px;
  margin-bottom: 10px;
}

.product-row:last-child {
  margin-bottom: 0;
}

.product-row.is-hidden,
.product-section.is-hidden {
  display: none;
}

.product-current {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  color: var(--text2);
  font-size: 12px;
  margin-bottom: 10px;
}

.product-current-name {
  color: var(--text);
  font-weight: 900;
  font-size: 14px;
}

.product-current-sub {
  margin-top: 3px;
  color: var(--text2);
  line-height: 1.3;
}

.product-current-price {
  color: #160d20;
  background: var(--gold);
  border-radius: 999px;
  padding: 7px 10px;
  font-weight: 950;
  white-space: nowrap;
}

.product-edit-grid {
  display: grid;
  grid-template-columns: 1fr 1.3fr 1.4fr 120px auto auto;
  gap: 9px;
  align-items: end;
}

.empty {
  color: var(--text2);
  border: 1px dashed var(--border);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
}

.no-results {
  display: none;
  color: var(--text2);
  border: 1px dashed var(--border);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
  margin-top: 12px;
}

.no-results.is-visible {
  display: block;
}

@media(max-width:900px) {
  .form-grid,
  .product-edit-grid {
    grid-template-columns: 1fr 1fr;
  }

  .btn {
    width: 100%;
  }
}

@media(max-width:560px) {
  .wrap {
    padding: 12px;
  }

  .page-title {
    font-size: 24px;
  }

  .form-grid,
  .product-edit-grid,
  .search-box {
    grid-template-columns: 1fr;
  }

  .product-current {
    flex-direction: column;
  }

  .product-current-price {
    align-self: flex-start;
  }

  .section-toggle {
    padding: 13px;
  }

  .section-title {
    font-size: 18px;
  }

  .topbar-title {
    font-size: 16px;
  }
}
</style>

<link rel="stylesheet" href="styles/theme.css?v=<?= asset_version('styles/theme.css') ?>">
<script src="js/theme.js?v=<?= asset_version('js/theme.js') ?>" defer></script>
</head>

<body>

<div class="topbar">
  <div class="topbar-title" onclick="location.href='admin.php'">Productos / Precios</div>
  <button class="topbar-back" onclick="location.href='admin.php'">← Volver</button>
</div>

<div class="wrap">

  <h1 class="page-title">Productos de la base de datos</h1>

  <?php if ($flash): ?>
    <div class="flash <?= e((string)$flash['type']) ?>">
      <?= e((string)$flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">Añadir producto</div>

    <form method="POST" class="form-grid">
      <input type="hidden" name="action" value="add_product">

      <div class="field">
        <label>Categoría</label>
        <select name="cat" required>
          <?php foreach ($allowedCategories as $category): ?>
            <option value="<?= e($category) ?>"><?= e($category) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Nombre</label>
        <input type="text" name="name" placeholder="Ej: Gin Gordon" required>
      </div>

      <div class="field">
        <label>Descripción</label>
        <input type="text" name="sub" placeholder="Ej: Gin + tónica">
      </div>

      <div class="field">
        <label>Precio</label>
        <input type="number" name="price" min="0" step="1" placeholder="14500" required>
      </div>

      <?php if ($hasActive): ?>
        <label class="check-inline">
          <input type="checkbox" name="active" value="1" checked>
          Activo
        </label>
      <?php endif; ?>

      <button class="btn" type="submit">Añadir</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Modificar productos</div>

    <div class="search-box">
      <input
        type="search"
        id="product-search"
        class="search-input"
        placeholder="Buscar por nombre, categoría, descripción o precio..."
        autocomplete="off"
      >

      <button class="btn secondary" type="button" onclick="clearProductSearch()">Limpiar</button>
    </div>

    <div class="search-info" id="search-info">
      Mostrando todos los productos.
    </div>

    <?php if (empty($grouped)): ?>
      <div class="empty">No hay productos cargados.</div>
    <?php endif; ?>

    <?php foreach ($grouped as $category => $items): ?>
     <section class="product-section is-collapsed" data-category="<?= e(mb_strtolower($category, 'UTF-8')) ?>">  <button class="section-toggle" type="button" onclick="toggleProductSection(this)">
          <span class="section-main">
            <span class="section-title"><?= e($category) ?></span>
            <span class="section-sub">
              <span class="section-visible-count"><?= count($items) ?></span> de <?= count($items) ?> producto<?= count($items) === 1 ? '' : 's' ?>
            </span>
          </span>

          <span class="section-pill">Ver</span>
        </button>

        <div class="section-body">
          <?php foreach ($items as $item): ?>
            <?php
              $searchText = mb_strtolower(
                  normalizeCategory((string)$item['cat']) . ' ' .
                  (string)$item['name'] . ' ' .
                  (string)$item['sub'] . ' ' .
                  (string)$item['price'] . ' ' .
                  money($item['price']),
                  'UTF-8'
              );
            ?>

            <div
              class="product-row"
              data-search="<?= e($searchText) ?>"
              data-price="<?= e((string)$item['price']) ?>"
            >
              <div class="product-current">
                <div>
                  <div class="product-current-name"><?= e((string)$item['name']) ?></div>

                  <?php if (!empty($item['sub'])): ?>
                    <div class="product-current-sub"><?= e((string)$item['sub']) ?></div>
                  <?php endif; ?>

                  <div class="product-current-sub">
                    Categoría: <?= e(normalizeCategory((string)$item['cat'])) ?>
                    <?php if ($hasQty): ?>
                      · Stock/cant: <?= e((string)$item['qty']) ?>
                    <?php endif; ?>
                    <?php if ($hasActive): ?>
                      · <?= ((int)$item['active'] === 1) ? 'Activo' : 'Inactivo' ?>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="product-current-price"><?= money($item['price']) ?></div>
              </div>

              <form method="POST" class="product-edit-grid">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                <div class="field">
                  <label>Categoría</label>
                  <select name="cat" required>
                    <?php foreach ($allowedCategories as $catOption): ?>
                      <option value="<?= e($catOption) ?>" <?= normalizeCategory((string)$item['cat']) === $catOption ? 'selected' : '' ?>>
                        <?= e($catOption) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="field">
                  <label>Nombre</label>
                  <input type="text" name="name" value="<?= e((string)$item['name']) ?>" required>
                </div>

                <div class="field">
                  <label>Descripción</label>
                  <input type="text" name="sub" value="<?= e((string)$item['sub']) ?>">
                </div>

                <div class="field">
                  <label>Precio</label>
                  <input type="number" name="price" min="0" step="1" value="<?= e((string)(int)$item['price']) ?>" required>
                </div>

                <?php if ($hasActive): ?>
                  <label class="check-inline">
                    <input type="checkbox" name="active" value="1" <?= ((int)$item['active'] === 1) ? 'checked' : '' ?>>
                    Activo
                  </label>
                <?php endif; ?>

                <button class="btn" type="submit">Guardar</button>

                <button
                  class="btn danger"
                  type="submit"
                  name="action"
                  value="delete_product"
                  onclick="return confirm('¿Eliminar este producto?')"
                >
                  Eliminar
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="no-results" id="no-results">
      No encontré productos con esa búsqueda.
    </div>
  </div>

</div>

<script>
function toggleProductSection(button) {
  const section = button.closest('.product-section');
  if (!section) return;

  section.classList.toggle('is-collapsed');

  const pill = section.querySelector('.section-pill');
  const isOpen = !section.classList.contains('is-collapsed');

  if (pill) {
    pill.textContent = isOpen ? 'Ocultar' : 'Ver';
  }
}

function normalizeText(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
}

function applyProductSearch() {
  const input = document.getElementById('product-search');
  const info = document.getElementById('search-info');
  const noResults = document.getElementById('no-results');

  const q = normalizeText(input ? input.value : '');
  let totalVisible = 0;

  document.querySelectorAll('.product-section').forEach(section => {
    let sectionVisible = 0;

    section.querySelectorAll('.product-row').forEach(row => {
      const haystack = normalizeText(row.dataset.search || '');
      const matches = q === '' || haystack.includes(q);

      row.classList.toggle('is-hidden', !matches);

      if (matches) {
        sectionVisible++;
        totalVisible++;
      }
    });

    section.classList.toggle('is-hidden', sectionVisible === 0);

    const count = section.querySelector('.section-visible-count');
    if (count) {
      count.textContent = sectionVisible;
    }

    if (q !== '' && sectionVisible > 0) {
      section.classList.remove('is-collapsed');

      const pill = section.querySelector('.section-pill');
      if (pill) pill.textContent = 'Ocultar';
    }
  });

  if (info) {
    info.textContent = q === ''
      ? 'Mostrando todos los productos.'
      : `Resultado: ${totalVisible} producto${totalVisible === 1 ? '' : 's'} encontrado${totalVisible === 1 ? '' : 's'}.`;
  }

  if (noResults) {
    noResults.classList.toggle('is-visible', q !== '' && totalVisible === 0);
  }
}

function clearProductSearch() {
  const input = document.getElementById('product-search');
  if (!input) return;

  input.value = '';
  applyProductSearch();
  input.focus();
}

document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('product-search');

  if (input) {
    input.addEventListener('input', applyProductSearch);
  }
});
</script>


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
