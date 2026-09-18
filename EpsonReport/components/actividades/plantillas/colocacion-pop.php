<?php
// Materiales fijos de "ESTADISTICOS COLOCACION DE POP.xlsx" — Campaña/Tipo son siempre "Mundial"/"Unidades" en el Excel real, no se piden por fila.
$epPopMateriales = [
	'vibrin' => 'Vibrin',
	'hablador' => 'Hablador',
	'rompe-trafico' => 'Rompe Tráfico',
	'bases' => 'Bases',
	'displays' => 'Displays',
	'cenefas' => 'Cenefas',
];
?>
<!-- IDs = contrato con assets/js/app.js -->
<div class="ep-steps">

	<!-- Paso 1: POP Recibido -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">1</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">POP Recibido</h3>
			<p class="ep-step-hint">Campaña Mundial · Unidades. Disponible se calcula solo (Bodega − Canales − Retail).</p>
			<div class="ep-pop-tabla">
				<div class="ep-pop-fila-header">
					<span>Material</span><span>Bodega</span><span>Canales</span><span>Retail</span><span>Disponible</span>
				</div>
				<?php foreach ($epPopMateriales as $key => $nombre): ?>
					<div class="ep-pop-fila">
						<span class="ep-pop-material-nombre"><?= htmlspecialchars($nombre) ?></span>
						<span class="ep-pop-fila-label-movil">Bodega</span>
						<input type="number" min="0" class="ep-input" id="ep-pop-bodega-<?= $key ?>" placeholder="0">
						<span class="ep-pop-fila-label-movil">Canales</span>
						<input type="number" min="0" class="ep-input" id="ep-pop-canales-<?= $key ?>" placeholder="0">
						<span class="ep-pop-fila-label-movil">Retail</span>
						<input type="number" min="0" class="ep-input" id="ep-pop-retail-<?= $key ?>" placeholder="0">
						<span class="ep-pop-fila-label-movil">Disponible</span>
						<div class="ep-pop-disponible" id="ep-pop-disponible-<?= $key ?>">0</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Paso 2: Detalle de Entrega a Puntos de Venta -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Detalle de Entrega a Puntos de Venta</h3>
			<p class="ep-step-hint">Por material, a qué PDV se entregó y cuánto — el total debería calzar con el Retail del paso 1.</p>

			<?php foreach ($epPopMateriales as $key => $nombre): ?>
				<div class="ep-pop-material-card">
					<div class="ep-pop-material-head">
						<span class="ep-pop-material-nombre"><?= htmlspecialchars($nombre) ?></span>
						<span class="ep-pop-material-head-info">Retail: <strong id="ep-pop-header-retail-<?= $key ?>">0</strong> · Entregado: <strong id="ep-pop-entregado-<?= $key ?>">0</strong></span>
						<span class="ep-hist-badge" id="ep-pop-badge-<?= $key ?>">Sin registrar</span>
					</div>
					<div id="ep-pop-entregas-<?= $key ?>" style="display:flex;flex-direction:column;gap:8px;"></div>
					<button type="button" class="ep-btn-agregar-fila" id="ep-pop-entregas-agregar-<?= $key ?>">
						<?= ep_icon('plus', 14) ?>
						Agregar PDV
					</button>
				</div>
			<?php endforeach; ?>
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
