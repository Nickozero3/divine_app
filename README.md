# ✨ Divine App ✨



\

---

## 🚀 ¿Qué es Divine App?

**Divine App** es una aplicación web para administrar eventos, boliches, fiestas o locales desde un panel simple, rápido y responsive.

Permite gestionar en tiempo real:

* 🚪 **Puerta:** listas, invitados y estados de ingreso.
* 📲 **QR:** generación, envío y validación de entradas.
* 📷 **Scanner:** lectura de QR desde cámara.
* 🛒 **Kioskito:** ventas tipo carrito con métodos de pago.
* 🧾 **Cierre de caja:** resumen y cierre por rango de ventas.
* 🧥 **Guardarropas:** registro, cobro y retiro de prendas.
* 👑 **Admin:** estadísticas, usuarios, roles, productos y control general.
* 🔴 **Live:** actualización automática sin recargar.

---

## ✨ Funciones principales

| Módulo              | Función                                                     |
| ------------------- | ----------------------------------------------------------- |
| 🚪 Puerta           | Ver listas, buscar invitados y controlar ingresos           |
| 📲 QR               | Generar y compartir QR personalizados                       |
| 📷 Scanner          | Validar entradas por QR desde cámara                        |
| 🛒 Kioskito         | Vender productos con carrito                                |
| 💵 Métodos de pago  | Efectivo, transferencia, tarjeta y regalo                   |
| 🧾 Cierre de caja   | Guardar totales por caja cerrada                            |
| 🧥 Guardarropas     | Registrar prendas, cobrar y marcar retiradas                |
| 👑 Admin            | Control total de listas, usuarios, productos y estadísticas |
| 🚪 Puerta rol       | Ver todas las listas, usar scanner y cambiar estados        |
| 👤 Usuario/RRPP     | Administrar solo sus propias listas                         |
| ⚙️ Variables `.env` | Cambiar nombre de app, precio de ropero y datos generales   |

---

## 🔐 Roles

### 👑 Admin

Puede:

* Ver todas las listas.
* Buscar por usuario, lista o persona.
* Cambiar estados de ingreso: `No vino`, `Entró`, `Se fue`.
* Usar Kioskito.
* Usar Scanner.
* Acceder al Panel Admin.
* Enviar QR.
* Crear usuarios.
* Modificar roles.
* Eliminar listas y personas.
* Administrar productos.
* Ver ventas.
* Cerrar caja.
* Usar Guardarropas.

---

### 🚪 Puerta

Puede:

* Ver todas las listas.
* Buscar por usuario, lista o persona.
* Cambiar estados: `No vino`, `Entró`, `Se fue`.
* Usar Scanner.
* Confirmar entradas por QR.

No puede:

* Acceder al Panel Admin.
* Usar Kioskito.
* Crear usuarios.
* Modificar productos.
* Eliminar listas.
* Eliminar personas.
* Crear o pegar listas.

---

### 👤 Usuario / RRPP

Puede:

* Ver solo sus propias listas.
* Crear listas normales.
* Crear listas de cumpleaños.
* Agregar personas.
* Pegar listas completas.
* Buscar invitados dentro de sus listas.
* Enviar QR.

No puede:

* Ver listas ajenas.
* Cambiar estados de ingreso.
* Usar Scanner.
* Usar Kioskito.
* Acceder al Panel Admin.
* Cerrar caja.

---

## 🧩 Tecnologías

```txt
Frontend: HTML + CSS + JavaScript
Backend: PHP
Base de datos: MySQL
Sesiones: PHP Sessions
Servidor local: Apache
Deploy local: Docker / XAMPP
```

---

## ⚙️ Variables `.env`

La app puede tomar configuraciones desde el archivo `.env`.

Ejemplo:

```env
NAME_APP=Divine
APP_VERSION=1.0.0
APP_AUTHOR=Nicko

MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=divine_db
MYSQL_USER=usuario
MYSQL_PASSWORD=password

DB_HOST=mysql
DB_PORT=3306
DB_NAME=divine_db
DB_USER=usuario
DB_PASSWORD=password
```

Variables principales:

