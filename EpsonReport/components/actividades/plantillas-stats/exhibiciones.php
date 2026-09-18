<?php
// Vista final de Exhibiciones que Inspiran, calculada en vivo. Solo 2 cards, sin porcentajes, tal como pidió el usuario.
?>
<div class="ep-stats-panel">

	<div class="ep-stats-row-2">
		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('store', 15) ?><span>Detalle de Exhibiciones</span></div>
			<div id="ep-exh-stat-detalle" style="display:flex;flex-direction:column;gap:8px;">
				<span class="ep-stat-comentarios-vacio">Todavía no cargaste exhibiciones.</span>
			</div>
		</div>

		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?><span>Comentarios</span></div>
			<div id="ep-exh-stat-comentarios" class="ep-stat-comentarios">
				<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>
			</div>
		</div>
	</div>
</div>
