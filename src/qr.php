<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_role(): string
{
    $role =
        $_SESSION['user']['role'] ??
        $_SESSION['role'] ??
        $_SESSION['currentUser']['role'] ??
        $_SESSION['auth']['role'] ??
        '';

    return strtolower(trim((string) $role));
}

function can_activate_qr(): bool
{
    return in_array(current_role(), ['admin', 'puerta'], true);
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    die('QR inválido');
}

$canActivate = can_activate_qr();
$activated = false;
$error = '';

try {
    if ($canActivate) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT 
                dp.*,
                dl.name AS list_name
            FROM door_people dp
            INNER JOIN door_lists dl ON dl.id = dp.list_id
            WHERE dp.qr_token = :token
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':token' => $token
        ]);

        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            $pdo->rollBack();
            die('QR no encontrado');
        }

        if ((int) $person['qr_enabled'] !== 1) {
            $pdo->rollBack();
            die('QR desactivado');
        }

        if (!empty($person['qr_used_at'])) {
            $pdo->rollBack();
            die('QR ya utilizado');
        }

        $stmt = $pdo->prepare("
            UPDATE door_people
            SET status = 'entro',
                qr_used_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => (int) $person['id']
        ]);

        $pdo->commit();

        $person['status'] = 'entro';
        $person['qr_used_at'] = date('Y-m-d H:i:s');
        $activated = true;
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                dp.*,
                dl.name AS list_name
            FROM door_people dp
            INNER JOIN door_lists dl ON dl.id = dp.list_id
            WHERE dp.qr_token = :token
            LIMIT 1
        ");

        $stmt->execute([
            ':token' => $token
        ]);

        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            die('QR no encontrado');
        }

        if ((int) $person['qr_enabled'] !== 1) {
            die('QR desactivado');
        }
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
}

if ($error !== '') {
    die('Error: ' . h($error));
}

$isUsed = !empty($person['qr_used_at']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR de entrada</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #0c0a12;
    color: #f7f1df;
    display: grid;
    place-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 18px;
}

.box {
    background: #15111d;
    border: 1px solid #33284a;
    border-radius: 22px;
    padding: 24px;
    width: min(420px, 90vw);
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.28);
}

.icon {
    font-size: 46px;
    margin-bottom: 8px;
}

.ok {
    color: #36c985;
}

.warn {
    color: #f0d48d;
}

.bad {
    color: #ff6b6b;
}

h1 {
    color: #f0d48d;
    margin: 8px 0 14px;
}

p {
    margin: 8px 0;
    color: #ded6ea;
}

.name {
    font-size: 22px;
    color: #fff;
    font-weight: 800;
}

.status {
    margin-top: 18px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(255,255,255,.06);
    color: #f7f1df;
    font-weight: 700;
}

.small {
    margin-top: 14px;
    font-size: 13px;
    color: #aaa0b8;
    line-height: 1.4;
}
</style>
</head>

<body>
<div class="box">

    <?php if ($activated): ?>
        <div class="icon ok">✓</div>
        <h1>Entrada confirmada</h1>

        <p class="name"><?= h($person['name']) ?></p>
        <p>Lista: <?= h($person['list_name']) ?></p>

        <div class="status">
            QR activado por <?= h(current_role()) ?>
        </div>

    <?php elseif ($isUsed): ?>
        <div class="icon bad">×</div>
        <h1>QR ya utilizado</h1>

        <p class="name"><?= h($person['name']) ?></p>
        <p>Lista: <?= h($person['list_name']) ?></p>

        <div class="status">
            Este QR ya fue usado.
        </div>

    <?php else: ?>
        <div class="icon warn">!</div>
        <h1>QR válido</h1>

        <p class="name"><?= h($person['name']) ?></p>
        <p>Lista: <?= h($person['list_name']) ?></p>

        <div class="status">
            Mostrá este QR en puerta.
        </div>

        <div class="small">
            Este QR todavía no fue usado. Solo puede activarlo una cuenta con rol puerta o admin.
        </div>
    <?php endif; ?>

</div>
</body>
</html>