| Variable                        | Uso                          |
| ------------------------------- | ---------------------------- |
| `NAME_APP`                      | Nombre visible de la app     |
| `PRECIO_ROPERO`                 | Precio base del guardarropas |
| `APP_VERSION`                   | Versión visible o interna    |
| `APP_AUTHOR`                    | Autor de la app              |
| `DB_HOST` / `MYSQLHOST`         | Host de MySQL                |
| `DB_NAME` / `MYSQLDATABASE`     | Nombre de la base            |
| `DB_USER` / `MYSQLUSER`         | Usuario de MySQL             |
| `DB_PASSWORD` / `MYSQLPASSWORD` | Contraseña de MySQL          |

En PHP se usa así:

```php
<?= APP_NAME ?>
```

Ejemplo:

```php
<title><?= APP_NAME ?></title>
```

---

## 🐳 Levantar con Docker

```bash
docker compose up -d --build
```

Abrir la app:

```txt
http://localhost:8080
```

phpMyAdmin:

```txt
http://localhost:8081
```

Datos de phpMyAdmin:

```txt
Servidor: mysql
Usuario: usuario
Contraseña: password
Base: divine_db
```

---

## 🐳 Docker compose recomendado

```yaml
services:
  web:
    build: .
    container_name: divine_web
    ports:
      - "8080:80"
    volumes:
      - ./src:/var/www/html
      - ./db:/var/www/db
      - ./.env:/var/www/.env:ro
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
    restart: always

  mysql:
    image: mysql:8.0
    container_name: divine_mysql
    environment:
      MYSQL_ROOT_PASSWORD: 12345678
      MYSQL_DATABASE: divine_db
      MYSQL_USER: usuario
      MYSQL_PASSWORD: password
    ports:
      - "3307:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./db/init.sql:/docker-entrypoint-initdb.d/init.sql
    restart: always

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: divine_phpmyadmin
    ports:
      - "8081:80"
    environment:
      PMA_HOST: mysql
      PMA_PORT: 3306
    depends_on:
      - mysql
    restart: always

volumes:
  mysql_data:
```

---

## 🐳 Dockerfile

```dockerfile
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mysql \
    php-cli \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    curl \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

RUN rm -f /var/www/html/index.html

COPY db/ /var/www/db/
COPY src/ /var/www/html/

RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/mods-enabled/dir.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]
```

---

## 🔄 Cambiar `.env` en desarrollo

Si el `.env` está montado como volumen:

```yaml
- ./.env:/var/www/.env:ro
```

se puede cambiar, guardar y refrescar el navegador.

Ejemplo:

```env
NAME_APP=Lopez
```

Cambiar a:

```env
NAME_APP=Cubik
```

Luego refrescar la web.

---

## 🧪 Crear usuarios iniciales

Abrir una vez:

```txt
http://localhost:8080/setup.php
```

Luego entrar al login:

```txt
http://localhost:8080/login.php
```

Usuarios disponibles:

```txt
Lopez      / lopez123       -> admin
Nicolas    / nicolas123     -> admin
Camila     / camila123      -> puerta
Publica    / publica123     -> usuario
Candelaria / candelaria123  -> usuario
```

Después de crear los usuarios, borrar:

```txt
src/setup.php
```

---

## 🖥️ Usar con XAMPP

Copiar `src` dentro de:

```txt
C:\xampp\htdocs\divine
```

Crear la base:

```txt
divine_db
```

Importar:

```txt
db/init.sql
```

Abrir:

```txt
http://localhost/divine/login.php
```

---

## 📁 Archivos importantes

```txt
src/index.php              Pantalla principal / menú
src/login.php              Inicio de sesión
src/logout.php             Cierre de sesión
src/const.php              Constantes y lectura de .env
src/api.php                Endpoints PHP
src/script.js              Lógica frontend
src/styles.css             Estilos responsive
src/admin.php              Panel Admin
src/scanner.php            Scanner QR
src/qr.php                 Validación QR
src/setup.php              Creación inicial de usuarios
src/config/conexion.php    Conexión MySQL
src/config/app_logs.php    Registro de acciones internas
db/init.sql                Tablas y productos iniciales
.env                       Variables de entorno
Dockerfile                 Imagen del servidor web
docker-compose.yml         Servicios Docker
```

---

## 🗄️ Tablas principales

```txt
users             Usuarios y roles
products          Productos del Kioskito
kiosko_sales      Historial de ventas
kiosko_closings   Cierres de caja
door_lists        Listas de puerta
door_people       Personas dentro de cada lista y QR
guardarropas      Prendas registradas
app_logs          Logs internos de acciones
```

