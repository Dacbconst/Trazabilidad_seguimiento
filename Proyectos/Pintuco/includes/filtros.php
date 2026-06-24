<?php
// Funciones de filtro compartidas por todos los getters de Univeler.
// Incluir con: include_once '../includes/filtros.php';

function filtroTipo(string $valor): string {
    if ($valor === '.' || $valor === '') return "1=1";
    if ($valor === 'Sin Tipo')          return "ins.tipo IS NULL";
    if ($valor === 'GESTIONADA')        return "(TRIM(ins.tipo) IN ('GESTIONADA','GESTIONADAS'))";
    if ($valor === 'PAGADA')            return "(TRIM(ins.tipo) IN ('PAGADA','PAGADAS'))";
    return "TRIM(ins.tipo) = '" . $valor . "'";
}

function filtroCategoria(string $valor): string {
    if ($valor === '.' || $valor === '') return "1=1";
    return "ins.categoria = '$valor'";
}
