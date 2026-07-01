<?php
/**
 * COMPONENTE: promotor-row.php
 * Fila de la lista de PDVs en la pestaña Avance %.
 * Variable requerida: $p = ['id','nombre','pdv','ciudad','estado','comisiona']
 *   $p['estado']    — 'ejecutando' | 'cerrados' | 'pendiente' | 'vencida' | 'cancelada'
 *   $p['comisiona'] — 'si' | 'no' | 'na'
 */
$estadoLabels = [
    'ejecutando' => 'En curso',
    'cerrados'   => 'Completado',
    'pendiente'  => 'Pendiente',
    'vencida'    => 'Vencido',
    'cancelada'  => 'Cancelado',
];
$comisionaIcon = [
    'si' => ['glyphicon-ok-circle', 'comisiona-si'],
    'no' => ['glyphicon-remove-circle', 'comisiona-no'],
    'na' => ['glyphicon-unchecked', 'comisiona-na'],
];
$estado   = isset($estadoLabels[$p['estado']]) ? $p['estado'] : 'pendiente';
$comisiona = isset($comisionaIcon[$p['comisiona']]) ? $p['comisiona'] : 'na';
[$icon, $iconClass] = $comisionaIcon[$comisiona];
?>
<div class="promotor-row">
    <div class="col-nombre">
        <div class="avatar"><i class="glyphicon glyphicon-user"></i></div>
        <?= htmlspecialchars($p['nombre']) ?>
    </div>
    <div class="col-pdv"><?= htmlspecialchars($p['pdv']) ?></div>
    <div class="col-ciudad"><?= htmlspecialchars($p['ciudad']) ?></div>
    <div class="col-estado">
        <span class="badge-estado badge-<?= $estado ?>"><?= $estadoLabels[$estado] ?></span>
    </div>
    <div class="col-comisiona">
        <i class="glyphicon <?= $icon ?> comisiona-icon <?= $iconClass ?>"></i>
    </div>
</div>
