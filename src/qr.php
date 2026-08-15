<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/assets.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    die('QR inválido');
}

$error = '';
$person = null;

try {
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
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($error !== '') {
    die('Error: ' . h($error));
}

$isUsed = !empty($person['qr_used_at']);
$canActivate = in_array(
    strtolower(trim((string) (
        $_SESSION['user']['role'] ??
        $_SESSION['role'] ??
        $_SESSION['currentUser']['role'] ??
        $_SESSION['auth']['role'] ??
        ''
    ))),
    ['admin', 'puerta'],
    true
);
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, .28);
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
            background: rgba(255, 255, 255, .06);
            color: #f7f1df;
            font-weight: 700;
        }

        .small {
            margin-top: 14px;
            font-size: 13px;
            color: #aaa0b8;
            line-height: 1.4;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-ok {
            background: #36c985;
            color: #08130f;
        }

        .btn-bad {
            background: #ff6b6b;
            color: white;
        }

        .btn-dark {
            background: #2a2336;
            color: #f7f1df;
        }
    </style>

    <link rel="stylesheet" href="styles/theme.css?v=<?= asset_version('styles/theme.css') ?>">
    <script src="js/theme.js?v=<?= asset_version('js/theme.js') ?>" defer></script>
</head>

<body class="centered-theme-page">
    <div class="box">

        <?php if ($isUsed): ?>
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

            <div class="status" id="statusBox">
                <?php if ($canActivate): ?>
                    Datos cargados. Confirmá manualmente o esperá 10 segundos.
                <?php else: ?>
                    Solo lectura: este QR todavía no fue confirmado.
                <?php endif; ?>
            </div>

            <div class="actions">
                <?php if ($canActivate): ?>
                    <button class="btn btn-ok" id="confirmBtn" type="button">Aceptar</button>
                    <button class="btn btn-bad" id="rejectBtn" type="button">Rechazar</button>
                <?php endif; ?>
            </div>

            <div class="small" id="countdownText">
                <?php if ($canActivate): ?>
                    Se aceptará automáticamente en 10 segundos si no hacés nada.
                <?php else: ?>
                    Mostrá este QR en puerta.
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

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

    <?php if (!$isUsed && $canActivate): ?>
        <script>
            const token = <?= json_encode($token) ?>;
            let seconds = 10;
            let done = false;

            const statusBox = document.getElementById('statusBox');
            const countdownText = document.getElementById('countdownText');
            const confirmBtn = document.getElementById('confirmBtn');
            const rejectBtn = document.getElementById('rejectBtn');

            async function postAction(action) {
                const res = await fetch('api.php?action=' + encodeURIComponent(action), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        token
                    })
                });

                const json = await res.json();
                if (!res.ok || !json.ok) {
                    throw new Error(json.error || 'Error del servidor');
                }

                return json;
            }

            async function confirm(auto = false) {
                if (done) return;
                done = true;

                try {
                    await postAction('qr_confirm');
                    statusBox.textContent = auto ?
                        'Entrada confirmada automáticamente.' :
                        'Entrada confirmada manualmente.';
                    countdownText.textContent = 'Marcado como ENTRO.';
                    if (confirmBtn) confirmBtn.disabled = true;
                    if (rejectBtn) rejectBtn.disabled = true;
                } catch (e) {
                    done = false;
                    alert(e.message);
                }
            }

            async function reject() {
                if (done) return;
                done = true;

                try {
                    await postAction('qr_reject');
                    statusBox.textContent = 'Entrada rechazada.';
                    countdownText.textContent = 'Marcado como RECHAZADO.';
                    if (confirmBtn) confirmBtn.disabled = true;
                    if (rejectBtn) rejectBtn.disabled = true;
                } catch (e) {
                    done = false;
                    alert(e.message);
                }
            }

            if (confirmBtn) confirmBtn.addEventListener('click', () => confirm(false));
            if (rejectBtn) rejectBtn.addEventListener('click', reject);

            const timer = setInterval(() => {
                if (done) {
                    clearInterval(timer);
                    return;
                }

                seconds--;
                countdownText.textContent = 'Se aceptará automáticamente en ' + seconds + ' segundos si no hacés nada.';

                if (seconds <= 0) {
                    clearInterval(timer);
                    confirm(true);
                }
            }, 1000);
        </script>
    <?php endif; ?>

</body>

</html>
