<?php

declare(strict_types=1);

/**
 * =========================================================
 * REGISTRO PÚBLICO (autoinscripción a una lista de puerta)
 * ---------------------------------------------------------
 * Archivo ÚNICO y AUTOCONTENIDO. No modifica ningún otro
 * archivo del sistema (api.php, scanner.php, qr.php, script.js).
 *
 * Qué hace:
 *   1) Muestra un formulario (nombre + últimos 3 dígitos del DNI).
 *   2) Al enviarlo, ejecuta EXACTAMENTE la misma operación de base
 *      de datos que hace el panel cuando un RRPP agrega una
 *      persona (case 'person_add' en api.php) y EXACTAMENTE la
 *      misma generación de qr_token que hace el panel al mostrar
 *      el QR (case 'qr_generate' en api.php):
 *          - mismo INSERT en door_people (status "no_vino")
 *          - mismo criterio de duplicado (normalize_text + note)
 *          - mismo algoritmo de token: bin2hex(random_bytes(24))
 *          - mismas columnas actualizadas: qr_token, qr_enabled=1,
 *            qr_used_at=NULL
 *   3) Muestra el QR (misma URL que usa hoy el sistema:
 *      location.origin + "/qr.php?token=" + token) usando la MISMA
 *      función JS generarImagenQR(...) que ya existe en script.js
 *      (se incluye el mismo script.js + la misma librería QRious
 *      que usa listas.php, sin tocar ese archivo).
 *
 * -------------------------------------------------------------------
 * NOTA IMPORTANTE (léela antes de publicar el archivo):
 * -------------------------------------------------------------------
 * En api.php, la lógica de "person_add" y "qr_generate" NO son
 * funciones reutilizables independientes: son bloques "case" dentro
 * de un switch que se ejecuta después de exigir sesión iniciada
 * (require_login($pdo) en la línea 378 de api.php). No existe hoy
 * una función tipo crear_persona() que se pueda "llamar" desde
 * afuera, y este formulario es público (sin login), así que no puede
 * pasar por api.php sin que lo rechace con 401.
 *
 * Como pediste UN SOLO archivo nuevo y CERO cambios en los archivos
 * existentes, la única forma de lograr un comportamiento idéntico es
 * reproducir aquí, línea por línea, las mismas consultas SQL y el
 * mismo algoritmo de token que hoy usa api.php (no es "otra lógica":
 * es literalmente copiada). Si en algún momento preferís que sea una
 * función real y compartida (cero duplicación de SQL), la única
 * manera es extraer esos bloques a una función dentro de api.php,
 * lo cual sí implica tocar ese archivo.
 *
 * -------------------------------------------------------------------
 * ¿A qué lista se anota la gente?
 * -------------------------------------------------------------------
 * El requerimiento no especifica cómo se elige la lista, así que el
 * link lleva el id de la lista por URL, igual que cualquier RRPP
 * comparte su propio link:
 *
 *   https://tu-dominio/registro_publico.php?lista=ID_DE_LA_LISTA
 *
 * Podés ver el ID de cada lista en tu panel (listas.php). Si el
 * parámetro falta o no existe, se muestra un aviso en vez del
 * formulario.
 * =========================================================
 */

require_once __DIR__ . '/config/conexion.php'; // define $pdo (idéntico al resto del sistema)
require_once __DIR__ . '/const.php';           // define APP_NAME, etc.

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Copiada literal de api.php (normalize_text). Se usa para el mismo
 * criterio de "persona duplicada" que ya usa person_add en el panel.
 */
