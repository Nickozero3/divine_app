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

**Divine App** es una aplicación web para administrar eventos, boliche o fiestas desde un panel simple y responsive.

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

| Módulo          | Función                                             |
| --------------- | --------------------------------------------------- |
| 🚪 Puerta       | Crear listas, agregar personas y controlar ingresos |
| 📲 QR           | Generar y compartir QR personalizados               |
| 📷 Scanner      | Validar entradas por QR                             |
| 🛒 Kioskito     | Vender productos con carrito                        |
| 🧥 Guardarropas | Registrar prendas y marcar retiradas                |
| 👑 Admin        | Ver todo, cambiar estados y controlar listas        |
| 👤 Usuario      | Ver y administrar solo sus propias listas           |

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
* Eliminar listas y personas.

### 👤 Usuario / RRPP

Puede:

* Ver solo sus listas.
* Crear listas normales y cumpleaños.
* Agregar personas.
* Pegar listas completas.
* Buscar invitados.
* Enviar QR.

No puede cambiar estados ni ver listas ajenas.

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
Camila / camila123
Nicolas / nicolas123
Lopez / lopez123
Publica / publica123
Candelaria / candelaria123
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
src/config/conexion.php    Conexión MySQL
db/init.sql                Tablas y productos iniciales
```

---

## ⚠️ Notas importantes

* La app **no usa localStorage**.
* Todo se guarda en **MySQL**.
* El scanner QR necesita **HTTPS o localhost** para usar la cámara.
* El live reload se pausa al escribir o abrir modales.
* Los estados de ingreso solo los cambia un admin.
* Los QR se guardan en `door_people`.

---

<div align="center">

### 💜 Divine App

Sistema simple, rápido y responsive para controlar eventos.

</div>
