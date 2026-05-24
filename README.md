# Divine App corregida sin localStorage

Esta versión guarda todo en MySQL mediante PHP:

- Login con sesión PHP.
- Usuarios: Camila, Nicolas y Lopez como admin; Publica como usuario.
- Kioskito solo para admin.
- Puerta para admin y usuario.
- Publica ve únicamente sus listas.
- Publica puede crear su lista principal automática con su nombre.
- Publica puede crear varias listas de cumpleaños automáticas: `Publica Cumpleaños 1`, `Publica Cumpleaños 2`, etc.
- Publica puede agregar personas una por una o pegando una lista completa.
- Publica puede buscar nombres dentro de sus listas.
- Solo admin puede cambiar el estado `No vino / Entró / Se fue`.
- Admin puede ver todas las listas y buscar por lista, usuario o persona.
- La creación de listas ya no permite escribir nombres: se toma automáticamente el nombre del usuario logueado.
- Se guardan logs de inicio de sesión y de borrado de listas en la tabla `app_logs`.
- Productos, cantidades, listas, personas y estados se guardan en la base de datos.
- No se usa localStorage.


## Permisos de Puerta

### Admin

- Ve todas las listas: normales y cumpleaños.
- Puede buscar por lista, usuario y nombre de persona.
- Puede cambiar el estado de cada persona: `No vino`, `Entró`, `Se fue`.
- Puede agregar personas, pegar listas, eliminar registros y borrar listas.
- Si crea una lista, se nombra automáticamente con su propio usuario.

### Publica / usuario

- Ve únicamente sus propias listas.
- Su lista normal se crea automáticamente con su nombre, por ejemplo: `Publica` o `Anna`.
- Si crea listas de cumpleaños, se nombran automáticamente como `Publica Cumpleaños 1`, `Publica Cumpleaños 2`, o `Anna Cumpleaños 1`, `Anna Cumpleaños 2`, etc.
- Puede agregar nombres manualmente o pegar una lista completa.
- Puede buscar nombres dentro de sus propias listas.
- No puede cambiar si una persona vino o no; el estado aparece solo para lectura.

## Levantar con Docker

Desde la carpeta raíz del proyecto:

```powershell
docker compose up --build
```

Abrí:

- App: http://localhost:8080
- phpMyAdmin: http://localhost:8081

Datos phpMyAdmin:

- Servidor: mysql
- Usuario: usuario
- Contraseña: password
- Base: divine_db

Luego abrí una vez:

```text
http://localhost:8080/setup_user.php
```

Después entrá al login:

```text
http://localhost:8080/login.php
```

Usuarios:

- Camila / camila123
- Nicolas / nicolas123
- Lopez / lopez123
- Publica / publica123

Cuando confirmes que los usuarios existen, borrá `src/setup_user.php`.

## Usar con XAMPP

1. Copiá la carpeta `src` dentro de `htdocs`, por ejemplo:

```text
C:\xampp\htdocs\divine
```

2. En phpMyAdmin creá una base llamada:

```text
divine_db
```

3. Importá el archivo:

```text
db/init.sql
```

4. Si usás XAMPP normal, en `src/config/conexion.php` ya queda por defecto:

```php
DB_HOST = localhost
DB_NAME = divine_db
DB_USER = root
DB_PASSWORD = vacío
DB_PORT = 3306
```

5. Abrí una vez:

```text
http://localhost/divine/setup_user.php
```

6. Después entrá a:

```text
http://localhost/divine/login.php
```

## Archivos importantes

- `src/api.php`: endpoints que reemplazan localStorage.
- `src/script.js`: frontend usando fetch hacia MySQL/PHP.
- `src/config/conexion.php`: conexión corregida.
- `src/config/app_logs.php`: crea y guarda logs de login/listas.
- `db/init.sql`: tablas y productos iniciales.
- `src/setup_user.php`: crea los usuarios iniciales.
