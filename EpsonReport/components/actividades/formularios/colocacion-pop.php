<?php
// Colocación de POP: un registro por punto de venta. El material sale del mes abierto que cargó el gestor, no se escribe libre.
require_once __DIR__.'/../../../includes/pop_datos.php';
$popMateriales = ep_pop_materiales();
?>
<script>window.EP_POP_MATERIALES = <?= json_encode($popMateriales, JSON_UNESCAPED_UNICODE) ?>;</script>
<div class="ep-steps">

	<!-- Paso 1: Material colocado en este punto de venta -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">1</div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Material POP colocado</h3>
			<?php if (empty($popMateriales)): ?>
				<p class="ep-step-hint">Todavía no hay material POP cargado para este mes. Avísale a tu supervisor antes de reportar.</p>
			<?php else: ?>
				<p class="ep-step-hint">Elige el material que colocaste y cuántas unidades. Puedes agregar varios.</p>
			<?php endif; ?>
			<div class="ep-pop-entregas" id="ep-pop-entregas"></div>
			<button type="button" class="ep-btn-agregar-fila" id="ep-pop-entregas-agregar"<?= empty($popMateriales) ? ' disabled' : '' ?>>
				<?= ep_icon('plus', 14) ?>
				Agregar material
			</button>
		</div>
	</div>

	<!-- Paso 2: Comentarios (sin línea hacia abajo, es el último) -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num">2</div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Comentarios</h3>
			<textarea rows="3" class="ep-input" id="ep-pop-comentarios" placeholder="Un comentario por línea..." style="margin-top:10px;"></textarea>
		</div>
	</div>

</div>
