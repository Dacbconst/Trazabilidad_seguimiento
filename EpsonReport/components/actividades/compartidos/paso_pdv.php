<?php
// Punto de venta del registro: selector con búsqueda, filtrado por el canal del usuario (solo puntos activos), compartido por todos los formularios.
require_once __DIR__.'/../../../includes/pdv_datos.php';
$epPdvCanales = ep_canales_usuario();
$epPdvPuntos = ep_pdv_listar($epPdvCanales);
?>
<div class="ep-pdv" id="ep-pdv" data-total="<?= count($epPdvPuntos) ?>">
	<span class="ep-label" id="ep-pdv-etiqueta">Punto de venta</span>
	<div class="ep-pdv-caja">
	<button type="button" class="ep-pdv-trigger" id="ep-pdv-trigger" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="ep-pdv-lista" aria-labelledby="ep-pdv-etiqueta ep-pdv-titulo">
		<span class="ep-pdv-icono"><?= ep_icon('store', 18) ?></span>
		<span class="ep-pdv-cuerpo">
			<span class="ep-pdv-titulo" id="ep-pdv-titulo">Selecciona el punto de venta</span>
			<span class="ep-pdv-sub" id="ep-pdv-sub" hidden></span>
		</span>
		<span class="ep-pdv-flecha"><?= ep_icon('chevron', 16) ?></span>
	</button>

	<div class="ep-pdv-fondo" id="ep-pdv-fondo" hidden></div>
	<div class="ep-pdv-panel" id="ep-pdv-panel" role="dialog" aria-label="Elegir punto de venta" hidden>
		<div class="ep-pdv-agarre" aria-hidden="true"></div>
		<div class="ep-pdv-busca">
			<?= ep_icon('search', 16) ?>
			<input type="text" id="ep-pdv-buscar" placeholder="Buscar por nombre o ciudad" autocomplete="off" aria-label="Buscar punto de venta" aria-autocomplete="list" aria-controls="ep-pdv-lista">
			<button type="button" class="ep-pdv-cerrar" id="ep-pdv-cerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		</div>
		<?php if (count($epPdvCanales) > 1): ?>
			<div class="ep-pdv-canales" id="ep-pdv-canales" role="group" aria-label="Canal">
				<button type="button" class="ep-pdv-canal activo" data-canal="">Todos</button>
				<?php foreach ($epPdvCanales as $c): ?>
					<button type="button" class="ep-pdv-canal" data-canal="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars(ucfirst(strtolower($c))) ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="ep-pdv-lista" id="ep-pdv-lista" role="listbox" aria-label="Puntos de venta"></div>
		<div class="ep-pdv-pie" id="ep-pdv-pie" aria-live="polite"></div>
	</div>
	</div>
	<script type="application/json" id="ep-pdv-datos"><?= json_encode($epPdvPuntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
