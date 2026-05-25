<?php
require_once __DIR__ . '/config/conexion.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    die('QR inválido');
}

$stmt = $pdo->prepare("
    SELECT 
        dp.*,
        dl.name AS list_name
    FROM door_people dp
    INNER JOIN door_lists dl ON dl.id = dp.list_id
    WHERE dp.qr_token = :token
    LIMIT 1
");

$stmt->execute([':token' => $token]);
$person = $stmt->fetch();

if (!$person) {
    die('QR no encontrado');
}

if ((int)$person['qr_enabled'] !== 1) {
    die('QR desactivado');
}

if ($person['qr_used_at']) {
    die('QR ya utilizado');
}

$stmt = $pdo->prepare("
    UPDATE door_people
    SET status = 'entro',
        qr_used_at = NOW()
    WHERE id = :id
");

$stmt->execute([':id' => $person['id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR válido</title>
<style>
body{font-family:Arial;background:#0c0a12;color:#f7f1df;display:grid;place-items:center;min-height:100vh;margin:0}
.box{background:#15111d;border:1px solid #33284a;border-radius:22px;padding:24px;width:min(420px,90vw);text-align:center}
.ok{font-size:46px;color:#36c985}
h1{color:#f0d48d}
</style>
</head>
<body>
<div class="box">
  <div class="ok">✓</div>
  <h1>Entrada confirmada</h1>
  <p><b><?= htmlspecialchars($person['name']) ?></b></p>
  <p>Lista: <?= htmlspecialchars($person['list_name']) ?></p>
</div>
</body>
</html>