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
    ['id' => 'agendamientos', 'label' => 'Agendamientos',   'icono' => 'calendar',  'componente' => 'components/agendamiento/agendamientos.php'],
    ['id' => 'proforma',      'label' => 'Proforma',        'icono' => 'file',      'componente' => 'components/proforma/proforma.php'],
    ['id' => 'factura',       'label' => 'Factura',         'icono' => 'usd',       'componente' => 'components/factura/factura.php'],
    ['id' => 'estado-flujo', 'label' => 'Estado de Flujo', 'icono' => 'random', 'componente' => 'components/estado-flujo/estado-flujo.php'],
    ['id' => 'contactados',   'label' => 'Contactados',     'icono' => 'earphone',  'componente' => 'components/contactados/contactados.php'],
];
?>
