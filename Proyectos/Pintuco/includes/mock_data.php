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
    ['id' => 'flujo-comercial', 'label' => 'Flujo Comercial', 'icono' => 'random', 'componente' => 'components/flujo-comercial/flujo-comercial.php'],
];
?>
