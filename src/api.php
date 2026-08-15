<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/app_logs.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('No se pudo iniciar la conexión PDO.');
}

header('Content-Type: application/json; charset=utf-8');








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
function is_kioskito(array $user): bool
{
    return ($user['role'] ?? '') === 'kioskito';
}

function is_guardarropas(array $user): bool
{
    return ($user['role'] ?? '') === 'guardarropas';
}

function require_role(array $allowedRoles): void
{
    $role = current_role();

    if ($role === '') {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Tenés que iniciar sesión para usar el escáner.'
        ]);
        exit;
    }

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'No tenés permiso para activar este QR.'
        ]);
        exit;
    }
}






/* =========================================================
   RESPUESTAS JSON Y VALIDACIONES BASE
========================================================= */


function response_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $data = []): never
{
    response_json(['ok' => true] + $data);
}

function fail(string $message, int $status = 400): never
{
    response_json(['ok' => false, 'error' => $message], $status);
}

function require_login(PDO $pdo): array
{

    if (!isset($_SESSION['user']['id'])) {
        fail('No autorizado. Iniciá sesión nuevamente.', 401);
    }

    $stmt = $pdo->prepare("
        SELECT id, username, display_name, role
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => (int) $_SESSION['user']['id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        fail('Usuario inexistente. Iniciá sesión nuevamente.', 401);
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role'],
    ];

    return $_SESSION['user'];
}

function require_admin(array $user): void
{
    if (($user['role'] ?? '') !== 'admin') {
        fail('No tenés permiso para realizar esta acción.', 403);
    }
}
function is_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function is_puerta(array $user): bool
{
    return ($user['role'] ?? '') === 'puerta';
}

function is_door_manager(array $user): bool
{
    return is_admin($user) || is_puerta($user);
}

function require_door_manager(array $user): void
{
    $role = strtolower(trim((string) ($user['role'] ?? '')));

    if (!in_array($role, ['admin', 'puerta'], true)) {
        fail('No tenés permiso para usar el escáner QR.', 403);
    }
}

function read_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST ?: [];
}

function normalize_text(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

/* =========================================================
   PERMISOS Y HELPERS DE NORMALIZACIÓN
========================================================= */
function normalize_payment_method(string $method): string
{
    $method = strtolower(trim($method));

    return in_array($method, ['efectivo', 'transferencia', 'tarjeta', 'regalo'], true)
        ? $method
        : 'efectivo';
}

function payment_label(string $method): string
{
    return match ($method) {
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'regalo' => 'Regalo',
        default => 'Efectivo',
    };
}


function build_kiosko_summary(PDO $pdo, int $fromSaleId = 0): array
{
    $stmt = $pdo->prepare("
        SELECT id, items, total, payment_method, created_at
        FROM kiosko_sales
        WHERE id > :from_sale_id
        ORDER BY id ASC
    ");

    $stmt->execute([
        ':from_sale_id' => $fromSaleId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'sales_count' => count($rows),
        'total_amount' => 0,
        'from_sale_id' => $rows ? (int) $rows[0]['id'] : 0,
        'to_sale_id' => $rows ? (int) $rows[count($rows) - 1]['id'] : 0,
        'by_payment' => [
            'efectivo' => 0,
            'transferencia' => 0,
            'tarjeta' => 0,
            'regalo' => 0,
        ],
        'products' => [],
    ];

    $products = [];

    foreach ($rows as $row) {
        $total = (int) $row['total'];
        $paymentMethod = normalize_payment_method((string) ($row['payment_method'] ?? 'efectivo'));

        $summary['total_amount'] += $total;
        $summary['by_payment'][$paymentMethod] += $total;

        $items = json_decode((string) $row['items'], true);

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? 'Producto'));
            $qty = (int) ($item['qty'] ?? 0);
            $subtotal = (int) ($item['subtotal'] ?? 0);

            if ($name === '' || $qty <= 0) {
                continue;
            }

            if (!isset($products[$name])) {
                $products[$name] = [
                    'name' => $name,
                    'qty' => 0,
                    'subtotal' => 0,
                ];
            }

            $products[$name]['qty'] += $qty;
            $products[$name]['subtotal'] += $subtotal;
        }
    }

    usort($products, function ($a, $b) {
        return $b['qty'] <=> $a['qty'];
    });

    $summary['products'] = array_values($products);

    return $summary;
}

function current_user_can_access_list(PDO $pdo, int $listId, array $user): array
{
    $stmt = $pdo->prepare('SELECT dl.*, u.display_name AS owner_name FROM door_lists dl INNER JOIN users u ON u.id = dl.user_id WHERE dl.id = :id LIMIT 1');
    $stmt->execute([':id' => $listId]);
    $list = $stmt->fetch();

    if (!$list) {
        fail('Lista no encontrada.', 404);
    }

    if (($user['role'] ?? '') !== 'admin' && (int) $list['user_id'] !== (int) $user['id']) {
        fail('No tenés permiso para esta lista.', 403);
    }

    return $list;
}

/* =========================================================
   FORMATEADORES DE RESPUESTA
========================================================= */

function product_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'price' => (int) $row['price'],
        'cat' => $row['cat'],
        'sub' => $row['sub'] ?? '',
        'qty' => (int) $row['qty'],
        'custom' => (bool) $row['custom'],
    ];
}

function person_row(array $person): array
{
    return [
        'id' => (int) $person['id'],
        'listId' => (int) $person['list_id'],
        'name' => $person['name'] ?? '',
        'note' => $person['note'] ?? '',
        'status' => $person['status'] ?? 'no_vino',
        'qr_token' => $person['qr_token'] ?? null,
        'qr_enabled' => (int) ($person['qr_enabled'] ?? 0),
        'qr_used_at' => $person['qr_used_at'] ?? null,
    ];
}

