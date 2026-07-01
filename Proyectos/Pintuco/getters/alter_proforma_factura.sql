-- Agrega la columna para la foto de factura del pedido.
-- Ejecutar UNA SOLA VEZ en HeidiSQL sobre la BD de Pintuco.
-- Estado nuevo: 'venta_finalizada' → el promotor sube la foto de la
-- factura desde el app Android, marcando el cierre real de la venta.

ALTER TABLE insert_proforma
  ADD COLUMN foto_factura VARCHAR(500) NULL
    COMMENT 'Ruta de la foto de factura del pedido (subida por Android cuando la venta se concreta)';

-- Verificación: debe aparecer la nueva columna
-- SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT
-- FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insert_proforma'
-- ORDER BY ORDINAL_POSITION;
