-- Tablas del módulo Liquidación — correr en HeidiSQL contra luckyec_jaboneria_wilson.
-- Diseñado 2026-08-17 con Diego, ver CLAUDE.md sección "Módulo Liquidación".
-- Formato simple a propósito (2026-08-18): CREATE TABLE solo con columnas,
-- índices en sentencias CREATE INDEX aparte — un KEY inline en el CREATE
-- TABLE dio error de sintaxis (1064) al correrlo, así que se separó todo en
-- sentencias independientes y más fáciles de correr una por una.
--
-- Convenciones del proyecto respetadas:
--   - Prefijo repositorio_ obligatorio.
--   - Sin FOREIGN KEY (el usuario de BD no tiene privilegio REFERENCES).
--   - Nunca se guardan totales/comparaciones calculadas — eso se calcula al
--     vuelo con un JOIN contra repositorio_acuerdo_lineas al mostrar el
--     Resumen de Pagos, nunca se duplica acá.
--
-- NOTA: se descartó tocar repositorio_locales_supervisores_cliente (maestro
-- externo de Alicorp) — decisión explícita del usuario de no tocar esquema
-- de tablas que no son propias de este módulo.

CREATE TABLE repositorio_liquidacion_importaciones (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	canal ENUM('directa','distribuidor') NOT NULL,
	anio SMALLINT UNSIGNED NOT NULL,
	mes_inicio TINYINT UNSIGNED NOT NULL,
	mes_fin TINYINT UNSIGNED NOT NULL,
	nombre_archivo VARCHAR(255) NOT NULL,
	subido_por INT UNSIGNED NULL,
	estado ENUM('procesando','completado','con_errores') NOT NULL DEFAULT 'procesando',
	total_filas INT UNSIGNED NOT NULL DEFAULT 0,
	filas_pendientes INT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE repositorio_liquidacion_cuota_categoria (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	importacion_id INT UNSIGNED NOT NULL,
	cedi_o_distribuidor VARCHAR(200) NOT NULL,
	cliente_o_nombre VARCHAR(300) NOT NULL,
	codigo VARCHAR(50) NULL,
	ruc VARCHAR(20) NULL,
	categoria VARCHAR(200) NOT NULL,
	cuota_total_excel DECIMAL(12,2) NOT NULL,
	venta_total_excel DECIMAL(12,2) NOT NULL,
	rebate_pct_excel DECIMAL(6,4) NOT NULL,
	rebate_dolares_excel DECIMAL(12,2) NOT NULL,
	rebate_maximo_110 DECIMAL(12,2) NULL,
	cumplimiento VARCHAR(50) NULL,
	acuerdo_id INT UNSIGNED NULL,
	estado_match ENUM('pendiente','matcheado','sin_match','sin_acta') NOT NULL DEFAULT 'pendiente',
	matcheado_por INT UNSIGNED NULL,
	matcheado_en DATETIME NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE repositorio_liquidacion_visibilidad (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	importacion_id INT UNSIGNED NOT NULL,
	cedi_o_distribuidor VARCHAR(200) NOT NULL,
	cliente_o_nombre VARCHAR(300) NOT NULL,
	cantidad_cabecera SMALLINT UNSIGNED NULL,
	cantidad_isla SMALLINT UNSIGNED NULL,
	cantidad_percha SMALLINT UNSIGNED NULL,
	pago_cabecera DECIMAL(10,2) NULL,
	pago_isla DECIMAL(10,2) NULL,
	pago_percha DECIMAL(10,2) NULL,
	pago_total_excel DECIMAL(10,2) NULL,
	cumplimiento VARCHAR(50) NULL,
	acuerdo_id INT UNSIGNED NULL,
	estado_match ENUM('pendiente','matcheado','sin_match','sin_acta') NOT NULL DEFAULT 'pendiente',
	matcheado_por INT UNSIGNED NULL,
	matcheado_en DATETIME NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_canal_periodo ON repositorio_liquidacion_importaciones (canal, anio, mes_inicio, mes_fin);

CREATE INDEX idx_importacion ON repositorio_liquidacion_cuota_categoria (importacion_id);
CREATE INDEX idx_acuerdo ON repositorio_liquidacion_cuota_categoria (acuerdo_id);
CREATE INDEX idx_estado_match ON repositorio_liquidacion_cuota_categoria (estado_match);

CREATE INDEX idx_importacion ON repositorio_liquidacion_visibilidad (importacion_id);
CREATE INDEX idx_acuerdo ON repositorio_liquidacion_visibilidad (acuerdo_id);
CREATE INDEX idx_estado_match ON repositorio_liquidacion_visibilidad (estado_match);
