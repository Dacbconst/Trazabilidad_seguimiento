-- Tablas del módulo Repositorios (Rebate y Participación de Percha) — correr
-- en HeidiSQL contra luckyec_jaboneria_wilson. Diseñado 2026-08-24 con Diego,
-- ver Acuerdos_Comerciales/CLAUDE.md sección "Módulo Repositorios".
-- Mismo formato que datos/liquidacion_schema.sql (CREATE TABLE solo con
-- columnas, índices en CREATE INDEX/CREATE UNIQUE INDEX aparte — un KEY/
-- UNIQUE inline en el CREATE TABLE ya dio error 1064 en este mismo servidor
-- antes, ver liquidacion_schema.sql).
--
-- Convenciones del proyecto respetadas:
--   - Prefijo repositorio_ obligatorio.
--   - Sin FOREIGN KEY (el usuario de BD no tiene privilegio REFERENCES) —
--     actualizado_por es FK lógica a repositorio_usuarios_acuerdos.id,
--     validada en código, igual que creado_por en repositorio_acuerdos.
--
-- Segmento/Sector/Categoría/Marca son el mismo vocabulario que ya usa
-- repositorio_productos y la cascada de Meta de Compras en Registrar
-- (bindCascadaComboConSector) — Sector es NOT NULL acá (a diferencia de
-- repositorio_acuerdo_lineas.sector, que quedó NULL-able por compatibilidad
-- con Actas viejas de antes de 2026-08-18): este repositorio es dato nuevo
-- desde cero, no hay nada legacy que tolerar.

-- Borrado lógico, regla base (2026-08-25, pedido explícito del usuario tras
-- descubrir que "Eliminar" era un DELETE físico real, sin forma de deshacer
-- un borrado por error): eliminado_en (NULL = activa; con fecha = cuándo se
-- borró) + eliminado_por (quién) — mismo patrón de auditoría que
-- created_at/updated_at/actualizado_por, que ya existían. Nunca más DELETE
-- físico en una tabla de repositorio/catálogo nueva — "Eliminar" en la app
-- pasa a ser un UPDATE que llena estas 2 columnas, la fila sigue existiendo
-- y se puede reactivar (ver getters/repositorio_reactivar.php). Aplica
-- también como convención para cualquier tabla repositorio_* futura (ver
-- CLAUDE.md "Convenciones para código nuevo") — repositorio_cuota_cliente
-- se está trabajando aparte, no se tocó acá.

-- repositorio_rebate_producto — REDISEÑADA 2026-08-27 (2da vuelta, la
-- primera versión con Segmento NUNCA se usó de verdad, 0 filas reales en
-- producción). El primer diseño (segmento/sector/categoria/marca) fue una
-- suposición copiada de la jerarquía de Meta de Compras — el usuario
-- confirmó que el Excel real que sube JW (`datos/RABATE.xlsx`, "el
-- veredicto final") es la fuente de verdad y NO tiene columna de Segmento
-- en absoluto. En cambio, CIUDAD y CANAL sí son reales y SÍ importan:
-- verificado con las 55 filas reales que el MISMO producto (Sector+
-- Categoría+Marca) tiene un % de Rebate DISTINTO según Canal
-- (DISTRIBUIDOR/DIRECTA) y Ciudad (para Directa: MANABI/GUAYAQUIL/SANTO
-- DOMINGO/QUITO; Distribuidor siempre "TODAS") — sin Ciudad+Canal en la
-- clave, el UPSERT pisaría 44 de las 55 filas reales entre sí. "Sector" acá
-- es la columna "CATEGORIA" del Excel de JW, y "Categoría" es su
-- "SUBCATEGORIA" — mismo vocabulario ya usado en Meta de Compras.
-- ALTER en vez de DROP+CREATE (pedido explícito del usuario, 2026-08-27) —
-- la tabla ya existía con 0 filas reales, así que el resultado final es el
-- mismo, solo cambia el camino para llegar ahí.
ALTER TABLE repositorio_rebate_producto
	DROP COLUMN segmento,
	ADD COLUMN ciudad VARCHAR(200) NOT NULL AFTER id,
	ADD COLUMN canal VARCHAR(100) NOT NULL AFTER ciudad;

DROP INDEX uq_rebate_producto ON repositorio_rebate_producto;
CREATE UNIQUE INDEX uq_rebate_producto ON repositorio_rebate_producto (ciudad, canal, sector, categoria, marca);

-- Participación de Percha: TODAVÍA PENDIENTE de correr — la estructura de
-- abajo es un diseño inicial, no confirmado con el formato final que va a
-- subir JW (ver CLAUDE.md "Módulo Repositorios").
CREATE TABLE repositorio_participacion_percha (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	marca VARCHAR(200) NOT NULL,
	participacion_pct DECIMAL(5,2) NOT NULL,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	eliminado_en DATETIME NULL,
	eliminado_por INT UNSIGNED NULL
);

CREATE INDEX idx_participacion_eliminado_en ON repositorio_participacion_percha (eliminado_en);

-- Evita duplicados exactos de la misma marca — el getter de guardado hace
-- UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) sobre esta clave, tanto al
-- subir un Excel como al editar una fila desde la tabla.
CREATE UNIQUE INDEX uq_participacion_marca ON repositorio_participacion_percha (marca);
