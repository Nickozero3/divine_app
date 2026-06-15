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

function categorySlug(string $category): string
{
    $slug = mb_strtolower(trim($category), 'UTF-8');

    $slug = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'n'],
        $slug
    );

    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
}

function renderCategory(string $category, array $grouped): void
{
    if (empty($grouped[$category])) {
        return;
    }

    $items = $grouped[$category];
    $slug = categorySlug($category);
    ?>
    <section 
        class="menu-section" 
        id="cat-<?= e($slug) ?>" 
        data-category="<?= e($slug) ?>"
    >
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

                    <div class="product-price">
                        <?= money($item['price']) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
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

$floatingCategories = ['Vasos','Botellas','Combos', 'Bebidas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($appName) ?> · Menú</title>

<link rel="stylesheet" href="styles/menu.css">
</head>

<body>

<div class="menu-wrap">

  <header class="menu-header">
    <h1 class="menu-title">Carta Virtual de <?= e($appName) ?></h1>
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

    <nav class="floating-category-nav" aria-label="Navegación rápida del menú">
      <?php foreach ($floatingCategories as $category): ?>
        <?php if (!empty($grouped[$category])): ?>
          <?php $slug = categorySlug($category); ?>

          <a
            href="#cat-<?= e($slug) ?>"
            class="floating-category-btn"
            data-target="<?= e($slug) ?>"
            aria-label="Ir a <?= e($category) ?>"
          >
            <?= e($category) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

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
  const sections = document.querySelectorAll('.menu-section[data-category]');
  const buttons = document.querySelectorAll('.floating-category-btn');

  let manualScrolling = false;

  function setActive(category) {
    buttons.forEach((button) => {
      const isActive = button.dataset.target === category;

      button.classList.toggle('active', isActive);

      if (isActive) {
        button.setAttribute('aria-current', 'true');
      } else {
        button.removeAttribute('aria-current');
      }
    });
  }

  function highlightSection(section) {
    section.classList.remove('section-highlight');

    // Reinicia la animación aunque toques el mismo botón varias veces
    void section.offsetWidth;

    section.classList.add('section-highlight');

    setTimeout(() => {
      section.classList.remove('section-highlight');
    }, 1800);
  }

  function smoothScrollTo(targetY, duration = 950, callback = null) {
    const startY = window.scrollY;
    const distance = targetY - startY;
    const startTime = performance.now();

    manualScrolling = true;

    function easeInOutCubic(t) {
      return t < 0.5
        ? 4 * t * t * t
        : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }

    function animation(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = easeInOutCubic(progress);

      window.scrollTo(0, startY + distance * eased);

      if (progress < 1) {
        requestAnimationFrame(animation);
      } else {
        manualScrolling = false;

        if (typeof callback === 'function') {
          callback();
        }
      }
    }

    requestAnimationFrame(animation);
  }

  const observer = new IntersectionObserver((entries) => {
    if (manualScrolling) return;

    const visible = entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

    if (visible.length > 0) {
      setActive(visible[0].target.dataset.category);
    }
  }, {
    root: null,
    threshold: [0.20, 0.35, 0.50, 0.70],
    rootMargin: '-20% 0px -45% 0px'
  });

  sections.forEach((section) => observer.observe(section));

  buttons.forEach((button) => {
    button.addEventListener('click', (e) => {
      e.preventDefault();

      const targetSelector = button.getAttribute('href');
      const target = document.querySelector(targetSelector);

      if (!target) return;

      setActive(button.dataset.target);

      const extraOffset = 16;
      const targetY = target.getBoundingClientRect().top + window.scrollY - extraOffset;

      smoothScrollTo(targetY, 950, () => {
        highlightSection(target);
      });
    });
  });
});
</script>

</body>
</html>