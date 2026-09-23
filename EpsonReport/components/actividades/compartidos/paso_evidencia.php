<?php
// Bloque "Evidencia Fotográfica" — Tarjeta independiente ubicada debajo del formulario y estadísticas.
// Requiere $epEvidenciaFotos y $epEvidenciaPrefix (definidos por actividad en actividades.php).
if (empty($epEvidenciaFotos)) return;
$totalReqFotos = count($epEvidenciaFotos);
?>
<div class="ep-evidencia-bloque-card ep-evidencia-actividad" data-actividad-id="<?= (int) ($actividad['id'] ?? 1) ?>">
	
	<!-- Cabecera de la Card de Evidencia -->
	<div class="ep-evidencia-card-head">
		<div class="ep-evidencia-head-info">
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<div class="ep-evidencia-icon-badge">
					<?= ep_icon('camera', 18) ?>
				</div>
				<div>
					<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
						<h2 style="margin:0;font-size:16px;font-weight:700;color:var(--color-ink);">Evidencia Fotográfica Obligatoria</h2>
						<span class="ep-desktop-flow-status-pill pendiente" id="epDesktopFlowBadge">Pendiente (<?= $totalReqFotos ?> faltantes)</span>
					</div>
					<p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">
						Fotografías requeridas para auditar y justificar la actividad ante Epson.
					</p>
				</div>
			</div>
		</div>

		<div class="ep-evidencia-head-actions">
			<!-- Barra de progreso interactiva del paso -->
			<div class="ep-desktop-flow-meter-wrap">
				<div class="ep-desktop-flow-meter-track">
					<div class="ep-desktop-flow-meter-bar" id="epDesktopFotoProgressFill" style="width:0%;"></div>
				</div>
				<span class="ep-desktop-flow-meter-lbl" id="epDesktopFotoCount">0 de <?= $totalReqFotos ?> listas</span>
			</div>

			<button type="button" class="ep-btn-desktop-start-wizard" id="epBtnDesktopStartWizard" title="Abrir asistente guiado con visor ampliado y soporte Drag & Drop">
				<?= ep_icon('camera', 14) ?>
				<span>Subir con Asistente</span>
				<?= ep_icon('arrow-right', 12) ?>
			</button>
		</div>
	</div>

	<!-- Grilla panorámica de casillas fotográficas a todo lo ancho de la card -->
	<div class="ep-evidencia-grid-panoramica">
		<?php foreach ($epEvidenciaFotos as $foto): ?>
			<div class="ep-foto-slot" data-foto-id="<?= htmlspecialchars($foto['id']) ?>">
				<label class="ep-foto-dropzone" title="Subir foto para <?= htmlspecialchars($foto['label']) ?>">
					<input type="file" accept="image/*" class="ep-foto-input" id="ep-foto-<?= $epEvidenciaPrefix ?>-<?= $foto['id'] ?>" hidden>
					<img class="ep-foto-preview hidden" alt="Foto para <?= htmlspecialchars($foto['label']) ?>">
					<span class="ep-foto-dropzone-vacio">
						<span class="ep-foto-slot-icon"><?= ep_icon('camera', 22) ?></span>
						<span class="ep-foto-slot-action">Subir foto</span>
					</span>
				</label>
				<div class="ep-foto-slot-info">
					<span class="ep-foto-slot-label" title="<?= htmlspecialchars($foto['label']) ?>"><?= htmlspecialchars($foto['label']) ?></span>
					<span class="ep-hist-badge ep-foto-slot-estado">Pendiente</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

</div>