---

## 🛒 Kioskito

El módulo Kioskito permite:

* Agregar productos al carrito.
* Sumar y restar cantidades.
* Confirmar ventas.
* Registrar método de pago.
* Ver historial.
* Ver resumen de caja.
* Cerrar caja.
* Evitar ventas duplicadas con `client_sale_id`.

Métodos de pago disponibles:

```txt
efectivo
transferencia
tarjeta
regalo
```

La tabla `kiosko_sales` guarda:

```txt
id
client_sale_id
user_id
items
total
payment_method
created_at
```

---

## 🧾 Cierre de caja

La app usa la tabla `kiosko_closings` para guardar cierres de caja.

Campos principales:

```txt
id
user_id
from_sale_id
to_sale_id
total
efectivo_total
transferencia_total
tarjeta_total
regalo_total
sales_count
items
note
created_at
closed_at
```

El cierre permite saber qué ventas quedaron incluidas entre:

```txt
from_sale_id
to_sale_id
```

Esto evita volver a cerrar ventas ya cerradas.

---

## 🚪 Puerta

El módulo Puerta permite:

* Crear listas normales.
* Crear listas de cumpleaños.
* Agregar personas.
* Pegar listas completas.
* Buscar personas.
* Controlar estados de ingreso.
* Enviar QR.
* Ver estadísticas por lista.

Estados disponibles:

```txt
no_vino
entro
se_fue
```

Precios sugeridos:

```txt
Lista normal: $500
Lista cumpleaños: $1000
```

---

## 📲 QR y Scanner

Cada persona puede tener un QR único mediante `qr_token`.

La tabla `door_people` guarda:

```txt
qr_token
qr_enabled
qr_used_at
```

El Scanner permite:

* Leer QR desde cámara.
* Validar si el QR existe.
* Confirmar entrada.
* Evitar QR inválidos.

Importante:

```txt
El scanner necesita HTTPS o localhost para usar la cámara.
```

---

## 🧥 Guardarropas

El módulo Guardarropas permite:

* Crear número de prenda.
* Guardar nombre.
* Guardar DNI opcional.
* Guardar teléfono opcional.
* Marcar como retirado.
* Calcular ingresos.

Estado de prenda:

```txt
pendiente
retirado
```

Precio por defecto:

```txt
$2000
```

Puede configurarse desde:

```env
PRECIO_ROPERO=2000
```

---

## 📊 Admin

El Panel Admin permite:

* Ver estadísticas.
* Ver usuarios.
* Crear usuarios.
* Modificar roles.
* Controlar listas.
* Controlar QR.
* Ver actividad general.
* Auditar acciones mediante logs.

---

## 🧾 Logs internos

La tabla `app_logs` guarda acciones importantes:

```txt
ventas
cambios de estado
eliminaciones
errores
acciones administrativas
```

Sirve para auditoría y control interno.

---

## 🛠️ Roles permitidos en la base de datos

El campo `role` de la tabla `users` debe aceptar:

```sql
ENUM('admin', 'usuario', 'puerta')
```

Ejemplo:

```sql
ALTER TABLE users
MODIFY role ENUM('admin', 'usuario', 'puerta') NOT NULL DEFAULT 'usuario';
```

Para convertir un usuario existente al rol puerta:

```sql
UPDATE users
SET role = 'puerta'
WHERE username = 'camila';
```

---

## 🛠️ Migraciones útiles

Si la base ya existe, `init.sql` no siempre se vuelve a ejecutar automáticamente porque MySQL conserva el volumen.

Para agregar `client_sale_id` a ventas:

```sql
ALTER TABLE kiosko_sales
ADD COLUMN client_sale_id VARCHAR(80) NULL AFTER id;

ALTER TABLE kiosko_sales
ADD UNIQUE KEY uq_kiosko_sales_client_sale_id (client_sale_id);
```

Para agregar métodos de pago:

```sql
ALTER TABLE kiosko_sales
MODIFY payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'regalo') NOT NULL DEFAULT 'efectivo';
```

Para crear cierre de caja:

