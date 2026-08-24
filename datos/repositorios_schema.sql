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

CREATE TABLE repositorio_rebate_producto (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	segmento VARCHAR(200) NOT NULL,
	sector VARCHAR(200) NOT NULL,
	categoria VARCHAR(200) NOT NULL,
	marca VARCHAR(200) NOT NULL,
	rebate_pct DECIMAL(6,4) NOT NULL,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE repositorio_participacion_percha (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	marca VARCHAR(200) NOT NULL,
	participacion_pct DECIMAL(5,2) NOT NULL,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Evita duplicados exactos del mismo producto/marca — el getter de guardado
-- hace UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) sobre esta clave, tanto
-- al subir un Excel como al editar una fila desde la tabla.
CREATE UNIQUE INDEX uq_rebate_producto ON repositorio_rebate_producto (segmento, sector, categoria, marca);
CREATE UNIQUE INDEX uq_participacion_marca ON repositorio_participacion_percha (marca);
