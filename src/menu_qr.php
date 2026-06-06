<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/const.php')) {
    require_once __DIR__ . '/const.php';
}

$appName = defined('APP_NAME') ? APP_NAME : 'Menú';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme = $https ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

if ($dir === '/') {
    $dir = '';
}

$menuUrl = $scheme . '://' . $host . $dir . '/menu.php';

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&data=' . urlencode($menuUrl);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR del menú · <?= e($appName) ?></title>

<style>
:root {
  --bg: #09060f;
  --card: #160d20;
  --gold: #f0d48d;
  --purple: #8f4cff;
  --text: #fff8ea;
  --text2: #c9bdd7;
  --border: rgba(240, 212, 141, .18);
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at top, rgba(143, 76, 255, .22), transparent 35%),
    radial-gradient(circle at 90% 15%, rgba(240, 212, 141, .12), transparent 28%),
    linear-gradient(180deg, #120a1a 0%, #07040b 100%);
  color: var(--text);
  font-family: Arial, sans-serif;
  padding: 20px;
}

.qr-card {
  width: min(460px, 100%);
  background: rgba(22, 13, 32, .96);
  border: 1px solid var(--border);
  border-radius: 28px;
  padding: 24px;
  text-align: center;
  box-shadow: 0 22px 70px rgba(0,0,0,.38);
}

.logo {
  width: 68px;
  height: 68px;
  margin: 0 auto 12px;
  border-radius: 20px;
  display: grid;
  place-items: center;
  background: var(--gold);
  color: #160d20;
  font-size: 34px;
  font-weight: 900;
}

h1 {
  margin: 0;
  color: var(--gold);
  font-size: 32px;
  line-height: 1.05;
}

.subtitle {
  margin: 8px 0 20px;
  color: var(--text2);
  font-size: 15px;
}

.qr-box {
  background: white;
  border-radius: 24px;
  padding: 16px;
  margin: 0 auto 16px;
  width: min(330px, 100%);
}

.qr-box img {
  display: block;
  width: 100%;
  height: auto;
}

.url {
  color: var(--text2);
  font-size: 12px;
  line-height: 1.4;
  word-break: break-all;
  background: rgba(255,255,255,.05);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 10px;
  margin-bottom: 16px;
}

.actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  border: 0;
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
  background: linear-gradient(135deg, var(--gold), var(--purple));
  color: white;
  text-decoration: none;
}

.btn.secondary {
  background: rgba(255,255,255,.08);
  border: 1px solid var(--border);
  color: var(--text);
}

.note {
  margin-top: 14px;
  color: var(--text2);
  font-size: 12px;
  line-height: 1.35;
}

@media print {
  body {
    background: white;
    color: black;
    padding: 0;
  }

  .qr-card {
    box-shadow: none;
    border: 0;
    background: white;
    color: black;
  }

  .logo {
    background: black;
    color: white;
  }

  h1 {
    color: black;
  }

  .subtitle,
  .url,
  .note {
    color: #333;
  }

  .url {
    border-color: #ddd;
    background: white;
  }

  .actions {
    display: none;
  }
}
</style>
</head>

<body>

<div class="qr-card">
  <div class="logo">★</div>

  <h1><?= e($appName) ?></h1>
  <div class="subtitle">Escaneá para ver el menú</div>

  <div class="qr-box">
    <img src="<?= e($qrUrl) ?>" alt="QR del menú">
  </div>

  <div class="url">
    <?= e($menuUrl) ?>
  </div>

  <div class="actions">
    <a class="btn" href="<?= e($menuUrl) ?>" target="_blank">Ver menú</a>
    <button class="btn secondary" onclick="window.print()">Imprimir QR</button>
  </div>

  <div class="note">
    Este QR queda fijo. Cuando cambies precios o productos en la BD, el menú se actualiza sin cambiar el QR.
  </div>
</div>

</body>
</html>