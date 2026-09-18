<!-- Los IDs son el contrato con assets/js/app.js — no cambiarlos sin actualizar ese JS también. -->
<div class="ep-steps">

	<!-- Paso 1: Cobertura -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">1</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Cobertura</h3>
			<p class="ep-step-hint">Alcance de la activación a nivel nacional.</p>
			<div class="ep-form-grid-2">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-act-nacional">Tiendas a Nivel Nacional</label>
					<input type="number" min="0" class="ep-input" id="ep-act-nacional" placeholder="Ej. 10">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-act-coberturadas">Tiendas Coberturadas</label>
					<input type="number" min="0" class="ep-input" id="ep-act-coberturadas" placeholder="Ej. 7">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 2: Embudo -->
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
					<label class="ep-label" for="ep-act-visitaron">Visitaron</label>
					<input type="number" min="0" class="ep-input" id="ep-act-visitaron" placeholder="Ej. 100">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-act-interactuaron">Interactuaron</label>
					<input type="number" min="0" class="ep-input" id="ep-act-interactuaron" placeholder="Ej. 30">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-act-compraron">Compraron</label>
					<input type="number" min="0" class="ep-input" id="ep-act-compraron" placeholder="Ej. 10">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 3: Modelos -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">3</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<div class="ep-step-head">
				<h3 class="ep-step-title">Modelos Activados</h3>
				<span class="ep-step-total">Total: <strong id="ep-modelo-total-valor">0</strong> unidades</span>
			</div>
			<p class="ep-step-hint">Modelos exhibidos y unidades de cada uno.</p>

			<div id="ep-modelo-filas" style="display:flex;flex-direction:column;gap:8px;"></div>

			<button type="button" class="ep-btn-agregar-fila" id="ep-modelo-agregar" style="margin-top:10px;">
				<?= ep_icon('plus', 14) ?>
				Agregar modelo
			</button>
		</div>
	</div>

	<!-- Paso 4: Cumplimiento -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">4</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Cumplimiento de Activaciones</h3>
			<p class="ep-step-hint">Programadas vs. realizadas.</p>
			<div class="ep-form-grid-2">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-act-programadas">Activaciones Programadas</label>
					<input type="number" min="0" class="ep-input" id="ep-act-programadas" placeholder="Ej. 15">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-act-realizadas">Activaciones Realizadas</label>
					<input type="number" min="0" class="ep-input" id="ep-act-realizadas" placeholder="Ej. 8">
				</div>
			</div>
		</div>
	</div>

	<!-- Paso 5: Comentarios -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">5</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-act-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>


</div>
