<?php
/**
 * Datos de la cuenta Pintuco para el módulo Principal.
 * $usuario_actual y $mysqli vienen del shell (index.php).
 */

// ================================================================
// SECCIONES DEL SIDEBAR
// ================================================================
$secciones = [
    ['id' => 'principal',     'label' => 'Principal',       'icono' => 'home'],
    ['id' => 'contactados',   'label' => 'Contactados',     'icono' => 'earphone',  'componente' => 'components/contactados/contactados.php'],
    ['id' => 'agendamientos', 'label' => 'Agendamientos',   'icono' => 'calendar',  'componente' => 'components/agendamiento/agendamientos.php'],
    ['id' => 'proforma',      'label' => 'Proforma',        'icono' => 'file',      'componente' => 'components/proforma/proforma.php'],
    ['id' => 'estado-flujo',  'label' => 'Estado de Flujo', 'icono' => 'random',    'componente' => 'components/estado-flujo/estado-flujo.php'],
];

$promotores = [
    ['id' => 1,  'nombre' => 'Promotor 1',  'pdv' => 'KYWI CARACOL',   'ciudad' => 'Guayaquil', 'estado' => 'ejecutando', 'comisiona' => 'si'],
    ['id' => 2,  'nombre' => 'Promotor 2',  'pdv' => 'KYWI AMBATO',    'ciudad' => 'Ambato',    'estado' => 'cerrados',   'comisiona' => 'no'],
    ['id' => 3,  'nombre' => 'Promotor 3',  'pdv' => 'KYWI ALMENDROS', 'ciudad' => 'Guayaquil', 'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 4,  'nombre' => 'Promotor 4',  'pdv' => 'KYWI CARCELEN',  'ciudad' => 'Quito',     'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 5,  'nombre' => 'Promotor 5',  'pdv' => 'KYWI CARACOL',   'ciudad' => 'Guayaquil', 'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 6,  'nombre' => 'Promotor 6',  'pdv' => 'KYWI AMBATO',    'ciudad' => 'Ambato',    'estado' => 'cerrados',   'comisiona' => 'si'],
    ['id' => 7,  'nombre' => 'Promotor 7',  'pdv' => 'KYWI ALMENDROS', 'ciudad' => 'Guayaquil', 'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 8,  'nombre' => 'Promotor 8',  'pdv' => 'KYWI CARCELEN',  'ciudad' => 'Quito',     'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 9,  'nombre' => 'Promotor 9',  'pdv' => 'KYWI CARACOL',   'ciudad' => 'Guayaquil', 'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 10, 'nombre' => 'Promotor 10', 'pdv' => 'KYWI AMBATO',    'ciudad' => 'Ambato',    'estado' => 'pendiente',  'comisiona' => 'na'],
];

$resumen = [
    'total'         => count($promotores),
    'realizados'    => count(array_filter($promotores, fn($p) => $p['estado'] === 'cerrados')),
    'sin_realizar'  => count(array_filter($promotores, fn($p) => $p['estado'] === 'pendiente')),
    'comisionan'    => count(array_filter($promotores, fn($p) => $p['comisiona'] === 'si')),
    'no_comisionan' => count(array_filter($promotores, fn($p) => $p['comisiona'] === 'no')),
    'progreso'      => 25,
];

$meses = [
    '2026-01' => 'Enero 2026',
    '2026-02' => 'Febrero 2026',
    '2026-03' => 'Marzo 2026',
    '2026-04' => 'Abril 2026',
    '2026-05' => 'Mayo 2026',
    '2026-06' => 'Junio 2026',
    '2026-07' => 'Julio 2026',
];
$mes_actual = '2026-07';
?>
