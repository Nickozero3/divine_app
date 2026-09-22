# Divine — Optimización de consumo en Railway

## Cambios
- Polling principal reducido a 10 s.
- Puerta: como máximo una consulta cada 5 s mientras está activa.
- Resumen de caja: como máximo cada 30 s.
- Guardarropas: como máximo cada 30 s.
- Productos: conserva el caché existente de 30 s; el polling no fuerza recarga.
- Cola offline: sincronización cada 30 s y se pausa con la pestaña oculta.
- Se evitan consultas duplicadas durante un mismo ciclo.
- Un HTTP 429 activa backoff de 15–60 s y no vuelve a golpear Railway durante ese período.
- Un 429 no reemplaza la interfaz por “Se rompió esta sección”.
- Al volver a la pestaña se sincroniza Puerta, sin forzar todos los módulos.
- Se eliminó la solicitud automática de `manifest.json`, que estaba devolviendo 429 en Railway.

## Objetivo
Reducir drásticamente las solicitudes repetitivas y mantener la interfaz con los últimos datos válidos cuando Railway está temporalmente limitado.
