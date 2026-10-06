-- REEMPLAZADO por fix_pdvac_2026-10-06.sql (el Paso 3 de este archivo da error 1062 y los clientes deben quedar con código PDVAC) — no volver a correr.
-- Backfill de repositorio_clientes_propiosac a partir de lo que ya hay en Cuotas Trimestrales.
-- Correr en HeidiSQL, en orden. Claude no ejecuta nada de esto (regla de solo lectura del repo).

-- Paso 1: los 118 clientes que ya tienen pos_id real (EPV/EPVD) en repositorio_cuota_cliente.
-- Se preserva el mismo pos_id que ya tenían -- nada cambia para ellos, solo dejan de depender del maestro.
INSERT IGNORE INTO repositorio_clientes_propiosac
  (pos_id, cliente_excel, cliente_comparable, cedi_excel, distribuidor_excel, canal, creado_por, created_at)
SELECT
  pos_id, cliente_excel,
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    UPPER(cliente_excel)
  ,'.',''),',',''),' ',''),'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'),'Ü','U'),'Ñ','N'),
  cedi_excel, plan,
  IF(plan IS NOT NULL AND plan <> '', 'distribuidor', 'directo'),
  NULL, NOW()
FROM (
  SELECT pos_id, cliente_excel, cedi_excel, plan
  FROM repositorio_cuota_cliente
  WHERE pos_id IS NOT NULL AND pos_id <> '' AND pos_id NOT LIKE 'PDVAC%'
  GROUP BY pos_id
) t;

-- Paso 2: registrar "CHASI TINE JOSE IGNACIO/JOSEDEL S.A" como PDV nuevo (el único que seguía sin pos_id).
INSERT INTO repositorio_clientes_propiosac
  (pos_id, cliente_excel, cliente_comparable, cedi_excel, distribuidor_excel, canal, creado_por, created_at)
VALUES ('', 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A', 'CHASITINEJOSEIGNACIO/JOSEDELSA', 'SANTO DOMINGO', 'ASERTIA COMERCIAL SA', 'distribuidor', NULL, NOW());

UPDATE repositorio_clientes_propiosac SET pos_id = CONCAT('PDVAC', LPAD(id, 4, '0')) WHERE pos_id = '';

-- Paso 3: marcar las filas pendientes de ese cliente en Cuotas Trimestrales como ya resueltas.
UPDATE repositorio_cuota_cliente c
JOIN repositorio_clientes_propiosac p
  ON p.cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' AND p.cedi_excel = 'SANTO DOMINGO'
SET c.pos_id = p.pos_id, c.estado = 'pendiente_uso'
WHERE c.cliente_excel = 'CHASI TINE JOSE IGNACIO/JOSEDEL S.A' AND c.cedi_excel = 'SANTO DOMINGO' AND c.estado = 'pendiente_match';
