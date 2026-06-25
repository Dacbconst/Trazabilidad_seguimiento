<div class="topbar">
    <div class="filter-group">
        <label>Promotor</label>
        <select class="form-control input-sm" id="filtroPromotor" name="promotor">
            <option value="">Seleccione</option>
            <?php foreach ($promotores as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label>Búsqueda Rápida</label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="busquedaRapida" placeholder="Buscar...">
            <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
        </div>
    </div>

    <div class="filter-group">
        <label>Mes</label>
        <select class="form-control input-sm" id="filtroMes" name="mes">
            <?php foreach ($meses as $valor => $nombre): ?>
            <option value="<?= $valor ?>" <?= $valor === $mes_actual ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="button" id="btnActualizar" class="btn btn-actualizar">ACTUALIZAR</button>
</div>
