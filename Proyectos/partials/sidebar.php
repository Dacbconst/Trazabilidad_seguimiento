<button type="button" id="sidebarCollapse" class="sidebar-toggle" title="Mostrar/ocultar menú">
    <i class="glyphicon glyphicon-chevron-left"></i>
</button>

<nav id="sidebar">
    <div class="sidebar-brand">Proyectos y Obras</div>

    <div class="sidebar-user">
        <div class="avatar"><i class="glyphicon glyphicon-user"></i></div>
        <div>
            <div class="name"><?= htmlspecialchars($usuario_actual['nombre']) ?></div>
        </div>
    </div>

    <div class="sidebar-cuenta">
        <label for="selectCuenta">Cuenta</label>
        <select id="selectCuenta" class="form-control input-sm" onchange="location.href='?cuenta='+this.value">
            <option value="">Seleccione</option>
            <?php foreach ($cuentas_disponibles as $codigo => $detalle): ?>
            <option value="<?= htmlspecialchars($codigo) ?>" <?= $codigo === $cuenta_actual ? 'selected' : '' ?>>
                <?= htmlspecialchars($detalle) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($cuenta_habilitada): ?>
    <ul class="sidebar-nav">
        <?php foreach ($secciones as $i => $seccion): ?>
        <li class="<?= $i === 0 ? 'active' : '' ?>">
            <a href="#sec-<?= $seccion['id'] ?>" data-toggle="section">
                <i class="glyphicon glyphicon-<?= $seccion['icono'] ?>"></i>
                <?= htmlspecialchars($seccion['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</nav>
