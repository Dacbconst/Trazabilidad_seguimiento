<!-- actualizarEstadisticasCapacitaciones() en app.js rellena estos IDs. -->
<div class="ep-stats-panel">

	<!-- Oculto a pedido explícito (ya se ve en el paso 2 del formulario) — mecanismo intacto. -->
	<div class="ep-stats-row-2 hidden">
		<div class="ep-stat-card">
			<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Asistentes</div>
			<div class="ep-stat-card-body">
				<div class="ep-stat-card-pct" id="ep-cap-stat-asistentes">0</div>
				<div class="ep-stat-card-detalle">
					<div class="ep-stat-card-fila"><span>Asist. de Jefe Tienda</span><strong id="ep-cap-stat-asist-jefe">0</strong></div>
					<div class="ep-stat-card-fila"><span>Jefe de Tienda</span><strong id="ep-cap-stat-jefe-tienda">0</strong></div>
					<div class="ep-stat-card-fila"><span>Vendedores</span><strong id="ep-cap-stat-vendedores">0</strong></div>
				</div>
			</div>
		</div>

		<div class="ep-stat-card">
			<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Interacciones</div>
			<div class="ep-stat-card-body">
				<div class="ep-stat-card-pct" id="ep-cap-stat-interaccion-pct">0%</div>
				<div class="ep-stat-card-detalle">
					<div class="ep-stat-card-fila"><span>Interacciones</span><strong id="ep-cap-stat-interacciones">0</strong></div>
				</div>
			</div>
		</div>
	</div>

	<div class="ep-stat-card-plano">
		<div class="ep-stat-card-titulo"><?= ep_icon('users', 15) ?> Detalle Asistentes</div>
		<div id="ep-cap-stat-detalle-cargos"></div>
	</div>

	<div class="ep-stat-card-plano">
		<div class="ep-stat-card-titulo"><?= ep_icon('arrow-right', 15) ?> Asistentes vs. Interacciones</div>
		<div class="ep-stat-bars-vert">
			<div class="ep-stat-bar-vert">
				<span class="ep-stat-bar-vert-valor" id="ep-cap-stat-bar-asistentes-valor">0</span>
				<div class="ep-stat-bar-vert-fill" id="ep-cap-stat-bar-asistentes" style="height:0%;"></div>
				<span class="ep-stat-bar-vert-label">Asistentes</span>
			</div>
			<div class="ep-stat-bar-vert">
				<span class="ep-stat-bar-vert-valor" id="ep-cap-stat-bar-interacciones-valor">0</span>
				<div class="ep-stat-bar-vert-fill" id="ep-cap-stat-bar-interacciones" style="height:0%;"></div>
				<span class="ep-stat-bar-vert-label">Interacciones</span>
			</div>
		</div>
	</div>

	<div class="ep-stat-card-plano">
		<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Comentarios</div>
		<div class="ep-stat-comentarios" id="ep-cap-stat-comentarios"></div>
	</div>

</div>
