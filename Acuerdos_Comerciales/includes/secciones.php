<?php
// Secciones del sidebar. "roles" es la lista exacta de roles que ven cada
// módulo (se valida con rolPermitido() de functions.php). No es jerárquico.
//
//   superdesarrollador -> los 6 módulos (Liquidación está temporalmente
//                          oculta, ver comentario más abajo, pero cuenta)
//   desarrollador       -> Registrar, Historial
$secciones = [
	['id' => 'registrar',        'label' => 'Registrar Acuerdo PDV', 'icono' => 'dashboard',       'componente' => 'components/registrar/registrar.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'historial',        'label' => 'Historial de Acuerdos', 'icono' => 'description',     'componente' => 'components/historial/historial.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'repositorios',     'label' => 'Repositorios',          'icono' => 'inventory_2',     'componente' => 'components/repositorios/repositorios.php',         'roles' => ['superdesarrollador']],
	['id' => 'seguimiento',      'label' => 'Seguimiento de Equipo', 'icono' => 'monitoring',      'componente' => 'components/seguimiento/seguimiento.php',           'roles' => ['superdesarrollador']],
	// Cumplimiento de Cuota (2026-08-30): JW sube de vuelta el mismo Excel de
	// "Descargar Excel" (Historial), ya completado con venta real — este
	// módulo solo LEE los resultados que el propio Excel ya calculó (GANA
	// POR CATEGORÍA / GANA TOTAL / CUMPLIMIENTO), nunca los recalcula. Self
	// service (sube el superdesarrollador), mismo nivel de acceso que
	// Repositorios/Seguimiento.
	['id' => 'cumplimiento',     'label' => 'Cumplimiento de Cuota', 'icono' => 'fact_check',      'componente' => 'components/cumplimiento/cumplimiento.php',         'roles' => ['superdesarrollador']],
	// Liquidación oculta temporalmente (2026-08-25, pedido explícito del
	// usuario) — sigue en duda de negocio si el mecanismo de subir Excel +
	// matching automático es lo que el cliente realmente pidió (ver
	// CLAUDE.md "⚠️ REPLANTEO 2026-08-23" dentro de "Módulo Liquidación").
	// El código y los datos siguen intactos, esto solo la saca del sidebar
	// (nadie la ve, ningún rol). Para reactivarla: descomentar la línea de
	// abajo.
	// ['id' => 'liquidacion', 'label' => 'Liquidación', 'icono' => 'payments', 'componente' => 'components/liquidacion/liquidacion.php', 'roles' => ['superdesarrollador']],
	['id' => 'gestion-usuarios', 'label' => 'Gestión de Usuarios',   'icono' => 'manage_accounts', 'componente' => 'components/gestion-usuarios/gestion-usuarios.php', 'roles' => ['superdesarrollador']],
];
?>
