<!-- IDs = contrato con assets/js/app.js. Basado en "ESTADISTICO EXHIBICIONES QUE INSPIRAN.xlsx", sin porcentajes en el formulario. -->
<div class="ep-steps">

	<?php $epPrefijo = 'exh'; $epNumero = 1; $epEtiquetaTipo = 'Nombre de la exhibición'; $epEjemploTipo = 'Ej. Cabecera principal'; include __DIR__.'/../compartidos/paso_datos_actividad.php'; ?>


	<!-- Paso 1: Detalle de exhibiciones -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<div class="ep-step-head">
				<h3 class="ep-step-title">Detalle de Exhibiciones</h3>
				<span class="ep-step-total">Total: <strong id="ep-exh-total">0</strong></span>
			</div>
			<p class="ep-step-hint">Cuántas exhibiciones de cada tipo se armaron.</p>
			<div style="display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-exh-muebles">Muebles</label>
					<input type="number" min="0" class="ep-input" id="ep-exh-muebles" placeholder="Ej. 3" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-exh-rumas">Rumas</label>
					<input type="number" min="0" class="ep-input" id="ep-exh-rumas" placeholder="Ej. 4" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-exh-cabeceras">Cabeceras</label>
					<input type="number" min="0" class="ep-input" id="ep-exh-cabeceras" placeholder="Ej. 7" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-exh-regular">Exhibición regular</label>
					<input type="number" min="0" class="ep-input" id="ep-exh-regular" placeholder="Ej. 2" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-exh-otras">Otras</label>
					<input type="number" min="0" class="ep-input" id="ep-exh-otras" placeholder="Ej. 1" inputmode="numeric">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 2: Comentarios (sin línea hacia abajo, es el último) -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">3</div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-exh-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