```sql
CREATE TABLE IF NOT EXISTS kiosko_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NULL,

    from_sale_id INT NULL,
    to_sale_id INT NULL,

    total INT NOT NULL DEFAULT 0,
    efectivo_total INT NOT NULL DEFAULT 0,
    transferencia_total INT NOT NULL DEFAULT 0,
    tarjeta_total INT NOT NULL DEFAULT 0,
    regalo_total INT NOT NULL DEFAULT 0,

    sales_count INT NOT NULL DEFAULT 0,

    items LONGTEXT NULL,
    note VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_kiosko_closings_user_id (user_id),
    INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id),
    INDEX idx_kiosko_closings_created_at (created_at),

    CONSTRAINT fk_kiosko_closings_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Si ya existe `kiosko_closings` pero faltan columnas:

```sql
ALTER TABLE kiosko_closings
ADD COLUMN from_sale_id INT NULL AFTER user_id;

ALTER TABLE kiosko_closings
ADD COLUMN to_sale_id INT NULL AFTER from_sale_id;

ALTER TABLE kiosko_closings
ADD INDEX idx_kiosko_closings_sale_range (from_sale_id, to_sale_id);
```

Para cambiar `cortesia_total` a `regalo_total`:

```sql
ALTER TABLE kiosko_closings
CHANGE cortesia_total regalo_total INT NOT NULL DEFAULT 0;
```

---

## ⚠️ Notas importantes

* La app guarda la información en **MySQL**.
* El scanner QR necesita **HTTPS o localhost** para usar la cámara.
* El live reload se pausa al escribir, pegar listas o abrir modales.
* Los estados de ingreso los pueden cambiar los roles `admin` y `puerta`.
* El rol `puerta` ve todas las listas, pero no administra productos ni usuarios.
* Los QR se guardan en la tabla `door_people`.
* Las ventas se guardan en `kiosko_sales`.
* Los cierres de caja se guardan en `kiosko_closings`.
* Si se usa Docker con volumen de MySQL, modificar `init.sql` no actualiza una base ya creada.
* Para aplicar cambios en una base existente hay que ejecutar las migraciones manualmente en phpMyAdmin.
* Si se cambia el `.env` montado como volumen, alcanza con guardar y refrescar.
* Si el `.env` se copia dentro de la imagen Docker, hay que reconstruir con `docker compose up -d --build`.

---

## 🧯 Errores comunes

### Unknown column `client_sale_id`

Falta la columna en `kiosko_sales`.

Solución:

```sql
ALTER TABLE kiosko_sales
ADD COLUMN client_sale_id VARCHAR(80) NULL AFTER id;
```

---

### Table `kiosko_closings` doesn't exist

Falta la tabla de cierres de caja.

Solución:

```sql
CREATE TABLE IF NOT EXISTS kiosko_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    from_sale_id INT NULL,
    to_sale_id INT NULL,
    total INT NOT NULL DEFAULT 0,
    efectivo_total INT NOT NULL DEFAULT 0,
    transferencia_total INT NOT NULL DEFAULT 0,
    tarjeta_total INT NOT NULL DEFAULT 0,
    regalo_total INT NOT NULL DEFAULT 0,
    sales_count INT NOT NULL DEFAULT 0,
    items LONGTEXT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

### Unknown column `to_sale_id`

Faltan columnas de rango de cierre.

Solución:

```sql
ALTER TABLE kiosko_closings
ADD COLUMN from_sale_id INT NULL AFTER user_id;

ALTER TABLE kiosko_closings
ADD COLUMN to_sale_id INT NULL AFTER from_sale_id;
```

---

### `APP_NAME` no cambia desde `.env`

Revisar que exista:

```txt
/var/www/.env
```

y que en `docker-compose.yml` esté montado:

```yaml
- ./.env:/var/www/.env:ro
```

También revisar que `.dockerignore` no ignore el archivo:

```txt
.env
```

---

## 🧼 Comandos útiles

Levantar:

```bash
docker compose up -d --build
```

Apagar:

```bash
docker compose down
```

Reconstruir sin caché:

```bash
docker compose build --no-cache
docker compose up -d
```

Ver contenedores:

```bash
docker ps
```

Entrar al contenedor web:

```bash
docker exec -it divine_web bash
```

Entrar a MySQL:

```bash
docker exec -it divine_mysql mysql -u usuario -p divine_db
```

---

### 💜 Divine App

Sistema simple, rápido y responsive para controlar eventos, puerta, ventas, QR y caja.

**Hecho con PHP + MySQL + JavaScript + Docker**
