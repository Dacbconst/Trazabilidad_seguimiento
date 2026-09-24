<!-- IDs = contrato con assets/js/app.js. -->
<div class="ep-steps">

	<?php $epPrefijo = 'cap'; $epNumero = 1; $epEtiquetaTipo = 'Tema o equipo capacitado'; $epEjemploTipo = 'Ej. L4360'; include __DIR__.'/../compartidos/paso_datos_actividad.php'; ?>


	<!-- Paso 2: Asistentes por cargo -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<div class="ep-step-head">
				<h3 class="ep-step-title">Asistentes por Cargo</h3>
				<span class="ep-step-total">Total: <strong id="ep-cap-total-asistentes-1">0</strong> asistentes</span>
			</div>
			<p class="ep-step-hint">Cuántas personas de cada cargo asistieron.</p>
			<div style="display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-cap-asist-jefe">Asistente de Jefe Tienda</label>
					<input type="number" min="0" class="ep-input" id="ep-cap-asist-jefe" placeholder="Ej. 1" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-cap-jefe-tienda">Jefe de Tienda</label>
					<input type="number" min="0" class="ep-input" id="ep-cap-jefe-tienda" placeholder="Ej. 1" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-cap-vendedores">Vendedores</label>
					<input type="number" min="0" class="ep-input" id="ep-cap-vendedores" placeholder="Ej. 15" inputmode="numeric">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 3: Interacciones -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">3</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Interacciones</h3>
			<p class="ep-step-hint">Interacciones no puede superar el total de asistentes.</p>
			<div class="ep-form-grid-2">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label">Asistentes</label>
					<div class="ep-input" id="ep-cap-total-asistentes-2" style="display:flex;align-items:center;">0</div>
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-cap-interacciones">Interacciones</label>
					<input type="number" min="0" class="ep-input" id="ep-cap-interacciones" placeholder="Ej. 8" inputmode="numeric">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 4: Comentarios (sin línea hacia abajo, es el último) -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">4</div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-cap-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
