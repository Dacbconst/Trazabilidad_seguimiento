<?php
require_once __DIR__.'/../../includes/actividades_datos.php';
$actividades = ep_actividades();
$esAdmin = ep_rol_actual() === 'admin';
?>
<aside class="ep-side">
	<div>
		<div class="ep-eyebrow">Tipo de gestión</div>
		<h2 style="font-size:18px;margin-top:4px;">Actividades</h2>
	</div>

	<div class="ep-search-wrap">
		<?= ep_icon('search', 16) ?>
		<input class="ep-input" type="text" id="ep-buscar-actividad" placeholder="Buscar actividad">
	</div>

	<div id="ep-lista-actividades" style="display:flex;flex-direction:column;gap:8px;">
		<?php foreach ($actividades as $i => $a): ?>
			<button type="button" class="ep-activity-item<?= $i === 0 ? ' selected' : '' ?>" data-id="<?= (int) $a['id'] ?>" data-actividad="<?= htmlspecialchars($a['label']) ?>" data-nombre="<?= htmlspecialchars($a['label']) ?>">
				<span class="ep-activity-icon"><?= ep_icon('grid', 14) ?></span>
				<span class="ep-activity-label"><?= htmlspecialchars($a['label']) ?></span>
				<?php if ($a['badge']): ?><span class="ep-activity-badge"><?= htmlspecialchars($a['badge']) ?></span><?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php if ($esAdmin): ?>
		<button type="button" id="ep-nueva-actividad-btn" class="ep-add-activity-btn">
			<?= ep_icon('plus', 16) ?>
			Nueva actividad
		</button>
	<?php endif; ?>
</aside>

<main class="ep-content">
	<div id="ep-header-formulario">
		<div class="ep-eyebrow">Formulario</div>
		<h1 id="ep-seleccion-label" style="font-size:24px;margin-top:4px;"><?= htmlspecialchars($actividades[0]['label']) ?></h1>
	</div>

	<?php if ($esAdmin): ?>
	<div id="ep-header-constructor" class="hidden">
		<div class="ep-eyebrow">Nueva actividad</div>
		<h1 style="font-size:24px;margin-top:4px;">Configurar actividad</h1>
	</div>
	<?php endif; ?>

	<div class="ep-actividad-layout">
		<div id="ep-panel-formulario" class="ep-card">
			<?php foreach ($actividades as $i => $actividad): ?>
				<div class="ep-formulario-actividad<?= $i === 0 ? '' : ' hidden' ?>" data-actividad-id="<?= (int) $actividad['id'] ?>">
					<?php include __DIR__.'/plantillas/'.$actividad['plantilla'].'.php'; ?>
				</div>
			<?php endforeach; ?>

			<div style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px;">
				<button type="button" class="ep-btn-outline">Guardar borrador</button>
				<button type="button" class="ep-btn-primary">Enviar registro</button>
			</div>
		</div>

		<div id="ep-panel-estadisticas" class="ep-card">
			<div class="ep-eyebrow">Estadísticas</div>
			<?php foreach ($actividades as $i => $actividad): ?>
				<div class="ep-estadisticas-actividad<?= $i === 0 ? '' : ' hidden' ?>" data-actividad-id="<?= (int) $actividad['id'] ?>">
					<?php include __DIR__.'/plantillas-stats/'.$actividad['plantilla'].'.php'; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ($esAdmin): ?>
	<div id="ep-panel-constructor" class="hidden">
	<div class="ep-builder-grid">

		<div class="ep-builder-left">
			<div class="ep-builder-form ep-card">
				<div class="ep-eyebrow">Nuevo botón</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label">Nombre de la actividad</label>
					<input class="ep-input" type="text" id="ep-nueva-nombre" placeholder="Ej. Actividades Back to School" autocomplete="off">
				</div>

				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label">Lógica a replicar</label>
					<select class="ep-input" id="ep-nueva-logica">
						<?php foreach ($actividades as $i => $a): ?>
							<option value="<?= $i ?>"><?= htmlspecialchars($a['label']) ?></option>
						<?php endforeach; ?>
					</select>
					<span style="font-size:12px;color:var(--color-text-muted);">El nuevo reporte usa el mismo formulario, cálculo y formato de fotos que la lógica elegida.</span>
				</div>

				<div style="display:flex;gap:12px;margin-top:8px;">
					<button type="button" class="ep-btn-outline" id="ep-cancelar-nueva-actividad">Cancelar</button>
					<button type="button" class="ep-btn-primary">Guardar actividad</button>
				</div>
			</div>

			<div class="ep-gestion-actividades ep-card">
				<div class="ep-eyebrow">Eliminar / desactivar botones</div>
				<p style="margin:4px 0 0;font-size:12px;color:var(--color-text-muted);">Desactiva una actividad para ocultarla temporalmente del listado del usuario, o elimínala si ya no se usa.</p>

				<div id="ep-gestion-lista" style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">
					<?php foreach ($actividades as $a): ?>
						<div class="ep-gestion-fila" data-id="<?= (int) $a['id'] ?>">
							<span class="ep-activity-icon"><?= ep_icon('grid', 14) ?></span>
							<span class="ep-gestion-nombre"><?= htmlspecialchars($a['label']) ?></span>

							<label class="ep-switch" title="Activar / desactivar">
								<input type="checkbox" class="ep-gestion-switch" checked>
								<span class="ep-switch-slider"></span>
							</label>

							<button type="button" class="ep-gestion-eliminar" data-id="<?= (int) $a['id'] ?>" aria-label="Eliminar actividad">
								<?= ep_icon('trash', 15) ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="ep-builder-preview ep-card">
			<div>
				<div class="ep-eyebrow">Vista previa · botón</div>
				<div class="ep-activity-item selected" style="margin-top:10px;pointer-events:none;">
					<span class="ep-activity-icon"><?= ep_icon('grid', 14) ?></span>
					<span class="ep-activity-label" id="ep-preview-boton-label">Nombre de la actividad</span>
					<span class="ep-activity-badge">Nuevo</span>
				</div>
			</div>

			<div class="ep-builder-preview-divider"></div>

			<div>
				<div class="ep-eyebrow">Vista previa · formulario</div>
				<h3 id="ep-preview-form-titulo" style="font-size:18px;margin-top:8px;">Nombre de la actividad</h3>
				<div id="ep-preview-form-campos" style="display:flex;flex-direction:column;gap:12px;margin-top:16px;"></div>
			</div>
		</div>
	</div>
	</div>
	<?php endif; ?>
</main>

<?php if ($esAdmin): ?>
<script>
	// Mockup: lógicas disponibles para que "Nueva actividad" arme su vista previa en vivo.
	window.EP_LOGICAS = <?= json_encode(array_map(fn($a) => ['label' => $a['label'], 'campos' => $a['campos']], $actividades), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
