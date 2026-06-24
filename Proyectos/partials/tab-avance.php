<div class="stat-row">
    <div class="stat-item">
        <div class="num"><?= $resumen['total'] ?></div>
        <div class="label">Total Promotores</div>
    </div>
    <div class="stat-sep"></div>
    <div class="stat-item">
        <div class="num"><?= $resumen['realizados'] ?></div>
        <div class="label">Realizados</div>
    </div>
    <div class="stat-sep"></div>
    <div class="stat-item">
        <div class="num"><?= $resumen['sin_realizar'] ?></div>
        <div class="label">Sin realizar</div>
    </div>
    <div class="stat-sep"></div>
    <div class="stat-item">
        <div class="num"><?= $resumen['comisionan'] ?></div>
        <div class="label">Comisionan</div>
    </div>
    <div class="stat-sep"></div>
    <div class="stat-item">
        <div class="num"><?= $resumen['no_comisionan'] ?></div>
        <div class="label">No Comisionan</div>
    </div>

    <div class="progress-widget">
        <div class="pct-row">
            <span class="pct-label">Progreso</span>
            <span class="pct-value"><?= $resumen['progreso'] ?>%</span>
        </div>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" style="width: <?= $resumen['progreso'] ?>%;"></div>
        </div>
        <div class="caption">Meta vs Avance</div>
    </div>
</div>

<div class="promotor-list-header">
    <span style="flex:1;">Promotor</span>
    <span class="col-pdv">PDV</span>
    <span class="col-ciudad">Ciudad</span>
    <span class="col-estado">Estado</span>
    <span class="col-comisiona">Comisiona</span>
</div>

<div class="promotor-list">
    <?php foreach ($promotores as $p): ?>
        <?php include $cuenta_dir . '/components/promotor-row.php'; ?>
    <?php endforeach; ?>
</div>
