<?php
session_start();
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/app_logs.php';

header('Content-Type: application/json; charset=utf-8');

function requireAdmin(): void
{
    if (($_SESSION["user"]["role"] ?? "") !== "admin") {
        jsonResponse(false, ["error" => "Solo administradores"], 403);
    }
}

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

function require_login(): array
{
    global $pdo;

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

function person_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'note' => $row['note'],
        'status' => $row['status'],
    ];
}

$user = require_login();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = read_input();

try {
    switch ($action) {
        case 'me': {
            ok(['user' => $user]);
        }

        case 'products_list': {
            require_admin($user);
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

        case 'door_lists': {
            $isAdmin = ($user['role'] ?? '') === 'admin';

            if ($isAdmin) {
                $stmt = $pdo->query('SELECT dl.*, u.display_name AS owner_name FROM door_lists dl INNER JOIN users u ON u.id = dl.user_id ORDER BY dl.created_at DESC, dl.id DESC');
                $lists = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare('SELECT dl.*, u.display_name AS owner_name FROM door_lists dl INNER JOIN users u ON u.id = dl.user_id WHERE dl.user_id = :user_id ORDER BY dl.created_at DESC, dl.id DESC');
                $stmt->execute([':user_id' => (int) $user['id']]);
                $lists = $stmt->fetchAll();
            }

            $ids = array_map(fn($l) => (int) $l['id'], $lists);
            $peopleByList = [];

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmtPeople = $pdo->prepare("SELECT * FROM door_people WHERE list_id IN ($placeholders) ORDER BY id ASC");
                $stmtPeople->execute($ids);
                foreach ($stmtPeople->fetchAll() as $person) {
                    $peopleByList[(int) $person['list_id']][] = person_row($person);
                }
            }

            $result = array_map(function ($list) use ($peopleByList) {
                $id = (int) $list['id'];
                return [
                    'id' => $id,
                    'userId' => (int) $list['user_id'],
                    'ownerName' => $list['owner_name'] ?? '',
                    'name' => $list['name'],
                    'isBirthday' => (bool) $list['is_birthday'],
                    'pricePerPerson' => (int) $list['price_per_person'],
                    'people' => $peopleByList[$id] ?? [],
                ];
            }, $lists);

            ok(['lists' => $result]);
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
            if ($listId <= 0) fail('Lista inválida.');
            $list = current_user_can_access_list($pdo, $listId, $user);

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

            $stmt = $pdo->prepare('DELETE FROM door_lists WHERE id = :id');
            $stmt->execute([':id' => $listId]);
            ok();
        }

        case 'person_add': {
            $listId = (int) ($input['listId'] ?? 0);
            $name = trim((string) ($input['name'] ?? ''));
            $note = trim((string) ($input['note'] ?? ''));

            if ($listId <= 0) fail('Lista inválida.');
            current_user_can_access_list($pdo, $listId, $user);
            if ($name === '' || $note === '') fail('Completá nombre y dato/número.');

            $stmt = $pdo->prepare('SELECT name, note FROM door_people WHERE list_id = :list_id');
            $stmt->execute([':list_id' => $listId]);
            foreach ($stmt->fetchAll() as $existing) {
                if (normalize_text($existing['name']) === normalize_text($name) && trim((string) $existing['note']) === $note) {
                    fail('Esa persona ya está cargada en esta lista.');
                }
            }

            $stmt = $pdo->prepare('INSERT INTO door_people (list_id, name, note, status) VALUES (:list_id, :name, :note, "no_vino")');
            $stmt->execute([':list_id' => $listId, ':name' => $name, ':note' => $note]);
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
            require_admin($user);

            $listId = (int) ($input['listId'] ?? 0);
            $personId = (int) ($input['personId'] ?? 0);
            if ($listId <= 0 || $personId <= 0) fail('Datos inválidos.');
            current_user_can_access_list($pdo, $listId, $user);

            $stmt = $pdo->prepare('SELECT status FROM door_people WHERE id = :person_id AND list_id = :list_id LIMIT 1');
            $stmt->execute([':person_id' => $personId, ':list_id' => $listId]);
            $person = $stmt->fetch();
            if (!$person) fail('Persona no encontrada.', 404);

            $current = $person['status'];
            $next = match ($current) {
                'no_vino' => 'entro',
                'entro' => 'se_fue',
                default => 'no_vino',
            };

            $stmt = $pdo->prepare('UPDATE door_people SET status = :status WHERE id = :person_id AND list_id = :list_id');
            $stmt->execute([':status' => $next, ':person_id' => $personId, ':list_id' => $listId]);
            ok(['status' => $next]);
        }

        case 'person_delete': {
            $listId = (int) ($input['listId'] ?? 0);
            $personId = (int) ($input['personId'] ?? 0);
            if ($listId <= 0 || $personId <= 0) fail('Datos inválidos.');
            current_user_can_access_list($pdo, $listId, $user);

            $stmt = $pdo->prepare('DELETE FROM door_people WHERE id = :person_id AND list_id = :list_id');
            $stmt->execute([':person_id' => $personId, ':list_id' => $listId]);
            ok();
        }

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

            if (!in_array($role, ['admin', 'usuario'], true)) {
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

            if (!in_array($role, ['admin', 'usuario'], true)) {
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
            require_admin($user);

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
            require_admin($user);

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
            require_admin($user);

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
        case 'sale_register': {
            require_admin($user);

            $items = $input['items'] ?? [];
            $total = max(0, (int) ($input['total'] ?? 0));

            if (!is_array($items) || count($items) === 0) {
                fail('No hay productos en la venta.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO kiosko_sales (user_id, items, total)
                VALUES (:user_id, :items, :total)
            ");

            $stmt->execute([
                ':user_id' => (int) $user['id'],
                ':items'   => json_encode($items, JSON_UNESCAPED_UNICODE),
                ':total'   => $total,
            ]);

            $updQty = $pdo->prepare("
                UPDATE products
                SET qty = qty + :qty
                WHERE id = :id AND active = 1
            ");

            foreach ($items as $item) {
                $productId = (int) ($item['id'] ?? 0);
                $qty       = (int) ($item['qty'] ?? 0);

                if ($productId > 0 && $qty > 0) {
                    $updQty->execute([
                        ':qty' => $qty,
                        ':id'  => $productId,
                    ]);
                }
            }

            ok([
                'id' => (int) $pdo->lastInsertId()
            ]);
        }
        case 'sales_history': {
            require_admin($user);

            $stmt = $pdo->query("
                SELECT id, items, total, created_at
                FROM kiosko_sales
                ORDER BY id DESC
                LIMIT 100
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sales = array_map(function ($row) {
                return [
                    'id'         => (int) $row['id'],
                    'items'      => json_decode($row['items'], true) ?: [],
                    'total'      => (int) $row['total'],
                    'created_at' => $row['created_at'],
                ];
            }, $rows);

            ok(['sales' => $sales]);
        }

        // Administración de QR para puerta
        case 'admin_qr_people': {
            require_admin($user);

            $stmt = $pdo->query("
                SELECT 
                    dp.id,
                    dp.name,
                    dp.note,
                    dp.status,
                    dp.email,
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

            ok(['people' => $stmt->fetchAll()]);
        }

        case 'qr_generate': {
            require_admin($user);

            $personId = (int) ($input['personId'] ?? 0);
            if ($personId <= 0) fail('Persona inválida.');

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

            ok(['token' => $token]);
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
            require_admin($user);

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

            $person = $stmt->fetch();

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
            require_admin($user);

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

            $person = $stmt->fetch();

            if (!$person) {
                fail('QR no encontrado.');
            }

            if ((int) $person['qr_enabled'] !== 1) {
                fail('QR desactivado.');
            }

            if (!empty($person['qr_used_at'])) {
                fail('QR ya utilizado.');
            }

            $stmt = $pdo->prepare("
                UPDATE door_people
                SET status = 'entro',
                    qr_used_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => (int) $person['id']
            ]);

            $person['status'] = 'entro';
            $person['qr_used_at'] = date('Y-m-d H:i:s');

            ok([
                'person' => $person
            ]);
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