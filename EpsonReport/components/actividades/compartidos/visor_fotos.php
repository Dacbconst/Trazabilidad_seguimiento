<?php
// Visor de las fotos ya cargadas antes de enviar: carrusel con opciones de cambiar o quitar cada foto.
?>
<div id="epVisorFotos" class="ep-visor hidden" role="dialog" aria-modal="true" aria-label="Fotos cargadas">
	<div class="ep-visor-fondo" data-visor-cerrar></div>
	<div class="ep-visor-caja">
		<button type="button" class="ep-visor-cerrar" data-visor-cerrar aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		<button type="button" class="ep-visor-nav ep-visor-prev" id="epVisorPrev" aria-label="Foto anterior"><?= ep_icon('chevron-left', 20) ?></button>
		<img id="epVisorImg" alt="">
		<button type="button" class="ep-visor-nav ep-visor-next" id="epVisorNext" aria-label="Foto siguiente"><?= ep_icon('chevron-right', 20) ?></button>
		<div class="ep-visor-pie">
			<div class="ep-visor-info"><strong id="epVisorTitulo"></strong><span id="epVisorContador"></span></div>
			<div class="ep-visor-acciones">
				<button type="button" class="ep-visor-btn" id="epVisorCambiar"><?= ep_icon('camera', 15) ?> Cambiar foto</button>
				<button type="button" class="ep-visor-btn ep-visor-btn-quitar" id="epVisorQuitar"><?= ep_icon('trash', 15) ?> Quitar</button>
			</div>
		</div>
	</div>
</div>
