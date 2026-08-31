-- Tabla del módulo "Cumplimiento de Cuota" — correr en la base real
-- (luckyec_jaboneria_wilson). Diseñada 2026-08-30, ver Acuerdos_Comerciales/CLAUDE.md.
--
-- No reusa las tablas de Liquidación (repositorio_liquidacion_cuota_categoria):
--   - Esa tabla nunca guardó GANA TOTAL (el resultado del cliente completo).
--   - Su columna "cumplimiento" en realidad guarda el texto GANA/NO GANA, no
--     el % — no hay dónde meter el % real que este módulo sí necesita mostrar.
--   - No tiene rebate_real_vol (el monto ya topado al 110%), solo el bruto.
--   - Es de 2026-08-17, antes de la regla de borrado lógico (2026-08-25) —
--     no tiene eliminado_en/eliminado_por.
--   - Está atada al flujo de matching contra Actas (acuerdo_id/estado_match)
--     de Liquidación, que es un mecanismo aparte y en pausa (ver "REPLANTEO").
-- Sí se reusan las utilidades ya construidas: includes/xlsx_reader.php,
-- resolverPosIdCliente()/resolverSectorReal() (las mismas del Repositorio de
-- Cuotas Trimestrales) y repositorio_normalizar_texto().
--
-- Convenciones del proyecto respetadas: prefijo repositorio_, sin FOREIGN KEY,
-- borrado lógico (eliminado_en/eliminado_por), auditoría (actualizado_por +
-- updated_at con ON UPDATE), meses nunca se guardan (el período es
-- trimestre+año, igual que el resto de repositorios).
--
-- gana_categoria_anterior: no es un historial completo, solo UN paso atrás —
-- se actualiza en el UPSERT de guardado (getters/cumplimiento_guardar.php)
-- ANTES de pisar gana_categoria con el valor nuevo (el orden de las
-- asignaciones en el ON DUPLICATE KEY UPDATE importa: MySQL evalúa el SET de
-- izquierda a derecha, así que "gana_categoria_anterior = gana_categoria"
-- listado ANTES de "gana_categoria = VALUES(gana_categoria)" captura el
-- valor viejo de la fila). Sirve para pintar "Mejoró/Bajó desde la última
-- subida" en el listado principal sin necesitar una tabla de historial aparte.

CREATE TABLE repositorio_cumplimiento_cuota (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	pos_id VARCHAR(200) NOT NULL,
	cliente_excel VARCHAR(300) NOT NULL,
	cedi_excel VARCHAR(200) NULL,
	plan_excel VARCHAR(100) NULL,
	sector VARCHAR(200) NOT NULL,
	linea TINYINT UNSIGNED NOT NULL DEFAULT 1,
	trimestre TINYINT UNSIGNED NOT NULL,
	anio SMALLINT UNSIGNED NOT NULL,
	cuota_total DECIMAL(12,2) NOT NULL DEFAULT 0,
	venta_total DECIMAL(12,2) NOT NULL DEFAULT 0,
	cumplimiento_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
	gana_categoria ENUM('gana','no_gana') NOT NULL DEFAULT 'no_gana',
	gana_categoria_anterior ENUM('gana','no_gana') NULL,
	gana_total ENUM('gana','no_gana') NOT NULL DEFAULT 'no_gana',
	rebate_pct DECIMAL(6,4) NULL,
	pre_rebate DECIMAL(12,2) NULL,
	rebate_maximo_110 DECIMAL(12,2) NULL,
	rebate_real_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	eliminado_en DATETIME NULL,
	eliminado_por INT UNSIGNED NULL
);

CREATE UNIQUE INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota (pos_id, sector, trimestre, anio, linea);
CREATE INDEX idx_cumplimiento_cuota_eliminado_en ON repositorio_cumplimiento_cuota (eliminado_en);
CREATE INDEX idx_cumplimiento_cuota_periodo ON repositorio_cumplimiento_cuota (trimestre, anio);

-- linea (2026-08-31, bug real encontrado con datos reales): el Excel de JW
-- puede traer 2+ filas del MISMO Sector para el mismo cliente (ej.
-- "DISTRIBUIDORA NOVOA E HIJOS SOCIEDAD CIVIL" con 2 líneas de "AEROSOL",
-- cuota $62 y $73, ambas con la misma venta real $108 — probablemente 2
-- Subcategorías de AEROSOL que esta hoja no distingue por nombre). Con la
-- clave única original (pos_id, sector, trimestre, anio) la 2da fila pisaba
-- a la primera vía ON DUPLICATE KEY UPDATE y se perdía un dato real. La
-- columna `linea` (1, 2, 3... según el orden en que aparecen en el Excel
-- para ese mismo cliente+Sector) entra a la clave única para que las 2
-- líneas se guarden como filas separadas, calcando el archivo original —
-- se llama "línea" (línea 1 de 2, línea 2 de 2) y no "ocurrencia" a
-- propósito: es un dato normal, no una señal de que algo falló.
--
-- Pendiente correr en la base real, en 2 partes (ver "Excepción a la regla
-- de solo lectura" en Acuerdos_Comerciales/CLAUDE.md — Claude puede correr
-- CREATE/ALTER acá mostrando el SQL antes y con confirmación explícita,
-- pero CUALQUIER DROP —incluido DROP INDEX— lo tiene que correr el usuario):
--   1) El usuario corre esto en HeidiSQL:
--        DROP INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota;
--   2) Recién ahí, Claude corre (mostrando el SQL y con confirmación):
--        ALTER TABLE repositorio_cumplimiento_cuota
--          ADD COLUMN linea TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER sector;
--        CREATE UNIQUE INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota (pos_id, sector, trimestre, anio, linea);
-- (el CREATE TABLE de arriba ya incluye la columna final para cuando se
-- corra este script desde cero en un entorno nuevo — los 2 pasos de arriba
-- son solo para la base real, que ya tiene la tabla creada sin esta columna.)
