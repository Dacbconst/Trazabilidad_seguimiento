# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two user types, used about equally:

- **Promotores / mercaderistas en campo**: registran sus actividades del día (visibilidad, mantenimiento, auditoría, etc.) desde el punto de venta, con evidencia fotográfica.
- **Supervisores / admins**: revisan el historial consolidado de actividades y configuran qué tipos de actividad existen (crear, desactivar, eliminar), reutilizando la lógica/formulario de una actividad existente como base.

## Product Purpose

Digitalizar el reporte de trabajo de campo (visibilidad de marca, mantenimiento de equipos, auditorías de exhibición) que antes se hacía de forma manual, dejando un historial estructurado por día con evidencia fotográfica y checklist de pasos verificados.

## Positioning

Lo usa Lucky (agencia/proveedor tercero) para documentar y justificar ante Epson el trabajo de campo realizado en su representación. El diferenciador frente a un log de actividades genérico es que el admin puede definir nuevos tipos de actividad (nombre, formulario, cálculo y formato de fotos) reutilizando la lógica de una actividad ya existente, sin tocar código — la taxonomía de actividades no es fija, es configurable.

## Operating Context

- Los promotores registran actividades desde el punto de venta (uso mobile-first: header y drawer de navegación ya construidos para pantallas chicas).
- Los admins usan un "constructor" para crear nuevas actividades (nombre + lógica a replicar) y para activar/desactivar/eliminar actividades existentes.
- El historial agrupa registros por día, con metadata (punto de venta, categoría, nivel de impacto, quién registró), checklist de pasos y evidencia fotográfica, filtrable por tipo de actividad y rango de fecha.
- Backend PHP con sesión (login por usuario/admin) y MySQL en Azure (`luckyec_epson_nuevo`).

## Capabilities and Constraints

- Roles: `usuario` (registra) y `admin` (registra + configura actividades + ve panel constructor).
- Las actividades (Visibilidad, Mantenimiento, Auditoría en el mock actual) no son una taxonomía fija: son datos configurables por el admin. No asumir que esos tres nombres son permanentes.
- **El proyecto está aún en construcción** — no hay restricciones de marca ni de estructura confirmadas todavía más allá de lo que ya existe en el código. No inventar reglas fijas; tratar el estado actual del código como evidencia, no como contrato.

## Brand Commitments

Cliente/marca del reporte es Epson. Identidad visual específica (paleta, tipografía) aún no confirmada como vinculante — pendiente.

## Evidence on Hand

- `diseños/code.html` y `diseños/screen.png`: mockup de referencia usado para la estructura/interacción de `historial.php` (no para paleta/tipografía Epson — el comentario en el código aclara que se cambió paleta y tipografía respecto al mockup original).
- `diseños/DESIGN.md`: registra tokens de un sistema llamado "Modern Productivity Workspace" (paleta azul/índigo genérica). Tratar como evidencia de un intento previo, no como la identidad Epson confirmada — no se generó en este init y no está enlazado desde la raíz del proyecto.

## Product Principles

1. La configurabilidad de actividades es el corazón del producto: cualquier flujo de diseño debe asumir que el admin puede agregar tipos de actividad nuevos sin tocar código.
2. Uso mobile-first: el promotor registra desde el punto de venta, probablemente desde el celular.
3. Cada actividad debe dejar rastro auditable (metadata, checklist, fotos) porque el historial existe para justificar el trabajo ante Epson, no solo para uso interno de Lucky.
4. Proyecto en construcción activa: preferir evidencia del código actual sobre convenciones inventadas.
