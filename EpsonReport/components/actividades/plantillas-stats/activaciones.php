<!-- actualizarEstadisticasActivaciones() en app.js rellena estos IDs en cada input. -->
<div class="ep-stats-panel">

	<div class="ep-stats-row-3">
		<div class="ep-stat-card">
			<div class="ep-stat-card-header"><?= ep_icon('store', 13) ?> Cobertura</div>
			<div class="ep-stat-card-body">
				<div class="ep-stat-card-pct" id="ep-stat-cobertura-pct">0%</div>
				<div class="ep-stat-card-detalle">
					<div class="ep-stat-card-fila"><span>Nacional</span><strong id="ep-stat-nacional">0</strong></div>
					<div class="ep-stat-card-fila"><span>Coberturadas</span><strong id="ep-stat-coberturadas">0</strong></div>
				</div>
			</div>
		</div>

		<div class="ep-stat-card">
			<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Interacciones</div>
			<div class="ep-stat-card-body">
				<div class="ep-stat-card-pct" id="ep-stat-interaccion-pct">0%</div>
				<div class="ep-stat-card-detalle">
					<div class="ep-stat-card-fila"><span>Visitaron</span><strong id="ep-stat-visitaron">0</strong></div>
					<div class="ep-stat-card-fila"><span>Interactuaron</span><strong id="ep-stat-interactuaron">0</strong></div>
				</div>
			</div>
		</div>

		<div class="ep-stat-card">
			<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Ventas</div>
			<div class="ep-stat-card-body">
				<div class="ep-stat-card-pct" id="ep-stat-ventas-pct">0%</div>
				<div class="ep-stat-card-detalle">
					<div class="ep-stat-card-fila"><span>Realizadas</span><strong id="ep-stat-ventas-realizadas">0</strong></div>
				</div>
			</div>
		</div>
	</div>

	<div class="ep-stat-card-plano">
		<div class="ep-stat-card-titulo"><?= ep_icon('arrow-right', 15) ?> Embudo de Clientes</div>
		<div class="ep-stat-bars-vert">
			<div class="ep-stat-bar-vert">
				<span class="ep-stat-bar-vert-valor" id="ep-stat-bar-visitaron-valor">0</span>
				<div class="ep-stat-bar-vert-fill" id="ep-stat-bar-visitaron" style="height:0%;"></div>
				<span class="ep-stat-bar-vert-label">Visitaron</span>
			</div>
			<div class="ep-stat-bar-vert">
				<span class="ep-stat-bar-vert-valor" id="ep-stat-bar-interactuaron-valor">0</span>
				<div class="ep-stat-bar-vert-fill" id="ep-stat-bar-interactuaron" style="height:0%;"></div>
				<span class="ep-stat-bar-vert-label">Interactuaron</span>
			</div>
			<div class="ep-stat-bar-vert">
				<span class="ep-stat-bar-vert-valor" id="ep-stat-bar-compraron-valor">0</span>
				<div class="ep-stat-bar-vert-fill" id="ep-stat-bar-compraron" style="height:0%;"></div>
				<span class="ep-stat-bar-vert-label">Compraron</span>
			</div>
		</div>
	</div>

	<div class="ep-stats-row-2">
		<div class="ep-stats-col">
			<div class="ep-stat-card-mini">
				<span class="ep-stat-card-mini-pct" id="ep-stat-mayor-pct">0%</span>
				<span class="ep-stat-card-mini-nombre" id="ep-stat-mayor-nombre">Sin datos</span>
				<span class="ep-stat-card-mini-caption">SKU con mayor venta</span>
			</div>
			<div class="ep-stat-card-mini">
				<span class="ep-stat-card-mini-pct" id="ep-stat-menor-pct">0%</span>
				<span class="ep-stat-card-mini-nombre" id="ep-stat-menor-nombre">Sin datos</span>
				<span class="ep-stat-card-mini-caption">SKU con menor venta</span>
			</div>
		</div>

		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('store', 15) ?> Detalle de Ventas</div>
			<div id="ep-stat-detalle-ventas"></div>
		</div>
	</div>

	<div class="ep-stat-card-plano">
		<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Comentarios</div>
		<div class="ep-stat-comentarios" id="ep-stat-comentarios"></div>
	</div>

</div>
