<?php
/**
 * COMPONENTE: promotor-row.php
 * Fila de la lista de promotores en la pestaña Avance %.
 * Variable requerida en scope: $p = ['id','nombre','estado','comisiona']
 *   $p['estado']    string  — 'ejecutando' | 'cerrados' | 'pendiente'
 *   $p['comisiona'] string  — 'si' | 'no' | 'na'
 */
$estadoLabels = [
    'ejecutando' => 'Ejecutando',
    'cerrados'   => 'Cerrados',
    'pendiente'  => 'Pendiente',
];
$comisionaIcon = [
    'si' => ['glyphicon-ok-circle', 'comisiona-si'],
    'no' => ['glyphicon-remove-circle', 'comisiona-no'],
    'na' => ['glyphicon-unchecked', 'comisiona-na'],
];
[$icon, $iconClass] = $comisionaIcon[$p['comisiona']];
?>
<div class="promotor-row">
    <div class="col-nombre">
        <div class="avatar"><i class="glyphicon glyphicon-user"></i></div>
        <?= htmlspecialchars($p['nombre']) ?>
    </div>
    <div class="col-estado">
        <span class="badge-estado badge-<?= $p['estado'] ?>"><?= $estadoLabels[$p['estado']] ?></span>
    </div>
    <div class="col-comisiona">
        <i class="glyphicon <?= $icon ?> comisiona-icon <?= $iconClass ?>"></i>
    </div>
</div>
