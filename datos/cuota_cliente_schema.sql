-- Repositorio de Cuotas trimestrales por cliente — correr en HeidiSQL contra
-- luckyec_jaboneria_wilson. Diseñado 2026-08-25 con Diego, ver
-- Acuerdos_Comerciales/CLAUDE.md sección "Repositorio de Cuotas trimestrales
-- + Actas precargadas" y el plan de esa sesión.
--
-- NOTA 2026-08-25: la primera vez que se corrió este archivo, HeidiSQL creó
-- la tabla con espacios pegados al inicio de cada nombre de columna
-- ("  pos_id" en vez de "pos_id", confirmado con DESCRIBE de solo lectura)
-- — probablemente el copiar/pegar convirtió los tabs de indentación del
-- bloque original en espacios que quedaron pegados al identificador. Esta
-- versión usa espacios normales (sin tabs) para evitar que se repita. Si ya
-- corriste la versión con tabs, primero soltá la tabla rota:
--   DROP TABLE repositorio_cuota_cliente;
--
-- Mismo patrón self-service que repositorio_rebate_producto/
-- repositorio_participacion_percha (Módulo Repositorios, 2026-08-24), pero
-- con cliente: cada fila es un cliente (pos_id) x sector x trimestre, con el
-- monto mensual fijo que trae el Excel de JW (mismo valor los 3 meses del
-- trimestre). pos_id puede quedar NULL si el nombre del cliente en el Excel
-- no matchea de forma única contra repositorio_locales_supervisores_cliente
-- — esas filas quedan en estado 'pendiente_match' para resolver a mano
-- (pantalla "Pendientes de Asignar", mismo concepto que Liquidación).
--
-- Convenciones del proyecto respetadas:
--   - Prefijo repositorio_ obligatorio.
--   - Sin FOREIGN KEY (el usuario de BD no tiene privilegio REFERENCES).
--   - Índices en CREATE INDEX aparte, no inline (un KEY inline en el CREATE
--     TABLE ya dio error 1064 antes en este proyecto, ver liquidacion_schema.sql).
--   - Nunca se guarda un total/comparación calculada.

DROP TABLE IF EXISTS repositorio_cuota_cliente;

CREATE TABLE repositorio_cuota_cliente (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pos_id VARCHAR(50) NULL,
 cliente_excel VARCHAR(200) NOT NULL,
 cedi_excel VARCHAR(200) NULL,
 plan VARCHAR(100) NULL,
 sector VARCHAR(100) NOT NULL,
 trimestre TINYINT UNSIGNED NOT NULL,
 anio SMALLINT UNSIGNED NOT NULL,
 valor_mensual DECIMAL(12,2) NOT NULL DEFAULT 0,
 estado ENUM('pendiente_match','pendiente_uso','usada','descartada') NOT NULL DEFAULT 'pendiente_match',
 acuerdo_id_generado INT UNSIGNED NULL,
 actualizado_por INT UNSIGNED NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_pos_sector_periodo ON repositorio_cuota_cliente (pos_id, sector, trimestre, anio);
CREATE INDEX idx_estado ON repositorio_cuota_cliente (estado);
CREATE INDEX idx_acuerdo_generado ON repositorio_cuota_cliente (acuerdo_id_generado);
