<?php
// Secciones del sidebar. "roles" es la lista exacta de roles que ven cada módulo (validada con rolPermitido()); no es jerárquico.
$secciones = [
	['id' => 'registrar',        'label' => 'Registrar Acuerdo PDV', 'icono' => 'dashboard',       'componente' => 'components/registrar/registrar.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'historial',        'label' => 'Historial de Acuerdos', 'icono' => 'description',     'componente' => 'components/historial/historial.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'seguimiento',      'label' => 'Seguimiento de Equipo', 'icono' => 'monitoring',      'componente' => 'components/seguimiento/seguimiento.php',           'roles' => ['superdesarrollador']],
	// Cuenta ACUERDOS con al menos 1 línea de cada tipo (Rebate/Cabeceras/Rumas/Perchas), no filas de repositorio_acuerdo_lineas.
	['id' => 'resumen-negociacion', 'label' => 'Resumen de Negociación', 'icono' => 'query_stats', 'componente' => 'components/resumen-negociacion/resumen-negociacion.php', 'roles' => ['superdesarrollador']],
	// Cumplimiento de Cuota solo LEE los resultados que el Excel ya calculó (GANA POR CATEGORÍA/TOTAL/CUMPLIMIENTO), nunca los recalcula.
	['id' => 'cumplimiento',     'label' => 'Cumplimiento de Cuota', 'icono' => 'fact_check',      'componente' => 'components/cumplimiento/cumplimiento.php',         'roles' => ['superdesarrollador']],
	// Liquidación oculta temporalmente, en duda de negocio (ver CLAUDE.md "Módulo Liquidación"). Código y datos intactos; descomentar para reactivar. ['id' => 'liquidacion', 'label' => 'Liquidación', 'icono' => 'payments', 'componente' => 'components/liquidacion/liquidacion.php', 'roles' => ['superdesarrollador']],
	// Penúltimo a propósito (2026-09-23, pedido explícito), no primero como antes.
	['id' => 'repositorios',     'label' => 'Repositorios',          'icono' => 'inventory_2',     'componente' => 'components/repositorios/repositorios.php',         'roles' => ['superdesarrollador']],
	['id' => 'gestion-usuarios', 'label' => 'Gestión de Usuarios',   'icono' => 'manage_accounts', 'componente' => 'components/gestion-usuarios/gestion-usuarios.php', 'roles' => ['superdesarrollador']],
];
?>
