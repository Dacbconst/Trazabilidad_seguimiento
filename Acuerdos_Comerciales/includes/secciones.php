<?php
// Secciones del sidebar. "roles" es la lista exacta de roles que ven cada
// módulo (se valida con rolPermitido() de functions.php). No es jerárquico.
//
//   superdesarrollador -> los 4 módulos
//   desarrollador       -> Registrar, Historial
$secciones = [
	['id' => 'registrar',        'label' => 'Registrar Acuerdo PDV', 'icono' => 'dashboard',       'componente' => 'components/registrar/registrar.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'historial',        'label' => 'Historial de Acuerdos', 'icono' => 'description',     'componente' => 'components/historial/historial.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'repositorios',     'label' => 'Repositorios',          'icono' => 'inventory_2',     'componente' => 'components/repositorios/repositorios.php',         'roles' => ['superdesarrollador']],
	['id' => 'liquidacion',      'label' => 'Liquidación',           'icono' => 'payments',        'componente' => 'components/liquidacion/liquidacion.php',           'roles' => ['superdesarrollador']],
	['id' => 'gestion-usuarios', 'label' => 'Gestión de Usuarios',   'icono' => 'manage_accounts', 'componente' => 'components/gestion-usuarios/gestion-usuarios.php', 'roles' => ['superdesarrollador']],
];
?>
