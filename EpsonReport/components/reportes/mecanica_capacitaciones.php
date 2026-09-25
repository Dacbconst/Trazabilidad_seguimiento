<?php
// Mecánica de reporte para la lógica de Capacitaciones (Seleccionados a la izquierda, filtros por fecha/promotor/canal y grilla dual con previsualización de la diapositiva 1).
?>
<div class="ep-act-workspace ep-cap-workspace hidden" id="epCapWorkspace">
	<div class="ep-act-grid-layout ep-cap-grid-layout">

		<!-- ================= COLUMNA IZQUIERDA: SELECCIONADOS ================= -->
		<section class="ep-act-col-left ep-cap-col-left">
			<div class="ep-act-sec-header">
				<h3 class="ep-act-sec-title">
					<span class="ep-act-dot" id="epCapDot" style="background:#164194;"></span>
					<span id="epCapTituloLabel">CAPACITACIONES</span>
				</h3>
				<p class="ep-act-sec-sub" id="epCapTituloSub">Selecciona los registros que formarán parte del reporte mensual.</p>
			</div>

			<!-- Apartado de Seleccionados sincronizado en vivo -->
			<div class="ep-act-seleccionados-box ep-cap-seleccionados-box">
				<div class="ep-act-sel-head">
					<div class="ep-act-sel-title-wrap">
						<span class="ep-act-sel-title">Seleccionados</span>
						<span class="ep-act-sel-count-pill" id="epCapSelBadge">0 ítems</span>
					</div>
					<button type="button" class="ep-act-sel-limpiar" id="epCapLimpiarSel">Limpiar</button>
				</div>
				<div class="ep-act-sel-lista ep-cap-sel-lista" id="epCapSelLista">
					<div class="ep-act-sel-vacio" id="epCapSelVacio">
						<span>Marca los registros del lado derecho para sumarlos a este reporte.</span>
					</div>
				</div>
			</div>
		</section>

		<!-- ================= COLUMNA DERECHA: FILTROS + TABLA DUAL ================= -->
		<section class="ep-act-col-right">
			<!-- Barra de filtros: FECHA / RANGO | PROMOTOR | CANAL -->
			<div class="ep-act-filter-bar">
				<!-- Filtro Fecha / Rango -->
				<div class="ep-act-filter-col ep-act-filter-fecha">
					<div class="ep-act-filter-label-row">
						<label class="ep-act-filter-label" id="epCapFechaLabel">Fecha</label>
						<div class="ep-act-fecha-actions">
							<div class="ep-act-fecha-pills" role="group" aria-label="Modo de selección de fecha">
								<button type="button" class="ep-act-pill-btn active" id="epCapPillUnica" data-modo="unica">Única</button>
								<button type="button" class="ep-act-pill-btn" id="epCapPillRango" data-modo="rango">Rango</button>
							</div>
							<button type="button" class="ep-act-limpiar-fecha-btn hidden" id="epCapLimpiarFechaBtn" title="Limpiar filtro de fecha">Limpiar</button>
						</div>
					</div>
					<div class="ep-act-fecha-inputs-wrap" id="epCapFechaWrap">
						<div class="ep-act-fecha-input-box" id="epCapFechaDesdeBox">
							<span class="ep-act-fecha-icon"><?= ep_icon('calendar', 13) ?></span>
							<input type="date" id="epCapFechaDesde" class="ep-act-input-date" title="Fecha" placeholder="dd/mm/aaaa">
						</div>
						<span class="ep-act-fecha-sep hidden" id="epCapFechaSep">-</span>
						<div class="ep-act-fecha-input-box hidden" id="epCapFechaHastaBox">
							<input type="date" id="epCapFechaHasta" class="ep-act-input-date" title="Fecha fin" placeholder="dd/mm/aaaa">
						</div>
					</div>
				</div>

				<!-- Filtro Promotor con dropdown buscador reactivo -->
				<div class="ep-act-filter-col ep-act-filter-promotor">
					<label class="ep-act-filter-label" for="epCapPromotorDisplay">Promotor</label>
					<div class="ep-act-combo-wrap" id="epCapPromotorCombo">
						<button type="button" class="ep-act-combo-trigger" id="epCapPromotorDisplay">
							<span class="ep-act-combo-text" id="epCapPromotorTexto">Todos</span>
							<?= ep_icon('chevron', 12) ?>
						</button>
						<div class="ep-act-combo-menu hidden" id="epCapPromotorMenu">
							<div class="ep-act-combo-search-wrap">
								<?= ep_icon('search', 13) ?>
								<input type="text" id="epCapPromotorSearch" class="ep-act-combo-search" placeholder="Escribe para buscar..." autocomplete="off">
							</div>
							<div class="ep-act-combo-list" id="epCapPromotorList">
								<button type="button" class="ep-act-combo-item selected" data-value="todos">Todos los promotores</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Filtro Canal -->
				<div class="ep-act-filter-col ep-act-filter-canal">
					<label class="ep-act-filter-label" for="epCapCanalSelect">Canal</label>
					<select id="epCapCanalSelect" class="ep-act-filter-select">
						<option value="todos">Todos</option>
						<option value="Retail">Retail</option>
						<option value="Mayorista">Mayorista</option>
					</select>
				</div>
			</div>

			<!-- Tabla / Grilla dual: Registros | Previsualización (Diapositiva 1) -->
			<div class="ep-act-dual-table">
				<div class="ep-act-dual-head">
					<div class="ep-act-dual-col-title">Registros</div>
					<div class="ep-act-dual-col-title">Previsualización (Diapositiva 1)</div>
				</div>
				<div class="ep-act-dual-rows" id="epCapRegistrosLista">
					<div class="ep-act-loading-state">
						<?= ep_icon('clock', 18) ?>
						<span>Cargando registros de capacitaciones...</span>
					</div>
				</div>
			</div>
		</section>

	</div>
</div>
