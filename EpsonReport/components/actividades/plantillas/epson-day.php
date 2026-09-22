<!-- Igual que Activaciones pero sin paso de Cumplimiento (no está en el Excel de Epson Day). IDs con prefijo ep-eday- para no chocar con Activaciones. -->
<div class="ep-steps">

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
					<label class="ep-label" for="ep-eday-nacional">Tiendas a Nivel Nacional</label>
					<input type="number" min="0" class="ep-input" id="ep-eday-nacional" placeholder="Ej. 5" inputmode="numeric">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="ep-eday-coberturadas">Tiendas Coberturadas</label>
					<input type="number" min="0" class="ep-input" id="ep-eday-coberturadas" placeholder="Ej. 2" inputmode="numeric">
				</div>
			</div>
		</div>
	</div>

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
					<label class="ep-label" for="ep-eday-visitaron">Visitaron</label>
					<input type="number" min="0" class="ep-input" id="ep-eday-visitaron" placeholder="Ej. 100" inputmode="numeric">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-eday-interactuaron">Interactuaron</label>
					<input type="number" min="0" class="ep-input" id="ep-eday-interactuaron" placeholder="Ej. 30" inputmode="numeric">
				</div>
				<div class="ep-funnel-conexion"><?= ep_icon('arrow-right', 16) ?></div>
				<div class="ep-funnel-step">
					<label class="ep-label" for="ep-eday-compraron">Compraron</label>
					<input type="number" min="0" class="ep-input" id="ep-eday-compraron" placeholder="Ej. 20" inputmode="numeric">
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
				<h3 class="ep-step-title">Modelos Activados</h3>
				<span class="ep-step-total">Total: <strong id="ep-eday-modelo-total-valor">0</strong> unidades</span>
			</div>
			<p class="ep-step-hint">Elige cada modelo mostrado y cuántas unidades.</p>
			<div id="ep-eday-modelo-filas" style="display:flex;flex-direction:column;gap:8px;"></div>
			<button type="button" class="ep-btn-agregar-fila" id="ep-eday-modelo-agregar" style="margin-top:10px;">
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
			<textarea rows="3" class="ep-input" id="ep-eday-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
