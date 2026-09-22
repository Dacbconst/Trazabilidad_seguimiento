<nav class="ep-sidebar" id="epSidebar" title="Clic en zona vacía: mostrar/ocultar menú">
	<div class="ep-sidebar-menu-principal" id="epSidebarMenuPrincipal">
		<div class="ep-sidebar-user">
			<div class="ep-sidebar-avatar"></div>
			<span class="ep-sidebar-user-name ep-nav-label"><?= htmlspecialchars($_SESSION['usuario'] ?? '') ?></span>
		</div>

		<ul class="ep-sidebar-nav">
			<?php foreach ($secciones as $id => $s): ?>
				<li class="<?= $id === $vista ? 'active' : '' ?>">
					<a href="index.php?vista=<?= urlencode($id) ?>" title="<?= htmlspecialchars($s['label']) ?>"<?= $id === 'actividades' ? ' data-abre-submenu="epSidebarSubActividades"' : '' ?>>
						<?= ep_icon($s['icon']) ?>
						<span class="ep-nav-label"><?= htmlspecialchars($s['label']) ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="ep-sidebar-footer">
			<a href="logout.php" class="ep-sidebar-logout" title="Cerrar sesión">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
				<span class="ep-nav-label">Cerrar sesión</span>
			</a>
		</div>
	</div>

	<!-- Submenú "Actividades" — solo en celular, mismo diseño que el panel de escritorio (.ep-side / .ep-activity-item). -->
	<div class="ep-sidebar-submenu" id="epSidebarSubActividades">
		<button type="button" class="ep-sidebar-submenu-volver" id="epSidebarSubVolver">
			<?= ep_icon('chevron', 16) ?>
			Volver
		</button>
		<div class="ep-eyebrow">Tipo de Gestión</div>
		<div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;">
			<?php foreach ($actividadesNav as $a): ?>
				<a class="ep-activity-item" href="index.php?vista=actividades">
					<span class="ep-activity-icon"><?= ep_icon('grid', 14) ?></span>
					<span class="ep-activity-label"><?= htmlspecialchars($a['label']) ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</nav>