function normalize_text_public(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

/* =========================================================
   ENDPOINT AJAX (mismo archivo, sin redirección)
========================================================= */
if ($isPost && (($_GET['action'] ?? '') === 'registrar')) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '', true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $listId = (int) ($body['listId'] ?? 0);
    $name = trim((string) ($body['name'] ?? ''));
    $dni = trim((string) ($body['dni'] ?? ''));

    try {
        if ($listId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Falta el identificador de la lista.']);
            exit;
        }

        $stmtList = $pdo->prepare('SELECT id, name FROM door_lists WHERE id = :id LIMIT 1');
        $stmtList->execute([':id' => $listId]);
        $list = $stmtList->fetch(PDO::FETCH_ASSOC);

        if (!$list) {
            echo json_encode(['ok' => false, 'error' => 'La lista no existe.']);
            exit;
        }

        // Mismas validaciones de nombre que usa el sistema (ver parseo de "bulk" en script.js).
        if ($name === '' || !preg_match('/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/u', $name)) {
            echo json_encode(['ok' => false, 'error' => 'Ingresá tu nombre y apellido.']);
            exit;
        }

        // DNI: exactamente 3 números.
        if (!preg_match('/^\d{3}$/', $dni)) {
            echo json_encode(['ok' => false, 'error' => 'Ingresá exactamente los últimos 3 números de tu DNI.']);
            exit;
        }

        /* =====================================================
           MISMO CRITERIO DE DUPLICADO QUE person_add EN api.php
        ===================================================== */
        $stmtExisting = $pdo->prepare('SELECT id, name, note, qr_token FROM door_people WHERE list_id = :list_id');
        $stmtExisting->execute([':list_id' => $listId]);

        $existingPerson = null;
        foreach ($stmtExisting->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            if (
                normalize_text_public((string) $existing['name']) === normalize_text_public($name) &&
                trim((string) $existing['note']) === $dni
            ) {
                $existingPerson = $existing;
                break;
            }
        }

        if ($existingPerson) {
            // Ya está anotado: NO se crea otro registro. Se reutiliza el QR existente.
            $personId = (int) $existingPerson['id'];
            $token = (string) ($existingPerson['qr_token'] ?? '');

            if ($token === '') {
                // No tenía QR generado todavía: se genera con el MISMO algoritmo que qr_generate.
                $token = bin2hex(random_bytes(24));

                $stmtUpd = $pdo->prepare('
                    UPDATE door_people
                    SET qr_token = :token,
                        qr_enabled = 1,
                        qr_used_at = NULL
                    WHERE id = :id
                ');
                $stmtUpd->execute([':token' => $token, ':id' => $personId]);
            }
        } else {
            /* =================================================
               MISMO INSERT QUE person_add EN api.php
            ================================================= */
            $stmtInsert = $pdo->prepare('
                INSERT INTO door_people (list_id, name, note, status)
                VALUES (:list_id, :name, :note, "no_vino")
            ');
            $stmtInsert->execute([
                ':list_id' => $listId,
                ':name' => $name,
                ':note' => $dni,
            ]);

            $personId = (int) $pdo->lastInsertId();

            /* =================================================
               MISMA GENERACIÓN DE TOKEN QUE qr_generate EN api.php
            ================================================= */
            $token = bin2hex(random_bytes(24));

            $stmtUpd = $pdo->prepare('
                UPDATE door_people
                SET qr_token = :token,
                    qr_enabled = 1,
                    qr_used_at = NULL
                WHERE id = :id
            ');
            $stmtUpd->execute([':token' => $token, ':id' => $personId]);
        }

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'name' => $name,
            'listName' => $list['name'],
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error del servidor. Probá de nuevo.']);
        exit;
    }
}

/* =========================================================
   RENDER DEL FORMULARIO (GET)
========================================================= */
$listId = (int) ($_GET['lista'] ?? $_GET['listId'] ?? 0);
$list = null;

if ($listId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM door_lists WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $listId]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#0c0a12">
<title><?= h(APP_NAME) ?> · Anotarme</title>
<link rel="icon" type="image/x-icon" href="./favicon.ico">
<style>
  * { box-sizing: border-box; }

  body {
    font-family: Arial, sans-serif;
    background: #0c0a12;
    background-image:
      radial-gradient(circle at top, rgba(176, 124, 255, 0.16), transparent 34%),
      radial-gradient(circle at 80% 10%, rgba(240, 212, 141, 0.10), transparent 26%);
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
    padding: 28px 24px;
    width: min(430px, 92vw);
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
  }

  h1 {
    color: #f0d48d;
    margin: 6px 0 4px;
    font-size: 24px;
  }

  .subtitle {
    color: #aaa0b8;
    font-size: 14px;
    margin: 0 0 22px;
    line-height: 1.4;
  }

  label {
    display: block;
    text-align: left;
    font-size: 13px;
    color: #c9c0da;
    margin: 14px 0 6px;
    letter-spacing: .02em;
  }

  input[type="text"] {
    width: 100%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #33284a;
    background: #0f0b17;
    color: #fff;
    font-size: 16px;
    outline: none;
  }

  input[type="text"]:focus {
    border-color: #f0d48d;
  }

  .btn {
    width: 100%;
    margin-top: 22px;
    border: 0;
    border-radius: 14px;
    padding: 15px 16px;
    font-weight: 800;
    font-size: 16px;
    cursor: pointer;
    background: linear-gradient(135deg, #f7d774, #b07cff);
    color: #1a0f0f;
  }

  .btn:disabled {
    opacity: .6;
    cursor: default;
  }

  .error-box {
    margin-top: 14px;
    padding: 12px;
    border-radius: 12px;
    background: rgba(255, 107, 107, .12);
    border: 1px solid rgba(255, 107, 107, .4);
    color: #ff9b9b;
    font-size: 13.5px;
    display: none;
    text-align: left;
  }

  .icon-ok { font-size: 50px; color: #36c985; margin-bottom: 6px; }

  .name-ok { font-size: 21px; color: #fff; font-weight: 800; margin: 10px 0 2px; }

  .list-ok { color: #c9c0da; font-size: 14px; margin: 0 0 18px; }

  .qr-wrap {
    background: #fff;
    border-radius: 16px;
    padding: 14px;
    display: inline-block;
    margin: 6px auto 18px;
  }

  .qr-wrap canvas { display: block; }

  .warn-box {
    text-align: left;
    background: rgba(240, 212, 141, .08);
    border: 1px solid rgba(240, 212, 141, .3);
    border-radius: 14px;
    padding: 14px 16px;
    font-size: 13.5px;
    line-height: 1.55;
    color: #f0e6d8;
  }

  .warn-box b { color: #f0d48d; }

  .no-list {
    color: #ff9b9b;
    font-size: 14.5px;
    line-height: 1.5;
  }

  #formBox, #okBox { display: none; }
  #formBox.show, #okBox.show { display: block; }
</style>
</head>
<body data-page="registro-publico">

  <div class="box">
    <?php if (!$list): ?>
      <div class="icon-ok" style="color:#ff6b6b;">×</div>
      <h1>Link inválido</h1>
      <p class="no-list">
        Este link no tiene una lista asociada válida.<br>
        Pedile al organizador el link correcto (debe incluir <code>?lista=ID</code>).
      </p>
    <?php else: ?>

      <div id="formBox" class="show">
        <h1>Anotate en la lista</h1>
        <p class="subtitle">
          Lista: <b style="color:#f0d48d;"><?= h($list['name']) ?></b><br>
          Completá tus datos para anotarte.
        </p>

        <label for="f-name">Nombre y apellido</label>
        <input type="text" id="f-name" placeholder="Ej: Nicolas Ochoa" autocomplete="name">

        <label for="f-dni">Últimos 3 números de tu DNI</label>
        <input type="text" id="f-dni" placeholder="Ej: 991" inputmode="numeric" maxlength="3" autocomplete="off">

        <button type="button" class="btn" id="btnSubmit" onclick="registrarPersona()">Anotarme</button>

        <div class="error-box" id="errorBox"></div>
      </div>

      <div id="okBox">
        <div class="icon-ok">✔</div>
        <h1>Ya estás anotado</h1>
        <p class="name-ok" id="ok-name"></p>
        <p class="list-ok" id="ok-list"></p>

        <div class="qr-wrap">
          <canvas id="qrCanvas" width="260" height="260"></canvas>
        </div>

        <div class="warn-box">
          📸 <b>IMPORTANTE</b><br><br>
          Sacale una captura de pantalla.<br><br>
          Si cerrás esta página no vas a volver a ver este QR.<br><br>
          Presentalo en puerta para ingresar.<br><br>
          No compartas este QR.
        </div>
      </div>

    <?php endif; ?>
  </div>

  <?php if ($list): ?>
  <!-- Misma librería QRious y mismo script.js que usa listas.php,
       para reutilizar exactamente la función generarImagenQR(...) -->
  <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
  <script src="script.js?v=<?= time() ?>"></script>
  <script>
    const LIST_ID = <?= (int) $list['id'] ?>;

    function showError(msg) {
      const box = document.getElementById('errorBox');
      box.textContent = msg;
      box.style.display = 'block';
    }

    function hideError() {
      const box = document.getElementById('errorBox');
      box.style.display = 'none';
    }

    async function registrarPersona() {
      hideError();

      const nameInput = document.getElementById('f-name');
      const dniInput = document.getElementById('f-dni');
      const btn = document.getElementById('btnSubmit');

      const name = nameInput.value.trim();
      const dni = dniInput.value.trim();

      if (!name) {
        showError('Ingresá tu nombre y apellido.');
        return;
      }

      if (!/^\d{3}$/.test(dni)) {
        showError('Ingresá exactamente los últimos 3 números de tu DNI.');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Anotando...';

      try {
        const res = await fetch('registro_publico.php?action=registrar', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ listId: LIST_ID, name, dni })
        });

        const data = await res.json();

        if (!res.ok || !data.ok) {
          showError(data.error || 'No se pudo completar el registro.');
          btn.disabled = false;
          btn.textContent = 'Anotarme';
          return;
        }

        mostrarQR(data.token, data.name, data.listName);
      } catch (err) {
        showError('No se pudo conectar con el servidor. Probá de nuevo.');
        btn.disabled = false;
        btn.textContent = 'Anotarme';
      }
    }

    function mostrarQR(token, name, listName) {
      document.getElementById('formBox').classList.remove('show');

      const okBox = document.getElementById('okBox');
      okBox.classList.add('show');

      document.getElementById('ok-name').textContent = name;
      document.getElementById('ok-list').textContent = 'Lista: ' + listName;

      // Misma URL que arma hoy el sistema para el QR.
      const qrLink = location.origin + '/qr.php?token=' + encodeURIComponent(token);

      // Render visible en pantalla (para la captura de pantalla).
      if (typeof QRious !== 'undefined') {
        new QRious({
          element: document.getElementById('qrCanvas'),
          value: qrLink,
          size: 260,
          background: 'white',
          foreground: 'black'
        });
      }

      // Reutiliza EXACTAMENTE la misma función existente del sistema
      // (script.js) para generar/compartir la imagen del QR, igual
      // que cuando el RRPP agrega una persona desde el panel.
      if (typeof generarImagenQR === 'function') {
        generarImagenQR({
          token,
          personName: name,
          personNote: '',
          listName,
          expiresAt: '03:00 AM'
        });
      }
    }

    document.getElementById('f-dni')?.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 3);
    });
  </script>
  <?php endif; ?>

</body>
</html>
