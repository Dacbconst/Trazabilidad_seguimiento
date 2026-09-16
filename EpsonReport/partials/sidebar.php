<nav class="ep-sidebar" id="epSidebar" title="Clic en zona vacía: mostrar/ocultar menú">
	<div class="ep-sidebar-brand">
		<div class="ep-sidebar-brand-row">
			<div class="ep-brand-mark">ER</div>
			<span class="ep-sidebar-brand-text">EpsonReport</span>
		</div>
	</div>

	<ul class="ep-sidebar-nav">
		<?php foreach ($secciones as $id => $s): ?>
			<li class="<?= $id === $vista ? 'active' : '' ?>">
				<a href="index.php?vista=<?= urlencode($id) ?>" title="<?= htmlspecialchars($s['label']) ?>">
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
</nav>
