-- Tablas del módulo Liquidación — correr en HeidiSQL contra luckyec_jaboneria_wilson.
-- Diseñado 2026-08-17 con Diego, ver CLAUDE.md sección "Módulo Liquidación".
--
-- Convenciones del proyecto respetadas:
--   - Prefijo repositorio_ obligatorio.
--   - Sin FOREIGN KEY (el usuario de BD no tiene privilegio REFERENCES) — se
--     usa KEY normal para índice, y la integridad se valida en el backend PHP.
--   - Nunca se guardan totales/comparaciones calculadas (ej. si el rebate de
--     la Acta coincide con el del Excel) — eso se calcula al vuelo con un
--     JOIN contra repositorio_acuerdo_lineas al mostrar el Resumen de Pagos,
--     nunca se duplica acá.

-- 0) OPTIMIZACIÓN (opcional pero muy recomendada, aditiva, no toca datos):
--    repositorio_locales_supervisores_cliente (~41,000 filas) hoy NO tiene
--    ningún índice más que el PRIMARY KEY de `id` — confirmado con EXPLAIN:
--    cualquier búsqueda por pos_name/supervisor/tipo_distribuidor (el
--    matching de Liquidación hace muchas, una por cliente único de cada
--    Excel importado) es un escaneo COMPLETO de la tabla. Agregar estos 3
--    índices no cambia ni un dato, solo acelera las búsquedas.
ALTER TABLE repositorio_locales_supervisores_cliente
	ADD INDEX idx_pos_name (pos_name),
	ADD INDEX idx_supervisor (supervisor),
	ADD INDEX idx_tipo_distribuidor (tipo_distribuidor);

-- 1) Un registro por Excel subido (el "lote" de un canal). El período
--    (mes_inicio/mes_fin, 0-11 igual que repositorio_acuerdos) NO lo elige
--    quien sube el archivo — se detecta leyendo las columnas de mes reales
--    de la hoja de cuota/venta (ver liquidacion_parsear_cuota_categoria() en
--    includes/liquidacion_import.php). No hay ninguna frecuencia de subida
--    confirmada con el cliente (¿mensual? ¿trimestral?) — el archivo mismo
--    es la única fuente de verdad de qué período cubre cada vez.
CREATE TABLE repositorio_liquidacion_importaciones (
	id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	canal            ENUM('directa','distribuidor') NOT NULL,
	anio             SMALLINT UNSIGNED NOT NULL,
	mes_inicio       TINYINT UNSIGNED NOT NULL COMMENT '0-11, 0=Enero',
	mes_fin          TINYINT UNSIGNED NOT NULL COMMENT '0-11, 0=Enero',
	nombre_archivo   VARCHAR(255) NOT NULL,
	subido_por       INT UNSIGNED NULL COMMENT 'FK lógica a repositorio_usuarios_acuerdos.id',
	estado           ENUM('procesando','completado','con_errores') NOT NULL DEFAULT 'procesando',
	total_filas      INT UNSIGNED NOT NULL DEFAULT 0,
	filas_pendientes INT UNSIGNED NOT NULL DEFAULT 0,
	created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
	KEY idx_canal_periodo (canal, anio, mes_inicio, mes_fin)
);

-- 2) Una fila por cliente+categoría de la hoja "Cuota/Venta/Rebate" del Excel,
--    tal cual viene (datos crudos), vinculada a un acuerdo_id cuando se resuelve
--    el match (ver estado_match).
CREATE TABLE repositorio_liquidacion_cuota_categoria (
	id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	importacion_id       INT UNSIGNED NOT NULL,

	-- Datos crudos del Excel, para trazabilidad (nunca se sobreescriben).
	cedi_o_distribuidor  VARCHAR(200) NOT NULL COMMENT 'columna CEDI (directa) o DISTRIBUIDOR',
	cliente_o_nombre     VARCHAR(300) NOT NULL COMMENT 'columna CLIENTE (directa) o NOMBRE (distribuidor)',
	codigo               VARCHAR(50) NULL COMMENT 'solo distribuidor',
	ruc                  VARCHAR(20) NULL COMMENT 'solo distribuidor',
	categoria            VARCHAR(200) NOT NULL,
	cuota_total_excel    DECIMAL(12,2) NOT NULL,
	venta_total_excel    DECIMAL(12,2) NOT NULL,
	rebate_pct_excel     DECIMAL(6,4) NOT NULL,
	rebate_dolares_excel DECIMAL(12,2) NOT NULL,
	rebate_maximo_110    DECIMAL(12,2) NULL,
	cumplimiento         VARCHAR(50) NULL COMMENT 'GANA / NO GANA tal como viene del Excel',

	-- Vínculo con la Acta — NULL hasta que se resuelve el match.
	acuerdo_id           INT UNSIGNED NULL COMMENT 'FK lógica a repositorio_acuerdos.id',
	-- 'sin_acta': confirmado a mano que esta fila no corresponde a ninguna
	-- Acta del sistema (dato histórico de antes de que existiera, u otro caso
	-- legítimo) — a diferencia de 'sin_match'/'pendiente' (todavía sin
	-- resolver), 'sin_acta' es un estado FINAL: no cuenta como pendiente ni
	-- se vuelve a mostrar en la cola de "Pendientes de Asignar".
	estado_match         ENUM('pendiente','matcheado','sin_match','sin_acta') NOT NULL DEFAULT 'pendiente',
	matcheado_por        INT UNSIGNED NULL,
	matcheado_en         DATETIME NULL,

	created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
	KEY idx_importacion (importacion_id),
	KEY idx_acuerdo (acuerdo_id),
	KEY idx_estado_match (estado_match)
);

-- 3) Una fila por cliente de la hoja "Visibilidad" del Excel, mismo patrón de match.
CREATE TABLE repositorio_liquidacion_visibilidad (
	id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	importacion_id       INT UNSIGNED NOT NULL,

	cedi_o_distribuidor  VARCHAR(200) NOT NULL,
	cliente_o_nombre     VARCHAR(300) NOT NULL,
	cantidad_cabecera    SMALLINT UNSIGNED NULL,
	cantidad_isla        SMALLINT UNSIGNED NULL,
	cantidad_percha      SMALLINT UNSIGNED NULL,
	pago_cabecera        DECIMAL(10,2) NULL,
	pago_isla            DECIMAL(10,2) NULL,
	pago_percha          DECIMAL(10,2) NULL,
	pago_total_excel     DECIMAL(10,2) NULL,
	cumplimiento         VARCHAR(50) NULL,

	acuerdo_id           INT UNSIGNED NULL,
	-- 'sin_acta': confirmado a mano que esta fila no corresponde a ninguna
	-- Acta del sistema (dato histórico de antes de que existiera, u otro caso
	-- legítimo) — a diferencia de 'sin_match'/'pendiente' (todavía sin
	-- resolver), 'sin_acta' es un estado FINAL: no cuenta como pendiente ni
	-- se vuelve a mostrar en la cola de "Pendientes de Asignar".
	estado_match         ENUM('pendiente','matcheado','sin_match','sin_acta') NOT NULL DEFAULT 'pendiente',
	matcheado_por        INT UNSIGNED NULL,
	matcheado_en         DATETIME NULL,

	created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
	KEY idx_importacion (importacion_id),
	KEY idx_acuerdo (acuerdo_id),
	KEY idx_estado_match (estado_match)
);
