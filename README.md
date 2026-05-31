<div align="center">

# ✨ Divine App ✨

<img src="https://readme-typing-svg.demolab.com?font=DM+Sans&weight=700&size=24&pause=1000&color=F0D48D&center=true&vCenter=true&width=600&lines=Sistema+de+gesti%C3%B3n+para+eventos;Puerta+%C2%B7+Kioskito+%C2%B7+QR+%C2%B7+Guardarropas;Hecho+con+PHP+%2B+MySQL" alt="Typing SVG" />

<br>

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?style=for-the-badge\&logo=php\&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge\&logo=mysql\&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge\&logo=javascript\&logoColor=111)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge\&logo=docker\&logoColor=white)

</div>

---

## 🚀 ¿Qué es Divine App?

**Divine App** es una aplicación web para administrar eventos, boliches o fiestas desde un panel simple, rápido y responsive.

Permite gestionar:

* 🚪 **Puerta:** listas, invitados y estados de ingreso.
* 📲 **QR:** generación, envío y validación de entradas.
* 📷 **Scanner:** lectura de QR desde cámara.
* 🛒 **Kioskito:** ventas tipo carrito.
* 🧥 **Guardarropas:** registro y entrega de prendas.
* 📊 **Admin:** estadísticas y control general.
* 🔴 **Live reload:** actualización automática sin recargar.

---

## ✨ Funciones principales

| Módulo          | Función                                              |
| --------------- | ---------------------------------------------------- |
| 🚪 Puerta       | Ver listas, buscar invitados y controlar ingresos    |
| 📲 QR           | Generar y compartir QR personalizados                |
| 📷 Scanner      | Validar entradas por QR                              |
| 🛒 Kioskito     | Vender productos con carrito                         |
| 🧥 Guardarropas | Registrar prendas y marcar retiradas                 |
| 👑 Admin        | Control total de listas, usuarios y estadísticas     |
| 🚪 Puerta rol   | Ver todas las listas, usar scanner y cambiar estados |
| 👤 Usuario/RRPP | Administrar solo sus propias listas                  |

---

## 🔐 Roles

### 👑 Admin

Puede:

* Ver todas las listas.
* Buscar por usuario, lista o persona.
* Cambiar estados: `No vino`, `Entró`, `Se fue`.
* Usar Kioskito.
* Usar Scanner.
* Acceder al Panel Admin.
* Enviar QR.
* Crear usuarios.
* Modificar roles.
* Eliminar listas y personas.
* Administrar productos y ventas.

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

---

## 🧩 Tecnologías

```txt
Frontend: HTML + CSS + JavaScript
Backend: PHP
Base de datos: MySQL
Sesiones: PHP Sessions
Deploy local: Docker / XAMPP
```

---

## 🐳 Levantar con Docker

```bash
docker compose up --build
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
src/index.php              Pantalla principal
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
```

---

## 🗄️ Tablas principales

```txt
users          Usuarios y roles
products       Productos del Kioskito
kiosko_sales   Historial de ventas
door_lists     Listas de puerta
door_people    Personas dentro de cada lista
guardarropas   Prendas registradas
app_logs        Logs internos de acciones
```

---

## ⚠️ Notas importantes

* La app **no usa localStorage**.
* Todo se guarda en **MySQL**.
* El scanner QR necesita **HTTPS o localhost** para usar la cámara.
* El live reload se pausa al escribir, pegar listas o abrir modales.
* Los estados de ingreso los pueden cambiar los roles `admin` y `puerta`.
* El rol `puerta` ve todas las listas, pero no administra productos ni usuarios.
* Los QR se guardan en la tabla `door_people`.
* Si se modifica el rol desde la base de datos, el campo `users.role` debe aceptar: `admin`, `usuario` y `puerta`.

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

<div align="center">

### 💜 Divine App

Sistema simple, rápido y responsive para controlar eventos.

</div>
