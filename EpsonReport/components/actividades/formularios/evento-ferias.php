<!-- Igual que Activaciones pero sin Cobertura ni Cumplimiento (no están en el Excel de Eventos o Ferias). IDs con prefijo ep-evento-. -->
<div class="ep-steps">

	<?php $epPrefijo = 'evento'; $epNumero = 1; $epEtiquetaTipo = 'Nombre del evento o feria'; $epEjemploTipo = 'Ej. Hotel Swiss'; include __DIR__.'/../compartidos/paso_datos_actividad.php'; ?>

	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Embudo de clientes</h3>
			<p class="ep-step-hint">Visitaron → Interactuaron → Compraron, en ese orden.</p>
			<div class="ep-funnel">
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-evento-visitaron">Visitaron</label>
					<input type="number" min="0" class="ep-input" id="ep-evento-visitaron" placeholder="Ej. 100" inputmode="numeric">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-evento-interactuaron">Interactuaron</label>
					<input type="number" min="0" class="ep-input" id="ep-evento-interactuaron" placeholder="Ej. 30" inputmode="numeric">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-evento-compraron">Compraron</label>
					<input type="number" min="0" class="ep-input" id="ep-evento-compraron" placeholder="Ej. 20" inputmode="numeric">
				</div>
			</div>
		</div>
	</div>

	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">3</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<div class="ep-step-head">
				<h3 class="ep-step-title">Modelos</h3>
				<span class="ep-step-total">Total: <strong id="ep-evento-modelo-total-valor">0</strong> unidades</span>
			</div>
			<p class="ep-step-hint">Elige cada modelo mostrado y cuántas unidades.</p>
			<div id="ep-evento-modelo-filas" style="display:flex;flex-direction:column;gap:8px;"></div>
			<button type="button" class="ep-btn-agregar-fila" id="ep-evento-modelo-agregar" style="margin-top:10px;">
				<?= ep_icon('plus', 14) ?>
				Agregar modelo
			</button>
		</div>
	</div>

	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">4</div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-evento-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
