-- Corre esto en HeidiSQL antes de usar el nuevo campo "Supervisor" en Gestión
-- de Usuarios. repositorio_usuarios_acuerdos es una tabla propia del
-- proyecto (no un maestro externo), así que se puede alterar libremente.
-- Nullable porque los usuarios ya creados no tienen este dato todavía.
ALTER TABLE repositorio_usuarios_acuerdos
  ADD COLUMN supervisor VARCHAR(100) NULL AFTER rol;
