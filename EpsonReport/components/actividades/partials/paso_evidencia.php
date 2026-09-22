<?php
// Bloque reutilizable "Evidencia Fotográfica" — horizontal, fuera del layout de 2 columnas (form/estadísticas). Requiere $epEvidenciaFotos/$epEvidenciaPrefix.
if (empty($epEvidenciaFotos)) return;
?>
<div class="ep-evidencia-bloque">
	<div class="ep-evidencia-bloque-head">
		<span class="ep-eyebrow">Evidencia Fotográfica</span>
		<span class="ep-step-total">Subidas: <strong class="ep-evidencia-contador">0</strong>/<?= count($epEvidenciaFotos) ?></span>
	</div>
	<div class="ep-evidencia-fila">
		<?php foreach ($epEvidenciaFotos as $foto): ?>
			<div class="ep-foto-slot">
				<label class="ep-foto-dropzone">
					<input type="file" accept="image/*" capture="environment" class="ep-foto-input" id="ep-foto-<?= $epEvidenciaPrefix ?>-<?= $foto['id'] ?>" hidden>
					<img class="ep-foto-preview hidden" alt="">
					<span class="ep-foto-dropzone-vacio">
						<?= ep_icon('camera', 22) ?>
						<span>Subir foto</span>
					</span>
				</label>
				<div class="ep-foto-slot-info">
					<span class="ep-foto-slot-label"><?= htmlspecialchars($foto['label']) ?></span>
					<span class="ep-hist-badge ep-foto-slot-estado">Pendiente</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
