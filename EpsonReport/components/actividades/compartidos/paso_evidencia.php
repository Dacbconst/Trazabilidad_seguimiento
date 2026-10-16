<?php
// Bloque "Evidencia Fotográfica": requiere $epEvidenciaFotos y $epEvidenciaPrefix (definidos por actividad en actividades.php).
if (empty($epEvidenciaFotos)) return;
$totalReqFotos = count(array_filter($epEvidenciaFotos, fn($f) => empty($f['opcional'])));
$conDescripcion = ep_fotos_con_descripcion($actividad['plantilla'] ?? '');
$esCompetencia = ($actividad['plantilla'] ?? '') === 'competencia';
// Informe fotográfico: las casillas "competencia-N" van en su propia sección opcional, debajo de las fotos del punto.
$epFotosCompetencia = ($actividad['plantilla'] ?? '') === 'informe-fotografico' ? array_values(array_filter($epEvidenciaFotos, fn($f) => strpos($f['id'], 'competencia-') === 0)) : [];
$epEvidenciaFotos = array_values(array_filter($epEvidenciaFotos, fn($f) => !$epFotosCompetencia || strpos($f['id'], 'competencia-') !== 0));
// Una casilla de foto; la usan la grilla principal y la de competencia.
$epCasilla = function (array $foto) use ($epEvidenciaPrefix, $conDescripcion) {
	ob_start(); ?>
			<div class="ep-foto-slot" data-foto-id="<?= htmlspecialchars($foto['id']) ?>"<?= !empty($foto['opcional']) ? ' data-opcional="1"' : '' ?>>
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
				<?php if ($conDescripcion): ?>
					<textarea class="ep-foto-descripcion" rows="3" maxlength="<?= EP_FOTO_DESCRIPCION_MAX ?>" placeholder="¿Qué se ve en la foto?" aria-label="Descripción de <?= htmlspecialchars($foto['label']) ?>"></textarea>
				<?php endif; ?>
			</div>
	<?php return ob_get_clean();
};
?>
<div class="ep-evidencia-bloque-card ep-evidencia-actividad" data-actividad-id="<?= (int) ($actividad['id'] ?? 1) ?>" data-plantilla="<?= htmlspecialchars($actividad['plantilla'] ?? '') ?>"<?= $conDescripcion ? ' data-con-descripcion="1"' : '' ?>>
	
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
						<?php if ($conDescripcion): ?>
							Cada foto que subas lleva debajo su descripción: qué hizo la competencia y dónde. Sale tal cual en la presentación.
						<?php else: ?>
							Las fotos obligatorias son las primeras; las marcadas como opcionales suman evidencia pero no son necesarias.
						<?php endif; ?>
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
		<?php foreach ($epEvidenciaFotos as $foto) { echo $epCasilla($foto); } ?>
	</div>

	<?php if (ep_fotos_extensible($actividad['plantilla'] ?? '')): ?>
		<button type="button" class="ep-btn-agregar-fila ep-btn-agregar-foto" data-prefix="<?= $epEvidenciaPrefix ?>" data-siguiente="<?= count($epEvidenciaFotos) + 1 ?>" data-label="<?= htmlspecialchars(ep_foto_extra_label($actividad['plantilla'] ?? '')) ?>">
			<?= ep_icon('plus', 14) ?>
			Agregar foto
		</button>
	<?php endif; ?>

	<?php if ($epFotosCompetencia): ?>
		<!-- Sección aparte y opcional: fotos de la competencia en este mismo punto de venta. -->
		<div class="ep-evidencia-competencia-sec" id="epCompetenciaSec">
			<div class="ep-evidencia-competencia-head">
				<h3>Competencia en este punto <span class="ep-evidencia-opcional-tag">Opcional</span></h3>
				<p>Si viste exhibiciones de la competencia aquí, súbelas. Si no hay, puedes omitir esta sección. Salen en diapositivas aparte, tituladas «PUNTO - COMPETENCIA».</p>
			</div>
			<div class="ep-evidencia-grid-panoramica ep-evidencia-competencia">
				<?php foreach ($epFotosCompetencia as $foto) { echo $epCasilla($foto); } ?>
			</div>
			<button type="button" class="ep-btn-agregar-fila ep-btn-agregar-foto" data-grupo="competencia" data-prefix="<?= $epEvidenciaPrefix ?>" data-siguiente="<?= count($epFotosCompetencia) + 1 ?>" data-label="Competencia en este punto">
				<?= ep_icon('plus', 14) ?>
				Agregar foto de competencia
			</button>
		</div>
	<?php endif; ?>

	<?php if ($esCompetencia): ?>
		<!-- Guarda este punto de venta y deja el formulario en blanco para el siguiente, sin perder los ya guardados. -->
		<button type="button" class="ep-btn-competencia-otro-punto" id="epBtnCompetenciaOtroPunto">
			<?= ep_icon('plus', 16) ?>
			<span>Añadir otro punto de venta</span>
		</button>
	<?php endif; ?>

</div>
