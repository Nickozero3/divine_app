# Divine App — Consolidación

Build consolidado: 2026-09-22.

## Incluido

- Puerta/listas con estados `no_vino` → `entro` → `se_fue`.
- QR de invitado con generación, verificación, confirmación transaccional y bloqueo de reutilización.
- Kioskito y Kioskito VIP con carrito, historial, pagos y recargo del 10% para tarjeta.
- Recalculo de precios y total de venta en el servidor: el navegador ya no puede imponer el precio final.
- `client_sale_id` para impedir duplicados al reintentar ventas.
- Caja Kiosko y Caja VIP independientes, con cierres e historial.
- Guardarropas con búsqueda, alta, entrega y eliminación controlada.
- Stock del contenedor con sectores, mínimo, máximo y movimientos.
- Roles definitivos: `admin`, `usuario`, `puerta`, `cajera`, `kioskito`. `kiosko` queda solamente como rol legado que se migra automáticamente a `kioskito`.
- Auditoría mediante `app_logs`.
- `sync_operations` para idempotencia de operaciones offline de Puerta.
- Cola local para ventas y cambios de estado cuando se pierde la conexión.
- Caché local de productos/listas y cartel de conectividad.
- PWA mínima: cachea recursos estáticos y el `script.js`, pero no cachea páginas PHP autenticadas ni endpoints API.
- `setup.php` y `health.php` actualizados para el esquema consolidado.

## Seguridad añadida

- Permisos de Kioskito/Cajera/Admin validados en backend.
- Guardarropas protegido también en el endpoint de listado, no solamente en la interfaz.
- Venta validada contra productos activos y zona correcta en MySQL.
- Recargo de tarjeta calculado nuevamente en servidor.
- Estado de puerta offline sincronizado con `operation_id` + `expectedStatus` para detectar conflictos.
- Cierres e historial siguen restringidos al administrador donde corresponde.

## Limitaciones conocidas

- No se pudo ejecutar una prueba de integración contra MySQL/MariaDB real en este entorno: no están disponibles el cliente/servidor ni Docker.
- Se ejecutaron lint de los 24 PHP y comprobación de sintaxis de los 6 JS del proyecto.
- El modo offline está pensado para microcortes con la pantalla de Kioskito/Puerta ya cargada. No se cachean HTML autenticados deliberadamente.
- El lector QR depende actualmente de la librería externa de `html5-qrcode`; sin conexión, el escáner puede requerir que esa librería ya esté disponible en la caché del navegador.
- `products.qty` continúa siendo el contador usado por el Kioskito; el inventario físico real se administra en `container_stock_items` y sus movimientos.
