<?php
// Secciones del sidebar. "roles" es la lista exacta de roles que ven cada
// módulo (se valida con rolPermitido() de functions.php). No es jerárquico.
//
//   superdesarrollador -> los 4 módulos
//   desarrollador       -> Registrar, Historial, Auditoría
$secciones = [
	['id' => 'registrar',        'label' => 'Registrar Acuerdo PDV', 'icono' => 'dashboard',       'componente' => 'components/registrar/registrar.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'historial',        'label' => 'Historial de Acuerdos', 'icono' => 'description',     'componente' => 'components/historial/historial.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'auditoria',        'label' => 'Auditoría',             'icono' => 'verified_user',   'componente' => 'components/auditoria/auditoria.php',               'roles' => ['desarrollador', 'superdesarrollador']],
	['id' => 'gestion-usuarios', 'label' => 'Gestión de Usuarios',   'icono' => 'manage_accounts', 'componente' => 'components/gestion-usuarios/gestion-usuarios.php', 'roles' => ['superdesarrollador']],
];
?>
