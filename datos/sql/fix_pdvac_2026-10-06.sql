-- Corrige el backfill del 2026-10-05 (error 1062 en Paso 3) y pasa TODA la base propia a códigos PDVAC. Correr en HeidiSQL, bloque por bloque y en orden.
-- Claude no ejecuta nada de esto (regla de solo lectura del repo).

-- ============================================================
-- PASO 0 — DIAGNÓSTICO (solo SELECT, correr primero y revisar)
-- ============================================================

-- 0.1 Cuántas filas tiene la base propia y cuántas siguen con código Alicorp (EPV/EPVD).
SELECT SUM(pos_id LIKE 'PDVAC%') AS con_pdvac, SUM(pos_id NOT LIKE 'PDVAC%') AS con_codigo_alicorp, COUNT(*) AS total FROM repositorio_clientes_propiosac;

-- 0.2 CHASI repetido: el Paso 2 de ayer lo volvió a registrar aunque ya existía como PDVAC0006.
SELECT id, pos_id, cliente_excel, cedi_excel, canal, created_at FROM repositorio_clientes_propiosac WHERE cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' ORDER BY id;

-- 0.3 Filas de CHASI en Cuotas: las pendiente_match chocan con las que ya tienen PDVAC0006 (mismo sector/subcategoría/marca/trimestre/año).
SELECT id, pos_id, sector, subcategoria, marca, trimestre, anio, estado, valores_mensuales, created_at FROM repositorio_cuota_cliente WHERE cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' ORDER BY sector, subcategoria, marca, trimestre, anio, id;

-- ============================================================
-- PASO 1 — columna para guardar el código Alicorp original (ALTER, no borra nada)
-- ============================================================

ALTER TABLE repositorio_clientes_propiosac ADD COLUMN pos_id_alicorp VARCHAR(50) NULL AFTER pos_id;

-- ============================================================
-- PASO 2 — datos (todo en una transacción: si algo falla, correr ROLLBACK; y avisar)
-- ============================================================

START TRANSACTION;

-- 2.1 Quitar el CHASI duplicado que creó ayer el Paso 2 (se queda el registro más viejo, PDVAC0006).
DELETE p FROM repositorio_clientes_propiosac p JOIN repositorio_clientes_propiosac viejo ON viejo.cliente_excel = p.cliente_excel AND viejo.cedi_excel <=> p.cedi_excel AND viejo.canal = p.canal AND viejo.id < p.id WHERE p.cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A';

-- 2.2 Clientes de Cuotas con código Alicorp que todavía no estén en la base propia (idempotente, por si el Paso 1 de ayer no llegó a correr).
INSERT IGNORE INTO repositorio_clientes_propiosac (pos_id, cliente_excel, cliente_comparable, cedi_excel, distribuidor_excel, canal, creado_por, created_at)
SELECT pos_id, cliente_excel,
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(cliente_excel),'.',''),',',''),' ',''),'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'),'Ü','U'),'Ñ','N'),
  cedi_excel, plan, IF(plan IS NOT NULL AND plan <> '', 'distribuidor', 'directo'), NULL, NOW()
FROM (SELECT pos_id, MAX(cliente_excel) AS cliente_excel, MAX(cedi_excel) AS cedi_excel, MAX(plan) AS plan FROM repositorio_cuota_cliente WHERE pos_id IS NOT NULL AND pos_id <> '' AND pos_id NOT LIKE 'PDVAC%' GROUP BY pos_id) t;

-- 2.3 Mismo backfill para los clientes de Acuerdo Completo (el script de ayer no los incluía).
INSERT IGNORE INTO repositorio_clientes_propiosac (pos_id, cliente_excel, cliente_comparable, cedi_excel, distribuidor_excel, canal, creado_por, created_at)
SELECT pos_id, cliente_excel,
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(cliente_excel),'.',''),',',''),' ',''),'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'),'Ü','U'),'Ñ','N'),
  cedi_excel, plan, IF(plan IS NOT NULL AND plan <> '', 'distribuidor', 'directo'), NULL, NOW()
FROM (SELECT pos_id, MAX(cliente_excel) AS cliente_excel, MAX(cedi_excel) AS cedi_excel, MAX(plan) AS plan FROM repositorio_acuerdo_completo_linea WHERE pos_id IS NOT NULL AND pos_id <> '' AND pos_id NOT LIKE 'PDVAC%' GROUP BY pos_id) t;

-- 2.4 Renombrar a PDVAC + id (misma fórmula que crearClientePropio()) guardando el código Alicorp en pos_id_alicorp.
UPDATE repositorio_clientes_propiosac SET pos_id_alicorp = pos_id, pos_id = CONCAT('PDVAC', LPAD(id, 4, '0')) WHERE pos_id NOT LIKE 'PDVAC%';

-- 2.5 Propagar el código nuevo a las tablas que guardan el pos_id de esos clientes (mapeo 1 a 1, no genera choques de clave).
UPDATE repositorio_cuota_cliente c JOIN repositorio_clientes_propiosac p ON p.pos_id_alicorp = c.pos_id SET c.pos_id = p.pos_id;
UPDATE repositorio_acuerdo_completo_linea c JOIN repositorio_clientes_propiosac p ON p.pos_id_alicorp = c.pos_id SET c.pos_id = p.pos_id;
UPDATE repositorio_cumplimiento_cuota c JOIN repositorio_clientes_propiosac p ON p.pos_id_alicorp = c.pos_id SET c.pos_id = p.pos_id;

-- 2.6 CHASI: las pendiente_match que ya existen con PDVAC0006 (misma clave) se descartan — esto era lo que daba el error 1062.
UPDATE repositorio_cuota_cliente c JOIN repositorio_cuota_cliente r ON r.pos_id = 'PDVAC0006' AND r.sector = c.sector AND r.subcategoria <=> c.subcategoria AND r.marca <=> c.marca AND r.trimestre = c.trimestre AND r.anio = c.anio
SET c.estado = 'descartada'
WHERE c.cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' AND c.estado = 'pendiente_match';

-- 2.7 CHASI: las pendiente_match que quedan (sin choque) se asignan a PDVAC0006.
UPDATE repositorio_cuota_cliente SET pos_id = 'PDVAC0006', estado = 'pendiente_uso' WHERE cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' AND estado = 'pendiente_match';

COMMIT;

-- ============================================================
-- PASO 3 — VERIFICACIÓN (solo SELECT, todo debería dar 0)
-- ============================================================

SELECT COUNT(*) AS propios_sin_pdvac FROM repositorio_clientes_propiosac WHERE pos_id NOT LIKE 'PDVAC%';
SELECT COUNT(*) AS cuotas_con_codigo_alicorp FROM repositorio_cuota_cliente WHERE pos_id IS NOT NULL AND pos_id <> '' AND pos_id NOT LIKE 'PDVAC%';
SELECT COUNT(*) AS acuerdo_completo_con_codigo_alicorp FROM repositorio_acuerdo_completo_linea WHERE pos_id IS NOT NULL AND pos_id <> '' AND pos_id NOT LIKE 'PDVAC%';
SELECT COUNT(*) AS chasi_pendientes FROM repositorio_cuota_cliente WHERE cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' AND estado = 'pendiente_match';
