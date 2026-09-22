<nav class="ep-sidebar" id="epSidebar" title="Clic en zona vacía: mostrar/ocultar menú">
	<button type="button" class="ep-sidebar-close-btn" id="epSidebarCloseBtn" aria-label="Cerrar menú">
		<?= ep_icon('close', 18) ?>
	</button>
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

	<!-- Submenú Actividades en celular -->
	<div class="ep-sidebar-submenu" id="epSidebarSubActividades">
		<div class="ep-sidebar-sub-header">
			<button type="button" class="ep-sidebar-sub-back" id="epSidebarSubVolver" aria-label="Volver al menú">
				<?= ep_icon('arrow-left', 15) ?>
				<span>Volver</span>
			</button>
			<button type="button" class="ep-sidebar-sub-close" id="epSidebarSubCloseBtn" aria-label="Cerrar menú">
				<?= ep_icon('close', 16) ?>
			</button>
		</div>

		<div class="ep-sidebar-sub-titleblock">
			<div class="ep-sidebar-sub-eyebrow">Tipo de Gestión</div>
			<h3 class="ep-sidebar-sub-heading">Actividades</h3>
			<p class="ep-sidebar-sub-hint">Selecciona la actividad a reportar</p>
		</div>

		<div class="ep-sidebar-sub-list" id="epSidebarSubList">
			<?php foreach ($actividadesNav as $i => $a): ?>
				<button type="button" class="ep-sidebar-sub-item<?= $i === 0 ? ' selected' : '' ?>" data-id="<?= (int) $a['id'] ?>" data-render-id="<?= (int) ($a['render_id'] ?? $a['id']) ?>" data-nombre="<?= htmlspecialchars($a['label']) ?>">
					<span class="ep-sidebar-sub-item-icon"><?= ep_icon('grid', 15) ?></span>
					<div class="ep-sidebar-sub-item-content">
						<span class="ep-sidebar-sub-item-name"><?= htmlspecialchars($a['label']) ?></span>
						<?php if (!empty($a['badge'])): ?>
							<span class="ep-sidebar-sub-item-badge"><?= htmlspecialchars($a['badge']) ?></span>
						<?php endif; ?>
					</div>
					<span class="ep-sidebar-sub-item-arrow"><?= ep_icon('arrow-right', 13) ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
</nav>
