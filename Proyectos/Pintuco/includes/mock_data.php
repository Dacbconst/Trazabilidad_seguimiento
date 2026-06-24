<?php
/**
 * Datos de ejemplo (maqueta visual) para "Proyectos y Obras".
 * Reemplazar por consultas reales (vi_* / getters/) cuando se conecte la BD.
 */

$usuario_actual = [
    'nombre'          => 'Kattya Pozo',
    'ultimo_ingreso'  => '17/06/2026 12:19:50',
    'actualizado'     => 'Miércoles 17_ 12:20:55',
];

// ================================================================
// SECCIONES DEL SIDEBAR — todas viven en la misma página (sin rutas
// propias) y se togglean por JS, igual mecanismo que el stepper de
// pestañas de "Principal".
// 'principal' es especial: no usa 'componente', envuelve el stepper
// ($tabs) que ya existe en index.php.
// Para agregar una sección nueva: crear su archivo en components/ y
// sumar una entrada aquí.
// ================================================================
$secciones = [
    ['id' => 'principal',     'label' => 'Principal',       'icono' => 'home'],
    ['id' => 'contactados',   'label' => 'Contactados',     'icono' => 'earphone',  'componente' => 'components/contactados.php'],
    ['id' => 'agendamientos', 'label' => 'Agendamientos',   'icono' => 'calendar',  'componente' => 'components/agendamientos.php'],
    ['id' => 'proforma',      'label' => 'Proforma',        'icono' => 'file',      'componente' => 'components/proforma.php'],
    ['id' => 'estado-flujo',  'label' => 'Estado de Flujo', 'icono' => 'random',    'componente' => 'components/estado-flujo.php'],
];

$promotores = [
    ['id' => 1,  'nombre' => 'Promotor 1',  'estado' => 'ejecutando', 'comisiona' => 'si'],
    ['id' => 2,  'nombre' => 'Promotor 2',  'estado' => 'cerrados',   'comisiona' => 'no'],
    ['id' => 3,  'nombre' => 'Promotor 3',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 4,  'nombre' => 'Promotor 4',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 5,  'nombre' => 'Promotor 5',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 6,  'nombre' => 'Promotor 6',  'estado' => 'cerrados',   'comisiona' => 'si'],
    ['id' => 7,  'nombre' => 'Promotor 7',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 8,  'nombre' => 'Promotor 8',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 9,  'nombre' => 'Promotor 9',  'estado' => 'pendiente',  'comisiona' => 'na'],
    ['id' => 10, 'nombre' => 'Promotor 10', 'estado' => 'pendiente',  'comisiona' => 'na'],
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
];
$mes_actual = '2026-06';
