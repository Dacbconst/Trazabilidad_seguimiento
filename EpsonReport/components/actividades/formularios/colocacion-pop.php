<!-- Colocación de POP: un registro por punto de venta (elegido arriba). IDs = contrato con assets/js/app.js -->
<div class="ep-steps">

	<!-- Paso 1: Campaña del material (Mundial, BTS...) -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">1</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Campaña</h3>
			<p class="ep-step-hint">Campaña a la que pertenece el material POP que entregaste en este punto de venta.</p>
			<input type="text" class="ep-input ep-input-mayusculas" id="ep-pop-campana" maxlength="40" placeholder="Ej. BTS" autocomplete="off" style="margin-top:10px;">
		</div>
	</div>

	<!-- Paso 2: Material entregado en este punto de venta -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Material POP entregado</h3>
			<p class="ep-step-hint">Un material por fila y cuántas unidades entregaste en este punto de venta.</p>
			<div class="ep-pop-entregas" id="ep-pop-entregas">
				<div class="ep-pop-entrega-fila">
					<input type="text" class="ep-input ep-pop-entrega-material" maxlength="60" placeholder="Material (ej. Vibrin)" autocomplete="off">
					<input type="number" min="1" class="ep-input ep-pop-entrega-cantidad" placeholder="Cant." inputmode="numeric">
					<button type="button" class="ep-modelo-quitar" aria-label="Quitar material"><?= ep_icon('trash', 14) ?></button>
				</div>
			</div>
			<button type="button" class="ep-btn-agregar-fila" id="ep-pop-entregas-agregar">
				<?= ep_icon('plus', 14) ?>
				Agregar material
			</button>
		</div>
	</div>

	<!-- Paso 3: Comentarios (sin línea hacia abajo, es el último) -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">3</div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-pop-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