/* =========================================================
   INICIO DE API
========================================================= */
function try_remember_login(PDO $pdo): void
{
    if (isset($_SESSION['user']['id'])) {
        return;
    }

    if (empty($_COOKIE['divine_remember'])) {
        return;
    }

    $parts = explode(':', (string) $_COOKIE['divine_remember']);

    if (count($parts) !== 2) {
        setcookie('divine_remember', '', time() - 3600, '/');
        return;
    }

    [$selector, $token] = $parts;

    $stmt = $pdo->prepare("
        SELECT 
            rt.user_id,
            rt.token_hash,
            u.id,
            u.username,
            u.display_name,
            u.role
        FROM user_remember_tokens rt
        INNER JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = :selector
          AND rt.expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        ':selector' => $selector,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($token, $row['token_hash'])) {
        $stmtDelete = $pdo->prepare("
            DELETE FROM user_remember_tokens
            WHERE selector = :selector
        ");
        $stmtDelete->execute([':selector' => $selector]);

        setcookie('divine_remember', '', time() - 3600, '/');
        return;
    }

    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'display_name' => $row['display_name'],
        'role' => $row['role'],
    ];
}

try_remember_login($pdo);

$user = require_login($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = read_input();
function require_admin_or_kiosko(array $user): void
{
    $role = strtolower(trim((string) ($user['role'] ?? '')));

    if (!in_array($role, ['admin', 'kiosko', 'kioskito'], true)) {
        fail('No tenés permiso para realizar esta acción.', 403);
    }
}

try {
    switch ($action) {
        case 'me': {
                ok(['user' => $user]);
            }

            /* =========================
           PRODUCTOS / KIOSKITO
        ========================= */
        case 'products_list': {
                require_admin_or_kiosko($user);
                $stmt = $pdo->query('SELECT * FROM products WHERE active = 1 ORDER BY custom ASC, id ASC');
                $products = array_map('product_row', $stmt->fetchAll());
                ok(['products' => $products]);
            }

        case 'product_add': {
                require_admin($user);
                $name = trim((string) ($input['name'] ?? ''));
                $price = max(0, (int) ($input['price'] ?? 0));
                $cat = trim((string) ($input['cat'] ?? 'Otros')) ?: 'Otros';
                $sub = trim((string) ($input['sub'] ?? ''));

                if ($name === '') {
                    fail('El nombre del producto es obligatorio.');
                }

                $stmt = $pdo->prepare('INSERT INTO products (code, name, price, cat, sub, qty, custom, active) VALUES (NULL, :name, :price, :cat, :sub, 0, 1, 1)');
                $stmt->execute([
                    ':name' => $name,
                    ':price' => $price,
                    ':cat' => $cat,
                    ':sub' => $sub,
                ]);
                ok(['id' => (int) $pdo->lastInsertId()]);
            }

        case 'product_delete': {
                require_admin($user);
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) fail('Producto inválido.');

                $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id AND custom = 1');
                $stmt->execute([':id' => $id]);

                if ($stmt->rowCount() === 0) {
                    fail('Solo se pueden eliminar productos creados manualmente.');
                }
                ok();
            }

        case 'product_qty': {
                require_admin($user);
                $id = (int) ($input['id'] ?? 0);
                $delta = (int) ($input['delta'] ?? 0);
                if ($id <= 0 || $delta === 0) fail('Datos inválidos.');

                $stmt = $pdo->prepare('UPDATE products SET qty = GREATEST(0, qty + :delta) WHERE id = :id AND active = 1');
                $stmt->execute([':delta' => $delta, ':id' => $id]);
                ok();
            }

            /* =========================
           PUERTA / LISTAS
        ========================= */
        case 'door_lists': {

                $forceMine = !empty($_GET['mine']);

                // Si el admin eligió "Mi lista", se comporta como un usuario normal.
                $isDoorManager = is_door_manager($user) && !$forceMine;

                if ($isDoorManager) {

                    $stmt = $pdo->query("
            SELECT
                dl.id, dl.user_id, dl.name, dl.is_birthday, dl.price_per_person, dl.created_at,
                u.display_name AS owner_name
            FROM door_lists dl
            INNER JOIN users u
                ON u.id = dl.user_id
            ORDER BY
                dl.created_at DESC,
                dl.id DESC
        ");

                    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {

                    $stmt = $pdo->prepare("
            SELECT
                dl.id, dl.user_id, dl.name, dl.is_birthday, dl.price_per_person, dl.created_at,
                u.display_name AS owner_name
            FROM door_lists dl
            INNER JOIN users u
                ON u.id = dl.user_id
            WHERE
                dl.user_id = :user_id
            ORDER BY
                dl.created_at DESC,
                dl.id DESC
        ");

                    $stmt->execute([
                        ':user_id' => (int)$user['id']
                    ]);

                    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                $ids = array_map(
                    fn($list) => (int)$list['id'],
                    $lists
                );

                $peopleByList = [];

                if ($ids) {

                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    $stmtPeople = $pdo->prepare("
            SELECT id, list_id, name, note, status, qr_token, qr_enabled, qr_used_at
            FROM door_people
            WHERE list_id IN ($placeholders)
            ORDER BY id ASC
        ");

                    $stmtPeople->execute($ids);

                    foreach ($stmtPeople->fetchAll(PDO::FETCH_ASSOC) as $person) {
                        $peopleByList[(int)$person['list_id']][] = person_row($person);
                    }
                }

                $result = array_map(function ($list) use ($peopleByList) {

                    $id = (int)$list['id'];

                    return [
                        'id' => $id,
                        'userId' => (int)$list['user_id'],
                        'ownerName' => $list['owner_name'] ?? '',
                        'name' => $list['name'],
                        'isBirthday' => (bool)$list['is_birthday'],
                        'pricePerPerson' => (int)$list['price_per_person'],
                        'people' => $peopleByList[$id] ?? [],
                    ];
                }, $lists);

                $hiddenEmptyLists = [];

                // Solo ocultamos listas vacías cuando el admin está viendo TODAS.
                if ($isDoorManager) {

                    $filtered = [];

                    foreach ($result as $list) {

                        $peopleCount = count($list['people']);

                        if ($peopleCount > 0) {
                            $filtered[] = $list;
                            continue;
                        }

                        // Mantener cumpleaños aunque esté vacío.
                        if ($list['isBirthday']) {
                            $filtered[] = $list;
                            continue;
                        }

                        // Mantener mi propia lista principal aunque esté vacía.
                        if ((int)$list['userId'] === (int)$user['id']) {
                            $filtered[] = $list;
                            continue;
                        }

                        // Ocultar únicamente listas principales vacías de otros usuarios.
                        $hiddenEmptyLists[] = [
                            'name' => $list['name'],
                            'ownerName' => $list['ownerName']
                        ];
                    }

                    $result = $filtered;
                }

                ok([
                    'lists' => $result,
                    'hiddenEmptyLists' => $hiddenEmptyLists
                ]);
            }

        case 'list_add': {
                $isBirthday = !empty($input['isBirthday']);
                $pricePerPerson = $isBirthday ? 1000 : 500;
                $baseName = trim((string) ($user['display_name'] ?? $user['username'] ?? 'Usuario'));

                if ($baseName === '') {
                    $baseName = 'Usuario';
                }

                if ($isBirthday) {
                    $birthdayPrefix = $baseName . ' Cumpleaños ';
                    $stmtMax = $pdo->prepare("
                    SELECT COALESCE(MAX(CAST(TRIM(SUBSTRING(name, CHAR_LENGTH(:prefix) + 1)) AS UNSIGNED)), 0)
                    FROM door_lists
                    WHERE user_id = :user_id
                      AND is_birthday = 1
                      AND name LIKE :name_like
                ");
                    $stmtMax->execute([
                        ':prefix' => $birthdayPrefix,
                        ':user_id' => (int) $user['id'],
                        ':name_like' => $birthdayPrefix . '%',
                    ]);
                    $nextNumber = ((int) $stmtMax->fetchColumn()) + 1;
                    $name = $birthdayPrefix . $nextNumber;
                } else {
                    $name = $baseName;

                    $stmtExisting = $pdo->prepare('SELECT id FROM door_lists WHERE user_id = :user_id AND is_birthday = 0 ORDER BY id ASC LIMIT 1');
                    $stmtExisting->execute([':user_id' => (int) $user['id']]);
                    $existingId = $stmtExisting->fetchColumn();

                    if ($existingId) {
                        $stmtRename = $pdo->prepare('UPDATE door_lists SET name = :name, price_per_person = 500 WHERE id = :id');
                        $stmtRename->execute([':name' => $name, ':id' => (int) $existingId]);

                        ok([
                            'id' => (int) $existingId,
                            'name' => $name,
                            'existing' => true,
                            'message' => 'Ya existe tu lista principal.',
                        ]);
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO door_lists (user_id, name, is_birthday, price_per_person) VALUES (:user_id, :name, :is_birthday, :price_per_person)');
                $stmt->execute([
                    ':user_id' => (int) $user['id'],
                    ':name' => $name,
                    ':is_birthday' => $isBirthday ? 1 : 0,
                    ':price_per_person' => $pricePerPerson,
                ]);

                $listId = (int) $pdo->lastInsertId();
                app_log(
                    $pdo,
                    (int) $user['id'],
                    (string) ($user['username'] ?? ''),
                    'list_create',
                    'door_list',
                    $listId,
                    'Lista creada',
                    [
                        'list_id' => $listId,
                        'list_name' => $name,
                        'is_birthday' => $isBirthday,
                        'price_per_person' => $pricePerPerson,
                    ]
                );

                ok(['id' => $listId, 'name' => $name]);
            }

        case 'list_delete': {

                $listId = (int) ($input['id'] ?? 0);

                if ($listId <= 0) {
                    fail('Lista inválida.');
                }

                $list = current_user_can_access_list($pdo, $listId, $user);

                // ======================================================
                // Si es la lista principal NO se elimina.
                // Solamente se vacían las personas.
                // ======================================================
                if (!(bool)$list['is_birthday']) {

                    $stmt = $pdo->prepare("
            DELETE FROM door_people
            WHERE list_id = :id
        ");

                    $stmt->execute([
                        ':id' => $listId
                    ]);

                    ok([
                        'cleared' => true,
                        'message' => 'La lista fue vaciada correctamente.'
                    ]);
                }

                // ======================================================
                // Las listas de cumpleaños sí se eliminan completamente.
                // ======================================================

                app_log(
                    $pdo,
                    (int) $user['id'],
                    (string) ($user['username'] ?? ''),
                    'list_delete',
                    'door_list',
                    $listId,
                    'Lista eliminada',
                    [
                        'deleted_list_id' => $listId,
                        'deleted_list_name' => $list['name'] ?? '',
                        'deleted_list_owner_user_id' => isset($list['user_id']) ? (int) $list['user_id'] : null,
                        'deleted_list_owner_name' => $list['owner_name'] ?? '',
                        'deleted_by_user_id' => (int) $user['id'],
                        'deleted_by_username' => $user['username'] ?? '',
                    ]
                );

                $pdo->beginTransaction();

                try {

                    $stmt = $pdo->prepare("
            DELETE FROM door_people
            WHERE list_id = :id
        ");

                    $stmt->execute([
                        ':id' => $listId
                    ]);

                    $stmt = $pdo->prepare("
            DELETE FROM door_lists
            WHERE id = :id
        ");

                    $stmt->execute([
                        ':id' => $listId
                    ]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('No se pudo eliminar la lista.');
                    }

                    $pdo->commit();

                    ok();
                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    fail($e->getMessage());
                }
            }

        case 'person_add': {
                $listId = (int) ($input['listId'] ?? 0);
                $name = trim((string) ($input['name'] ?? ''));
                $note = trim((string) ($input['note'] ?? ''));

                if ($listId <= 0) fail('Lista inválida.');
                current_user_can_access_list($pdo, $listId, $user);

                if ($name === '' || $note === '') {
                    fail('Completá nombre y dato/número.');
                }

                $stmt = $pdo->prepare('SELECT name, note FROM door_people WHERE list_id = :list_id');
                $stmt->execute([':list_id' => $listId]);

                foreach ($stmt->fetchAll() as $existing) {
                    if (
                        normalize_text($existing['name']) === normalize_text($name) &&
                        trim((string) $existing['note']) === $note
                    ) {
                        fail('Esa persona ya está cargada en esta lista.');
                    }
                }

                $stmt = $pdo->prepare('
                INSERT INTO door_people (list_id, name, note, status)
                VALUES (:list_id, :name, :note, "no_vino")
            ');

                $stmt->execute([
                    ':list_id' => $listId,
                    ':name' => $name,
                    ':note' => $note,
                ]);

                ok(['id' => (int) $pdo->lastInsertId()]);
            }

        case 'people_bulk': {
                $listId = (int) ($input['listId'] ?? 0);
                $people = $input['people'] ?? [];

                if ($listId <= 0) fail('Lista inválida.');
                current_user_can_access_list($pdo, $listId, $user);
                if (!is_array($people)) fail('Lista inválida.');

                $stmt = $pdo->prepare('SELECT name, note FROM door_people WHERE list_id = :list_id');
                $stmt->execute([':list_id' => $listId]);
                $existingKeys = [];
                foreach ($stmt->fetchAll() as $existing) {
                    $existingKeys[normalize_text($existing['name']) . '|' . trim((string) $existing['note'])] = true;
                }

                $insert = $pdo->prepare('INSERT INTO door_people (list_id, name, note, status) VALUES (:list_id, :name, :note, "no_vino")');
                $added = 0;
                $repeated = 0;
                $ignored = 0;

                $pdo->beginTransaction();
                foreach ($people as $person) {
                    $name = trim((string) ($person['name'] ?? ''));
                    $note = trim((string) ($person['note'] ?? ''));
                    if ($name === '' || $note === '') {
                        $ignored++;
                        continue;
                    }

                    $key = normalize_text($name) . '|' . $note;
                    if (isset($existingKeys[$key])) {
                        $repeated++;
                        continue;
                    }

                    $insert->execute([':list_id' => $listId, ':name' => $name, ':note' => $note]);
                    $existingKeys[$key] = true;
                    $added++;
                }
                $pdo->commit();

                ok(['added' => $added, 'repeated' => $repeated, 'ignored' => $ignored]);
            }

        case 'person_toggle_status': {
                require_door_manager($user);

                $listId = (int) ($input['listId'] ?? 0);
                $personId = (int) ($input['personId'] ?? 0);

                if ($listId <= 0 || $personId <= 0) {
                    fail('Datos inválidos.');
                }

                $stmt = $pdo->prepare('
                SELECT status
                FROM door_people
                WHERE id = :person_id
                AND list_id = :list_id
                LIMIT 1
            ');

                $stmt->execute([
                    ':person_id' => $personId,
                    ':list_id' => $listId
                ]);

                $person = $stmt->fetch();

                if (!$person) {
                    fail('Persona no encontrada.', 404);
                }

                $current = $person['status'];

                $next = match ($current) {
                    'no_vino' => 'entro',
                    'entro' => 'se_fue',
                    default => 'no_vino',
                };

                $stmt = $pdo->prepare('
                UPDATE door_people
                SET status = :status
                WHERE id = :person_id
                AND list_id = :list_id
            ');

                $stmt->execute([
                    ':status' => $next,
                    ':person_id' => $personId,
                    ':list_id' => $listId
                ]);

                ok(['status' => $next]);
            }

        case 'person_delete': {
                $listId = (int) ($input['listId'] ?? 0);
                $personId = (int) ($input['personId'] ?? 0);

                if ($listId <= 0 || $personId <= 0) {
                    fail('Datos inválidos.', 422);
                }

                // Verifica que sea Admin o el dueño de la lista.
                current_user_can_access_list($pdo, $listId, $user);

                $stmtPerson = $pdo->prepare("
                SELECT id, name, status
                FROM door_people
                WHERE id = :person_id
                AND list_id = :list_id
                LIMIT 1
            ");

                $stmtPerson->execute([
                    ':person_id' => $personId,
                    ':list_id' => $listId,
                ]);

                $person = $stmtPerson->fetch(PDO::FETCH_ASSOC);

                if (!$person) {
                    fail('Persona no encontrada.', 404);
                }

                $role = strtolower(trim((string) ($user['role'] ?? '')));
                $status = (string) ($person['status'] ?? 'no_vino');

                /*
            * Una RRPP/Pública solamente puede borrar personas
            * que todavía no hayan ingresado.
            *
            * Admin sí puede borrar cualquiera.
            */
                if ($role !== 'admin' && $status !== 'no_vino') {
                    fail(
                        'No podés eliminar a esta persona porque ya ingresó al evento.',
                        403
                    );
                }

                $stmtDelete = $pdo->prepare("
                DELETE FROM door_people
                WHERE id = :person_id
                AND list_id = :list_id
            ");

                $stmtDelete->execute([
                    ':person_id' => $personId,
                    ':list_id' => $listId,
                ]);

                ok([
                    'message' => 'Persona eliminada correctamente.',
                ]);
            }

            /* =========================
           USUARIOS
        ========================= */
        case 'users_list': {
                require_admin($user);

                $stmt = $pdo->query("
                SELECT id, username, display_name, role, created_at
                FROM users
                ORDER BY id ASC
            ");

                ok([
                    'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
            }

        case 'user_create': {
                require_admin($user);

                $username = trim((string) ($input['username'] ?? ''));
                $displayName = trim((string) ($input['displayName'] ?? ''));
                $password = (string) ($input['password'] ?? '');
                $role = (string) ($input['role'] ?? 'usuario');

                if ($username === '' || $displayName === '' || $password === '') {
                    fail('Faltan datos.', 422);
                }

                if (!in_array($role, ['admin', 'usuario', 'puerta'], true)) {
                    fail('Rol inválido.', 422);
                }

                if (strlen($password) < 4) {
                    fail('La contraseña debe tener al menos 4 caracteres.', 422);
                }

                $stmt = $pdo->prepare("
                INSERT INTO users (
                    username,
                    display_name,
                    password_hash,
                    role
                ) VALUES (
                    :username,
                    :display_name,
                    :password_hash,
                    :role
                )
            ");

                try {
                    $stmt->execute([
                        ':username' => $username,
                        ':display_name' => $displayName,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role,
                    ]);
                } catch (PDOException $e) {
                    fail('Ese usuario ya existe.', 409);
                }

                ok([
                    'message' => 'Usuario creado correctamente.'
                ]);
            }

        case 'user_update': {
                require_admin($user);

                $id = (int) ($input['id'] ?? 0);
                $password = (string) ($input['password'] ?? '');
                $role = (string) ($input['role'] ?? 'usuario');

                if ($id <= 0) {
                    fail('ID inválido.', 422);
                }

                if (!in_array($role, ['admin', 'usuario', 'puerta'], true)) {
                    fail('Rol inválido.', 422);
                }

                if ($password !== '') {
                    if (strlen($password) < 4) {
                        fail('La contraseña debe tener al menos 4 caracteres.', 422);
                    }

                    $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        role = :role,
                        password_hash = :password_hash
                    WHERE id = :id
                ");

                    $stmt->execute([
                        ':role' => $role,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':id' => $id,
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                    UPDATE users
                    SET role = :role
                    WHERE id = :id
                ");

                    $stmt->execute([
                        ':role' => $role,
                        ':id' => $id,
                    ]);
                }

                ok([
                    'message' => 'Usuario actualizado.'
                ]);
            }

            /* =========================
           GUARDARROPAS
        ========================= */
        case 'guardarropas_list': {
                $stmt = $pdo->query("
                SELECT *
                FROM guardarropas
                ORDER BY id DESC
            ");

                $items = array_map(function (array $row): array {
                    return [
                        'id'            => (int) $row['id'],
                        'numero'        => (int) $row['numero'],
                        'codigo'        => $row['codigo'],
                        'nombre'        => $row['nombre'],
                        'dni'           => $row['dni'],
                        'telefono'      => $row['telefono'],
                        'precio'        => (int) $row['precio'],
                        'estado'        => $row['estado'],
                        'hora_ingreso'  => $row['hora_ingreso'],
                        'hora_retirado' => $row['hora_retirado'],
                        'created_by'    => (int) $row['created_by'],
                    ];
                }, $stmt->fetchAll(PDO::FETCH_ASSOC));

                ok(['items' => $items]);
            }

        case 'guardarropas_add': {
                require_admin_or_kiosko($user);

                $nombre   = trim((string) ($input['nombre']   ?? ''));
                $dni      = trim((string) ($input['dni']      ?? ''));
                $telefono = trim((string) ($input['telefono'] ?? ''));

                if ($nombre === '') {
                    fail('El nombre es obligatorio.');
                }

                $stmtMax = $pdo->query("SELECT COALESCE(MAX(numero), 0) FROM guardarropas");
                $numero  = ((int) $stmtMax->fetchColumn()) + 1;
                $codigo  = 'GR-' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                INSERT INTO guardarropas
                    (numero, codigo, nombre, dni, telefono, precio, estado, created_by)
                VALUES
                    (:numero, :codigo, :nombre, :dni, :telefono, :precio, 'pendiente', :created_by)
            ");

                $stmt->execute([
                    ':numero'     => $numero,
                    ':codigo'     => $codigo,
                    ':nombre'     => $nombre,
                    ':dni'        => $dni      !== '' ? $dni      : null,
                    ':telefono'   => $telefono !== '' ? $telefono : null,
                    ':precio'     => 2000,
                    ':created_by' => (int) $user['id'],
                ]);

                ok([
                    'id'     => (int) $pdo->lastInsertId(),
                    'numero' => $numero,
                    'codigo' => $codigo,
                ]);
            }

        case 'guardarropas_entregar': {
                require_admin_or_kiosko($user);

                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) {
                    fail('ID inválido.');
                }

                $stmtCheck = $pdo->prepare("SELECT estado FROM guardarropas WHERE id = :id LIMIT 1");
                $stmtCheck->execute([':id' => $id]);
                $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    fail('Prenda no encontrada.', 404);
                }
                if ($row['estado'] === 'retirado') {
                    fail('Esta prenda ya fue retirada.');
                }

                $stmt = $pdo->prepare("
                UPDATE guardarropas
                SET estado = 'retirado', hora_retirado = NOW()
                WHERE id = :id
            ");
                $stmt->execute([':id' => $id]);

                ok();
            }
        case 'products_reset': {
                require_admin($user);

                $pin = (string) ($input['pin'] ?? '');

                if ($pin !== '1234') {
                    fail('PIN incorrecto.');
                }

                $pdo->beginTransaction();

                // Borra historial de ventas
                $pdo->exec('DELETE FROM kiosko_sales');

                // Reinicia cantidades, pero conserva TODOS los productos
                $pdo->exec('UPDATE products SET qty = 0');

                $pdo->commit();

                ok();
            }

        case 'guardarropas_delete': {
                require_admin_or_kiosko($user);

                $id = (int) ($input['id'] ?? 0);

                if ($id <= 0) {
                    fail('ID inválido.');
                }

                $stmt = $pdo->prepare("
                SELECT *
                FROM guardarropas
                WHERE id = :id
                LIMIT 1
            ");

                $stmt->execute([
                    ':id' => $id
                ]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    fail('Guardarropas no encontrado.', 404);
                }

                // Opcional: solo permitir borrar retirados
                if ($row['estado'] !== 'retirado') {
                    fail('Solo se pueden eliminar guardarropas retirados.');
                }

                app_log(
                    $pdo,
                    (int) $user['id'],
                    (string) ($user['username'] ?? ''),
                    'guardarropas_delete',
                    'guardarropas',
                    (int) $row['id'],
                    'Guardarropas eliminado manualmente',
                    [
                        'codigo' => $row['codigo'],
                        'numero' => (int) $row['numero'],
                        'nombre' => $row['nombre'],
                        'dni' => $row['dni'],
                        'telefono' => $row['telefono'],
                        'precio' => (int) $row['precio'],
                        'estado' => $row['estado'],
                    ]
                );

                $stmtDelete = $pdo->prepare("
                DELETE FROM guardarropas
                WHERE id = :id
            ");

                $stmtDelete->execute([
                    ':id' => $id
                ]);

                ok();
            }
        case 'product_edit': {
                require_admin($user);

                $id    = (int) ($input['id'] ?? 0);
                $name  = trim((string) ($input['name'] ?? ''));
                $price = max(0, (int) ($input['price'] ?? 0));
                $cat   = trim((string) ($input['cat'] ?? 'Otros')) ?: 'Otros';
                $sub   = trim((string) ($input['sub'] ?? ''));

                if ($id <= 0 || $name === '') {
                    fail('Datos inválidos.');
                }

                $stmt = $pdo->prepare("
                UPDATE products
                SET name = :name,
                    price = :price,
                    cat = :cat,
                    sub = :sub
                WHERE id = :id
            ");

                $stmt->execute([
                    ':name'  => $name,
                    ':price' => $price,
                    ':cat'   => $cat,
                    ':sub'   => $sub,
                    ':id'    => $id,
                ]);

                ok();
            }
            /* =========================
           VENTAS / HISTORIAL
        ========================= */
        case 'sale_register': {
                require_admin_or_kiosko($user);

                $items = $input['items'] ?? [];
                $total = max(0, (int) ($input['total'] ?? 0));
                $paymentMethod = normalize_payment_method((string) ($input['paymentMethod'] ?? 'efectivo'));
                $clientSaleId = trim((string) ($input['clientSaleId'] ?? ''));

                if (!is_array($items) || count($items) === 0) {
                    fail('No hay productos en la venta.');
                }

                if ($total <= 0 && $paymentMethod !== 'regalo') {
                    fail('Total inválido.');
                }

                if ($clientSaleId === '') {
                    $clientSaleId = bin2hex(random_bytes(16));
                }

                $stmtExisting = $pdo->prepare("
                SELECT id
                FROM kiosko_sales
                WHERE client_sale_id = :client_sale_id
                LIMIT 1
            ");

                $stmtExisting->execute([
                    ':client_sale_id' => $clientSaleId
                ]);

                $existingId = $stmtExisting->fetchColumn();

                if ($existingId) {
                    ok([
                        'id' => (int) $existingId,
                        'duplicate' => true,
                        'message' => 'Venta ya registrada previamente.'
                    ]);
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                INSERT INTO kiosko_sales (
                    user_id,
                    items,
                    total,
                    payment_method,
                    client_sale_id
                ) VALUES (
                    :user_id,
                    :items,
                    :total,
                    :payment_method,
                    :client_sale_id
                )
            ");

                $stmt->execute([
                    ':user_id' => (int) $user['id'],
                    ':items' => json_encode($items, JSON_UNESCAPED_UNICODE),
                    ':total' => $total,
                    ':payment_method' => $paymentMethod,
                    ':client_sale_id' => $clientSaleId,
                ]);

                $saleId = (int) $pdo->lastInsertId();

                $updQty = $pdo->prepare("
                UPDATE products
                SET qty = qty + :qty
                WHERE id = :id AND active = 1
            ");

                foreach ($items as $item) {
                    $productId = (int) ($item['id'] ?? 0);
                    $qty = (int) ($item['qty'] ?? 0);

                    if ($productId > 0 && $qty > 0) {
                        $updQty->execute([
                            ':qty' => $qty,
                            ':id' => $productId,
                        ]);
                    }
                }

                $pdo->commit();

                ok([
                    'id' => $saleId,
                    'paymentMethod' => $paymentMethod,
                    'paymentLabel' => payment_label($paymentMethod),
                ]);
            }
        case 'sales_history': {
                require_admin_or_kiosko($user);

                // Mostrar solamente las ventas de la caja actualmente abierta.
                // Los cierres ocultos del historial también cuentan como cierres válidos.
                $stmtLast = $pdo->query("
                SELECT COALESCE(MAX(to_sale_id), 0)
                FROM kiosko_closings
            ");

                $lastClosedSaleId = (int) $stmtLast->fetchColumn();

                $stmt = $pdo->prepare("
                SELECT id, items, total, payment_method, created_at
                FROM kiosko_sales
                WHERE id > :last_closed_sale_id
                ORDER BY id DESC
                LIMIT 100
            ");

                $stmt->execute([
                    ':last_closed_sale_id' => $lastClosedSaleId,
                ]);

                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $sales = array_map(function (array $row): array {
                    $paymentMethod = normalize_payment_method(
                        (string) ($row['payment_method'] ?? 'efectivo')
                    );

                    return [
                        'id' => (int) $row['id'],
                        'items' => json_decode((string) $row['items'], true) ?: [],
                        'total' => (int) $row['total'],
                        'payment_method' => $paymentMethod,
                        'payment_label' => payment_label($paymentMethod),
                        'created_at' => $row['created_at'],
                    ];
                }, $rows);

                ok([
                    'sales' => $sales,
                    'lastClosedSaleId' => $lastClosedSaleId,
                    'scope' => 'current_cash',
                ]);
            }
        case 'kiosko_summary': {
                require_admin_or_kiosko($user);

                $stmtLast = $pdo->query("
                SELECT COALESCE(MAX(to_sale_id), 0)
                FROM kiosko_closings
            ");

                $lastClosedSaleId = (int) $stmtLast->fetchColumn();

                $summary = build_kiosko_summary($pdo, $lastClosedSaleId);

                ok([
                    'lastClosedSaleId' => $lastClosedSaleId,
                    'summary' => $summary,
                ]);
            }

        case 'kiosko_close': {
                require_admin_or_kiosko($user);

                $pdo->beginTransaction();

                $stmtLast = $pdo->query("
                SELECT COALESCE(MAX(to_sale_id), 0)
                FROM kiosko_closings
                FOR UPDATE
            ");

                $lastClosedSaleId = (int) $stmtLast->fetchColumn();
                $summary = build_kiosko_summary($pdo, $lastClosedSaleId);

                if ((int) $summary['sales_count'] <= 0) {
                    $pdo->rollBack();
                    fail('No hay ventas nuevas para cerrar.');
                }

                // La tabla real usa las columnas `total` e `items`.
                $stmt = $pdo->prepare("
                INSERT INTO kiosko_closings (
                    user_id,
                    from_sale_id,
                    to_sale_id,
                    sales_count,
                    total,
                    efectivo_total,
                    transferencia_total,
                    tarjeta_total,
                    regalo_total,
                    items
                ) VALUES (
                    :user_id,
                    :from_sale_id,
                    :to_sale_id,
                    :sales_count,
                    :total,
                    :efectivo_total,
                    :transferencia_total,
                    :tarjeta_total,
                    :regalo_total,
                    :items
                )
            ");

                $stmt->execute([
                    ':user_id' => (int) $user['id'],
                    ':from_sale_id' => (int) $summary['from_sale_id'],
                    ':to_sale_id' => (int) $summary['to_sale_id'],
                    ':sales_count' => (int) $summary['sales_count'],
                    ':total' => (int) $summary['total_amount'],
                    ':efectivo_total' => (int) $summary['by_payment']['efectivo'],
                    ':transferencia_total' => (int) $summary['by_payment']['transferencia'],
                    ':tarjeta_total' => (int) $summary['by_payment']['tarjeta'],
                    ':regalo_total' => (int) $summary['by_payment']['regalo'],
                    ':items' => json_encode(
                        $summary,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);

                $closingId = (int) $pdo->lastInsertId();
                $pdo->commit();

                ok([
                    'id' => $closingId,
                    'summary' => $summary,
                    'message' => 'Caja cerrada correctamente.'
                ]);
            }

        case 'kiosko_closings_list': {
                require_admin($user);

                $columnCheck = $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'kiosko_closings'
                  AND COLUMN_NAME = 'deleted_at'
            ");

                if ((int) $columnCheck->fetchColumn() === 0) {
                    $pdo->exec("
                    ALTER TABLE kiosko_closings
                    ADD COLUMN deleted_at DATETIME NULL,
                    ADD INDEX idx_kiosko_closings_deleted_at (deleted_at)
                ");
                }

                $stmt = $pdo->query("
                SELECT
                    kc.id,
                    kc.user_id,
                    kc.from_sale_id,
                    kc.to_sale_id,
                    kc.sales_count,
                    kc.total,
                    kc.efectivo_total,
                    kc.transferencia_total,
                    kc.tarjeta_total,
                    kc.regalo_total,
                    kc.items,
                    kc.created_at,
                    kc.closed_at,
                    COALESCE(u.display_name, u.username, 'Usuario eliminado') AS closed_by
                FROM kiosko_closings kc
                LEFT JOIN users u ON u.id = kc.user_id
                WHERE kc.deleted_at IS NULL
                ORDER BY COALESCE(kc.closed_at, kc.created_at) DESC, kc.id DESC
                LIMIT 200
            ");

                $closings = [];
                $historySummary = [
                    'closings' => 0,
                    'sales' => 0,
                    'total' => 0,
                    'efectivo' => 0,
                    'transferencia' => 0,
                    'tarjeta' => 0,
                    'regalo' => 0,
                ];

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $decoded = json_decode((string) ($row['items'] ?? ''), true);
                    $products = [];

                    if (is_array($decoded)) {
                        if (isset($decoded['products']) && is_array($decoded['products'])) {
                            $products = $decoded['products'];
                        } elseif (array_is_list($decoded)) {
                            $products = $decoded;
                        }
                    }

                    $closing = [
                        'id' => (int) $row['id'],
                        'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                        'from_sale_id' => (int) ($row['from_sale_id'] ?? 0),
                        'to_sale_id' => (int) ($row['to_sale_id'] ?? 0),
                        'sales_count' => (int) ($row['sales_count'] ?? 0),
                        'total' => (int) ($row['total'] ?? 0),
                        'efectivo_total' => (int) ($row['efectivo_total'] ?? 0),
                        'transferencia_total' => (int) ($row['transferencia_total'] ?? 0),
                        'tarjeta_total' => (int) ($row['tarjeta_total'] ?? 0),
                        'regalo_total' => (int) ($row['regalo_total'] ?? 0),
                        'created_at' => $row['created_at'] ?? null,
                        'closed_at' => $row['closed_at'] ?? null,
                        'closed_by' => (string) ($row['closed_by'] ?? 'Administrador'),
                        'products' => $products,
                    ];

                    $closings[] = $closing;
                    $historySummary['closings']++;
                    $historySummary['sales'] += $closing['sales_count'];
                    $historySummary['total'] += $closing['total'];
                    $historySummary['efectivo'] += $closing['efectivo_total'];
                    $historySummary['transferencia'] += $closing['transferencia_total'];
                    $historySummary['tarjeta'] += $closing['tarjeta_total'];
                    $historySummary['regalo'] += $closing['regalo_total'];
                }

                ok([
                    'closings' => $closings,
                    'summary' => $historySummary,
                ]);
            }

        case 'kiosko_closing_delete': {
                require_admin($user);

                $closingId = (int) ($input['id'] ?? 0);

                if ($closingId <= 0) {
                    fail('El cierre seleccionado no es válido.', 422);
                }

                $columnCheck = $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'kiosko_closings'
                  AND COLUMN_NAME = 'deleted_at'
            ");

                if ((int) $columnCheck->fetchColumn() === 0) {
                    $pdo->exec("
                    ALTER TABLE kiosko_closings
                    ADD COLUMN deleted_at DATETIME NULL,
                    ADD INDEX idx_kiosko_closings_deleted_at (deleted_at)
                ");
                }

                $stmt = $pdo->prepare("
                UPDATE kiosko_closings
                SET deleted_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL
            ");

                $stmt->execute([
                    ':id' => $closingId,
                ]);

                if ($stmt->rowCount() === 0) {
                    fail('La caja ya fue eliminada del historial o no existe.', 404);
                }

                ok([
                    'id' => $closingId,
                    'message' => 'Caja eliminada del historial.',
                ]);
            }


            /* =========================================================
           QR DE PUERTA
           - Admin ve todos los QR desde admin.php
           - Usuario común puede generar QR solo de sus propias listas
           - No se envían mails: solo se genera token para mostrar QR
        ========================================================= */
        case 'admin_qr_people': {
                require_admin($user);

                $stmt = $pdo->query("
                SELECT 
                    dp.id,
                    dp.name,
                    dp.note,
                    dp.status,
                    dp.qr_token,
                    dp.qr_enabled,
                    dp.qr_used_at,
                    dl.name AS list_name,
                    u.display_name AS owner_name
                FROM door_people dp
                INNER JOIN door_lists dl ON dl.id = dp.list_id
                INNER JOIN users u ON u.id = dl.user_id
                ORDER BY dp.id DESC
            ");

                ok(['people' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }

        case 'qr_generate': {
                $personId = (int) ($input['personId'] ?? 0);

                if ($personId <= 0) {
                    fail('Persona inválida.');
                }

                $stmt = $pdo->prepare("
                SELECT 
                    dp.id,
                    dp.list_id,
                    dl.user_id
                FROM door_people dp
                INNER JOIN door_lists dl ON dl.id = dp.list_id
                WHERE dp.id = :id
                LIMIT 1
            ");

                $stmt->execute([
                    ':id' => $personId
                ]);

                $person = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$person) {
                    fail('Persona no encontrada.', 404);
                }

                // Admin puede generar cualquier QR.
                // Usuario común solo puede generar QR de personas en sus propias listas.
                if (($user['role'] ?? '') !== 'admin' && (int) $person['user_id'] !== (int) $user['id']) {
                    fail('No tenés permiso para generar este QR.', 403);
                }

                $token = bin2hex(random_bytes(24));

                $stmt = $pdo->prepare("
                UPDATE door_people
                SET qr_token = :token,
                    qr_enabled = 1,
                    qr_used_at = NULL
                WHERE id = :id
            ");

                $stmt->execute([
                    ':token' => $token,
                    ':id' => $personId
                ]);

                ok([
                    'token' => $token
                ]);
            }

        case 'qr_disable': {
                require_admin($user);

                $personId = (int) ($input['personId'] ?? 0);
                if ($personId <= 0) fail('Persona inválida.');

                $stmt = $pdo->prepare("
                UPDATE door_people
                SET qr_enabled = 0
                WHERE id = :id
            ");

                $stmt->execute([':id' => $personId]);

                ok();
            }
        case 'qr_check': {
                require_door_manager($user);

                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    fail('Método no permitido. El QR solo puede verificarse desde el escáner interno.', 405);
                }

                $token = trim((string) ($input['token'] ?? ''));

                if ($token === '') {
                    fail('Token vacío.');
                }

                $stmt = $pdo->prepare("
                SELECT 
                    dp.id,
                    dp.name,
                    dp.note,
                    dp.status,
                    dp.qr_enabled,
                    dp.qr_used_at,
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
                    fail('QR no encontrado.');
                }

                if ((int) $person['qr_enabled'] !== 1) {
                    fail('QR desactivado.');
                }

                if (!empty($person['qr_used_at'])) {
                    fail('QR ya utilizado.');
                }

                ok([
                    'person' => $person
                ]);
            }

        case 'qr_confirm': {
                require_door_manager($user);

                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    fail('Método no permitido. El QR solo puede confirmarse desde el escáner interno.', 405);
                }

                $token = trim((string) ($input['token'] ?? ''));

                if ($token === '') {
                    fail('Token vacío.');
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare("
                    SELECT 
                        dp.id,
                        dp.name,
                        dp.note,
                        dp.status,
                        dp.qr_enabled,
                        dp.qr_used_at,
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
                        fail('QR no encontrado.');
                    }

                    if ((int) $person['qr_enabled'] !== 1) {
                        $pdo->rollBack();
                        fail('QR desactivado.');
                    }

                    if (!empty($person['qr_used_at'])) {
                        $pdo->rollBack();
                        fail('QR ya utilizado.');
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

                    ok([
                        'person' => $person
                    ]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    fail($e->getMessage(), 500);
                }
            }

            // Endpoint default en caso de no entrar en ningún case
        default:
            fail('Acción no válida.', 404);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('Error del servidor: ' . $e->getMessage(), 500);
}
