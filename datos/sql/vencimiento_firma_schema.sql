-- Vencimiento de firma (2026-08-25) — un Acta 'generado'/'enviado' que pasa
-- 20 días desde fecha_generacion sin firmar pasa a 'vencido': deja de poder
-- subírsele la firma y desaparece de Historial, mismo criterio que
-- 'anulado' pero como valor DISTINTO en el ENUM (a pedido explícito, para
-- poder diferenciar después "el usuario canceló" de "se venció solo" en
-- reportes). Ver includes/functions.php (barrer_actas_vencidas(),
-- listar_alertas_firma_propias(), listar_equipo_pendientes_firma()) y
-- getters/subir_acta_firmada.php.
--
-- Ejecutar manualmente en HeidiSQL u otra herramienta — Claude tiene
-- permiso de solo lectura sobre esta base (ver CLAUDE.md raíz del repo).

ALTER TABLE repositorio_acuerdos
  MODIFY estado ENUM('borrador','generado','enviado','firmado','liquidado','anulado','vencido')
  NOT NULL DEFAULT 'borrador';
