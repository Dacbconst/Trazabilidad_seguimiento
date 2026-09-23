<?php
require_once __DIR__.'/../../includes/actividades_datos.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
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
			<button type="button" class="ep-activity-item<?= $i === 0 ? ' selected' : '' ?>" data-id="<?= (int) $a['id'] ?>" data-render-id="<?= (int) ($a['render_id'] ?? $a['id']) ?>" data-actividad="<?= htmlspecialchars($a['label']) ?>" data-nombre="<?= htmlspecialchars($a['label']) ?>">
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
	<div id="ep-header-formulario" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
		<div>
			<div class="ep-eyebrow">Formulario</div>
			<h1 id="ep-seleccion-label" style="font-size:24px;margin-top:4px;"><?= htmlspecialchars($actividades[0]['label']) ?></h1>
		</div>
		<div style="display:flex;align-items:center;gap:10px;">
			<a href="index.php?vista=historial" class="ep-btn ep-btn-secondary" style="display:inline-flex;align-items:center;gap:8px;font-size:13px;padding:8px 14px;text-decoration:none;border-radius:10px;font-weight:600;" title="Ver reportes y métricas de campo generados">
				<?= ep_icon('clock', 15) ?>
				<span>Ver registros generados</span>
			</a>
		</div>
	</div>

	<?php if ($esAdmin): ?>
	<div id="ep-header-constructor" class="hidden">
		<div class="ep-eyebrow">Nueva actividad</div>
		<h1 style="font-size:24px;margin-top:4px;">Configurar actividad</h1>
	</div>
	<?php endif; ?>

	<!-- Pestañas móvil (Formulario / Fotos / Métricas) — ocultas en escritorio -->
	<div class="ep-mobile-tabs" id="epMobileTabs">
		<button type="button" class="ep-mobile-tab active" data-tab="formulario">
			<?= ep_icon('file', 15) ?>
			<span>Formulario</span>
		</button>
		<button type="button" class="ep-mobile-tab" data-tab="fotos">
			<?= ep_icon('camera', 15) ?>
			<span>Fotos</span>
			<span class="ep-mobile-tab-count" id="epMobileTabFotosCount">0</span>
		</button>
		<button type="button" class="ep-mobile-tab<?= !empty($actividades[0]['sin_estadisticas']) ? ' hidden' : '' ?>" data-tab="metricas" id="epMobileTabMetricas">
			<?= ep_icon('bar-chart', 15) ?>
			<span>Métricas</span>
		</button>
	</div>

	<?php
	// Las actividades copia apuntan a la misma plantilla que su origen — solo se renderiza UNA vez por origen real.
	$actividadesOriginales = array_filter($actividades, fn($a) => ($a['render_id'] ?? $a['id']) === $a['id']);
	?>
	<div class="ep-actividad-layout<?= !empty($actividades[0]['sin_estadisticas']) ? ' ep-actividad-layout-sin-stats' : '' ?>" id="ep-actividad-layout">
		<div id="ep-panel-formulario" class="ep-card">
			<?php foreach ($actividadesOriginales as $i => $actividad): ?>
				<div class="ep-formulario-actividad<?= $i === 0 ? '' : ' hidden' ?>" data-actividad-id="<?= (int) $actividad['id'] ?>">
					<?php include __DIR__.'/plantillas/'.$actividad['plantilla'].'.php'; ?>
				</div>
			<?php endforeach; ?>

			<!-- Banner de Flujo Continuo a Evidencias Fotográficas en Desktop -->
			<div class="ep-desktop-foto-flow-card" id="epDesktopFotoFlowCard">
				<div class="ep-desktop-flow-body">
					<div class="ep-desktop-flow-icon-box">
						<?= ep_icon('camera', 20) ?>
					</div>
					<div class="ep-desktop-flow-content">
						<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
							<span class="ep-desktop-flow-step-tag">Paso 2</span>
							<strong class="ep-desktop-flow-heading">Evidencia Fotográfica Obligatoria</strong>
							<span class="ep-desktop-flow-status-pill pendiente" id="epDesktopFlowBadge">Pendiente</span>
						</div>
						<p class="ep-desktop-flow-sub">
							Para auditar este reporte ante Epson, carga las fotos requeridas de campo.
						</p>
						<div class="ep-desktop-flow-meter-wrap">
							<div class="ep-desktop-flow-meter-track">
								<div class="ep-desktop-flow-meter-bar" id="epDesktopFotoProgressFill" style="width:0%;"></div>
							</div>
							<span class="ep-desktop-flow-meter-lbl" id="epDesktopFotoCount">0 fotos cargadas</span>
						</div>
					</div>
				</div>
				<button type="button" class="ep-btn-desktop-start-wizard" id="epBtnDesktopStartWizard">
					<?= ep_icon('camera', 15) ?>
					<span>Subir Fotos con Asistente</span>
					<?= ep_icon('arrow-right', 13) ?>
				</button>
			</div>

			<div class="ep-form-acciones-movil">
				<button type="button" class="ep-btn-siguiente-movil" id="epBtnIrAFotos">
					<span>Continuar a Evidencia Fotográfica</span>
					<?= ep_icon('arrow-right', 15) ?>
				</button>
			</div>

			<div class="ep-form-submit-row-desktop" style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px;">
				<button type="button" class="ep-btn-outline" id="epBtnGuardarBorrador">Guardar borrador</button>
				<button type="button" class="ep-btn-primary" id="epBtnEnviarRegistro">Enviar registro</button>
			</div>
		</div>

		<div id="ep-panel-estadisticas" class="ep-card">
			<div class="ep-eyebrow">Estadísticas</div>
			<?php foreach ($actividadesOriginales as $i => $actividad): ?>
				<div class="ep-estadisticas-actividad<?= $i === 0 ? '' : ' hidden' ?>" data-actividad-id="<?= (int) $actividad['id'] ?>" data-sin-estadisticas="<?= !empty($actividad['sin_estadisticas']) ? '1' : '0' ?>">
					<?php include __DIR__.'/plantillas-stats/'.$actividad['plantilla'].'.php'; ?>
				</div>
			<?php endforeach; ?>

			<div class="ep-metricas-acciones-movil">
				<button type="button" class="ep-btn-outline ep-btn-volver-movil" id="epBtnVolverAFotos">
					<?= ep_icon('arrow-left', 15) ?>
					<span>Volver a Fotos</span>
				</button>
				<button type="button" class="ep-btn-primary ep-btn-enviar-movil" id="epBtnEnviarDesdeMetricas">
					<span>Enviar registro</span>
				</button>
			</div>
		</div>

		<div class="ep-evidencia-wrap">
			<!-- Acceso rápido móvil al asistente guiado paso a paso -->
			<button type="button" class="ep-btn-reabrir-wizard" id="epBtnReabrirWizard">
				<?= ep_icon('camera', 16) ?>
				<span>Subir fotos paso a paso</span>
				<?= ep_icon('arrow-right', 14) ?>
			</button>

			<?php foreach ($actividadesOriginales as $i => $actividad):
				$epEvidenciaFotos = ep_fotos_requeridas($actividad['plantilla']);
				$epEvidenciaPrefix = 'a' . $actividad['id'];
			?>
				<div class="ep-evidencia-actividad<?= $i === 0 ? '' : ' hidden' ?>" data-actividad-id="<?= (int) $actividad['id'] ?>">
					<?php include __DIR__.'/partials/paso_evidencia.php'; ?>
				</div>
			<?php endforeach; ?>

			<div class="ep-fotos-acciones-movil">
				<button type="button" class="ep-btn-outline ep-btn-volver-movil" id="epBtnVolverAFormulario">
					<?= ep_icon('arrow-left', 15) ?>
					<span>Volver a Formulario</span>
				</button>
				<button type="button" class="ep-btn-siguiente-movil" id="epBtnIrAMetricas">
					<span id="epBtnIrAMetricasTexto">Revisar Métricas</span>
					<?= ep_icon('arrow-right', 15) ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Modal Asistente de Captura Fotográfica Paso a Paso (Móvil y Desktop) -->
	<div class="ep-wizard-overlay hidden" id="epWizardFotosOverlay" aria-modal="true" role="dialog">
		<div class="ep-wizard-sheet">
			<!-- Cabecera del asistente con contador y barra segmentada -->
			<div class="ep-wizard-head">
				<div class="ep-wizard-head-row">
					<div class="ep-wizard-badge-paso">
						<?= ep_icon('camera', 14) ?>
						<span id="epWizardPasoTexto">Foto 1 de 7</span>
					</div>
					<div class="ep-wizard-head-actions">
						<span class="ep-wizard-desktop-tag">Estudio Fotográfico Epson</span>
						<button type="button" class="ep-wizard-btn-cerrar" id="epWizardBtnCerrar" aria-label="Cerrar asistente">
							<?= ep_icon('close', 16) ?>
						</button>
					</div>
				</div>
				<div class="ep-wizard-track-segmentos" id="epWizardTrackSegmentos"></div>
			</div>

			<!-- Layout Desktop 2 Columnas / Móvil 1 Columna -->
			<div class="ep-wizard-main-grid">
				<!-- Panel Lateral de Requerimientos (Desktop) -->
				<aside class="ep-wizard-sidebar-checklist" id="epWizardSidebarChecklist">
					<div class="ep-wizard-sidebar-head">
						<strong>Requerimientos Obligatorios</strong>
						<span class="ep-wizard-sidebar-count" id="epWizardSidebarCount">0/3</span>
					</div>
					<div class="ep-wizard-sidebar-list" id="epWizardSidebarList">
						<!-- Items generados dinámicamente con JS -->
					</div>
					<div class="ep-wizard-sidebar-hint">
						<?= ep_icon('layers', 12) ?>
						<span>Arrastra fotos desde cualquier carpeta o WhatsApp Web.</span>
					</div>
				</aside>

				<!-- Escenario Central: Visor y Tira de Miniaturas -->
				<div class="ep-wizard-body">
					<div class="ep-wizard-info">
						<span class="ep-wizard-subtitulo">Requerimiento de Campo</span>
						<h3 class="ep-wizard-titulo" id="epWizardTituloFoto">Cargando...</h3>
					</div>

					<!-- Visor de captura con esquinas HUD fotográficas y Drag & Drop -->
					<div class="ep-wizard-visor-wrap">
						<div class="ep-wizard-visor" id="epWizardVisor">
							<span class="ep-visor-corner top-left"></span>
							<span class="ep-visor-corner top-right"></span>
							<span class="ep-visor-corner bottom-left"></span>
							<span class="ep-visor-corner bottom-right"></span>

							<!-- Estado vacío interactivo -->
							<div class="ep-wizard-visor-vacio" id="epWizardVisorVacio">
								<div class="ep-wizard-cam-circle">
									<?= ep_icon('camera', 30) ?>
								</div>
								<span class="ep-wizard-cam-label">Arrastra tu foto aquí o haz clic para explorar</span>
								<span class="ep-wizard-cam-sub">Soporta JPG, PNG, WEBP de alta resolución</span>
							</div>

							<!-- Estado con imagen capturada -->
							<div class="ep-wizard-visor-preview hidden" id="epWizardVisorPreview">
								<img src="" id="epWizardPreviewImg" alt="Foto capturada">
								<div class="ep-wizard-preview-overlay">
									<span class="ep-wizard-check-chip">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
										<span>Foto lista</span>
									</span>
									<button type="button" class="ep-wizard-btn-cambiar" id="epWizardBtnCambiar">
										<?= ep_icon('camera', 13) ?>
										<span>Cambiar foto</span>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Tira de miniaturas interactivas para navegación rápida -->
					<div class="ep-wizard-reel-scroll">
						<div class="ep-wizard-reel" id="epWizardReel"></div>
					</div>
				</div>
			</div>

			<!-- Barra de navegación inferior -->
			<div class="ep-wizard-footer">
				<button type="button" class="ep-btn-outline ep-wizard-btn-ant" id="epWizardBtnAnterior">
					<?= ep_icon('arrow-left', 14) ?>
					<span>Anterior</span>
				</button>
				<button type="button" class="ep-btn-primary ep-wizard-btn-sig" id="epWizardBtnSiguiente">
					<span id="epWizardBtnSigTexto">Siguiente foto</span>
					<?= ep_icon('arrow-right', 14) ?>
				</button>
			</div>
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
						<?php foreach ($actividades as $a): ?>
							<option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['label']) ?></option>
						<?php endforeach; ?>
					</select>
					<span style="font-size:12px;color:var(--color-text-muted);">El nuevo reporte usa el mismo formulario, cálculo y formato de fotos que la lógica elegida.</span>
				</div>

				<div id="ep-nueva-error" class="hidden" style="color:var(--color-danger);font-size:12px;"></div>

				<div style="display:flex;gap:12px;margin-top:8px;">
					<button type="button" class="ep-btn-outline" id="ep-cancelar-nueva-actividad">Cancelar</button>
					<button type="button" class="ep-btn-primary" id="ep-guardar-actividad-btn">Guardar actividad</button>
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
				<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
					<div class="ep-eyebrow">Vista previa · formulario</div>
					<span class="ep-hist-badge" style="background:#EBF1FD;color:var(--color-primary);border-color:#CAD9F8;font-size:11px;">Solo lectura</span>
				</div>
				<h3 id="ep-preview-form-titulo" style="font-size:18px;margin-top:8px;">Nombre de la actividad</h3>
				<p style="font-size:12px;color:var(--color-text-muted);margin:4px 0 0;">Así se ve el formulario real de esta lógica — idéntico al diseño en producción.</p>
				<div id="ep-preview-form-campos" class="ep-preview-formulario-real" style="margin-top:16px;"></div>
			</div>

			<div class="ep-builder-preview-divider"></div>

			<div>
				<div class="ep-eyebrow">Vista previa · evidencia fotográfica</div>
				<div id="ep-preview-form-fotos" style="margin-top:12px;"></div>
			</div>
		</div>
	</div>
	</div>
	<?php endif; ?>
</main>

<?php if ($esAdmin): ?>
<script>
	// Mockup: lógicas disponibles para que "Nueva actividad" arme su vista previa en vivo.
	window.EP_LOGICAS = <?= json_encode(array_combine(array_column($actividades, 'id'), array_map(fn($a) => ['label' => $a['label'], 'plantilla' => $a['plantilla'], 'campos' => $a['campos'], 'fotos' => ep_fotos_requeridas($a['plantilla'])], $actividades)), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
