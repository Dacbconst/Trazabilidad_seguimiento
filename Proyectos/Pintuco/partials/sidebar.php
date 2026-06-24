<nav id="sidebar">
    <div class="sidebar-brand">Proyectos y Obras</div>

    <div class="sidebar-user">
        <div class="avatar"><i class="glyphicon glyphicon-user"></i></div>
        <div>
            <div class="name"><?= htmlspecialchars($usuario_actual['nombre']) ?></div>
            <div class="meta">
                Último Ingreso: <?= htmlspecialchars($usuario_actual['ultimo_ingreso']) ?><br>
                Actualizado: <?= htmlspecialchars($usuario_actual['actualizado']) ?>
            </div>
        </div>
    </div>

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
</nav>
