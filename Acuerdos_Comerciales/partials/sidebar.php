<nav class="ac-sidebar" id="acSidebar">
	<div class="ac-sidebar-brand">
		<div class="ac-sidebar-brand-row">
			<span class="material-symbols-outlined ac-sidebar-brand-icon">corporate_fare</span>
			<h2 class="ac-sidebar-brand-text">Gestión Comercial</h2>
			<button type="button" id="sidebarToggle" class="ac-sidebar-toggle" title="Mostrar/ocultar menú">
				<span class="material-symbols-outlined">chevron_left</span>
			</button>
		</div>
		<p class="ac-sidebar-version">v1.0</p>
	</div>

	<ul class="ac-sidebar-nav">
		<?php foreach ($secciones as $i => $seccion): ?>
			<?php if (!rolPermitido($seccion['roles'])) continue; ?>
			<li class="<?= $i === 0 ? 'active' : '' ?>">
				<a href="#sec-<?= $seccion['id'] ?>" data-toggle="section" title="<?= htmlspecialchars($seccion['label']) ?>">
					<span class="material-symbols-outlined"><?= $seccion['icono'] ?></span>
					<span class="ac-nav-label"><?= htmlspecialchars($seccion['label']) ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="ac-sidebar-footer">
		<a href="logout.php" class="ac-sidebar-logout" title="Cerrar Sesión">
			<span class="material-symbols-outlined">logout</span>
			<span class="ac-nav-label">Cerrar Sesión</span>
		</a>
		<p class="ac-sidebar-watermark">ALICORP</p>
	</div>
</nav>
