<?php
// Plantilla de Exhibiciones que Inspiran, basada en "ESTADISTICO EXHIBICIONES QUE INSPIRAN.xlsx". Sin porcentajes, eso se calcula en el reporte.
?>
<div class="ep-form-section ep-form-section-full">
	<div class="ep-form-section-titulo">
		<span class="ep-form-section-num">1</span>
		<?= ep_icon('store', 16) ?>
		<span>Detalle de Exhibiciones</span>
	</div>

	<div class="ep-form-grid-2">
		<div style="display:flex;flex-direction:column;gap:6px;">
			<label class="ep-label">Muebles</label>
			<input class="ep-input" type="number" min="0" id="ep-exh-muebles" placeholder="Ej. 3">
		</div>
		<div style="display:flex;flex-direction:column;gap:6px;">
			<label class="ep-label">Rumas</label>
			<input class="ep-input" type="number" min="0" id="ep-exh-rumas" placeholder="Ej. 4">
		</div>
		<div style="display:flex;flex-direction:column;gap:6px;">
			<label class="ep-label">Cabeceras</label>
			<input class="ep-input" type="number" min="0" id="ep-exh-cabeceras" placeholder="Ej. 7">
		</div>
		<div style="display:flex;flex-direction:column;gap:6px;">
			<label class="ep-label">Total</label>
			<div class="ep-input ep-input-auto">
				<span id="ep-exh-total">0</span>
			</div>
		</div>
	</div>
</div>

<div class="ep-form-section ep-form-section-full">
	<div class="ep-form-section-titulo">
		<span class="ep-form-section-num">2</span>
		<?= ep_icon('file', 16) ?>
		<span>Comentarios</span>
	</div>
	<textarea class="ep-input" id="ep-exh-comentarios" rows="3" placeholder="Un comentario por línea..."></textarea>
</div>
