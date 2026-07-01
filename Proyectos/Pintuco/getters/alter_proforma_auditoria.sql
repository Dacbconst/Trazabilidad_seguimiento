-- ALTER TABLE pendiente para módulo Auditoría de Proformas.
-- Correr en HeidiSQL contra la BD de producción/desarrollo.
-- Sin estas columnas el módulo funciona (estado se guarda), pero
-- monto_validado y observaciones NO persisten hasta ejecutar esto.

ALTER TABLE insert_proforma
    ADD COLUMN monto_validado          DECIMAL(12,2)  NULL DEFAULT NULL COMMENT 'Monto cotizado leído de la foto (lo ingresa el analista)',
    ADD COLUMN observaciones_auditoria VARCHAR(500)    NULL DEFAULT NULL COMMENT 'Notas internas de validación del analista',
    ADD COLUMN fecha_auditoria         DATETIME        NULL DEFAULT NULL COMMENT 'Fecha/hora en que el analista resolvió la auditoría';
