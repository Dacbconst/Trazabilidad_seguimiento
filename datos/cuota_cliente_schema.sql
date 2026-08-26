-- Repositorio de Cuotas trimestrales por cliente — correr en HeidiSQL contra
-- luckyec_jaboneria_wilson. Diseñado 2026-08-25 con Diego, ver
-- Acuerdos_Comerciales/CLAUDE.md sección "Repositorio de Cuotas trimestrales
-- + Actas precargadas" y el plan de esa sesión.
--
-- NOTA 2026-08-25 (primera vuelta): la primera vez que se corrió este
-- archivo, HeidiSQL creó la tabla con espacios pegados al inicio de cada
-- nombre de columna — corregido, ver el DROP TABLE de abajo.
--
-- NOTA 2026-08-25 (segunda vuelta, MIGRACIÓN): la tabla ya se había creado
-- con una sola columna `valor_mensual DECIMAL(12,2)` (asumía que los 3
-- meses del trimestre siempre traían el mismo monto). El usuario confirmó
-- que NO es así — cada mes puede traer un monto distinto — así que se
-- reemplazó por `valores_mensuales JSON`, MISMO formato que ya usa
-- `repositorio_acuerdo_lineas.valores_mensuales` (`{"3": 600, "4": 650,
-- "5": 700}`, clave = índice de mes 0-11) para que la Fase 2 (Actas
-- Precargadas) lo pueda copiar directo a una línea de Meta de Compras sin
-- convertir nada. Si ya corriste la primera vuelta (con `valor_mensual`),
-- corré SOLO este bloque para migrar (pierde los datos de prueba que ya
-- hayas subido, hay que volver a subir el Excel después):
--
--   ALTER TABLE repositorio_cuota_cliente
--     DROP COLUMN valor_mensual,
--     ADD COLUMN valores_mensuales JSON NOT NULL AFTER anio;
--
-- Si es la primera vez que corrés este archivo (la tabla no existe
-- todavía), usá el CREATE TABLE completo de abajo — ya viene con
-- valores_mensuales, no hace falta la migración.
--
-- Ejecutar manualmente en HeidiSQL u otra herramienta — Claude tiene
-- permiso de solo lectura sobre esta base (ver CLAUDE.md raíz del repo).
--
-- Mismo patrón self-service que repositorio_rebate_producto/
-- repositorio_participacion_percha (Módulo Repositorios, 2026-08-24), pero
-- con cliente: cada fila es un cliente (pos_id) x sector x trimestre, con
-- los 3 montos mensuales que trae el Excel de JW. pos_id puede quedar NULL
-- si el nombre del cliente en el Excel no matchea de forma única contra
-- repositorio_locales_supervisores_cliente — esas filas quedan en estado
-- 'pendiente_match' para resolver a mano (pantalla "Pendientes de
-- Asignar", mismo concepto que Liquidación).
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
 valores_mensuales JSON NOT NULL,
 estado ENUM('pendiente_match','pendiente_uso','usada','descartada') NOT NULL DEFAULT 'pendiente_match',
 acuerdo_id_generado INT UNSIGNED NULL,
 actualizado_por INT UNSIGNED NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_pos_sector_periodo ON repositorio_cuota_cliente (pos_id, sector, trimestre, anio);
CREATE INDEX idx_estado ON repositorio_cuota_cliente (estado);
CREATE INDEX idx_acuerdo_generado ON repositorio_cuota_cliente (acuerdo_id_generado);
