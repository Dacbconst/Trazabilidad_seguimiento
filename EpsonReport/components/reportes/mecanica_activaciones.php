<?php
// Mecánica de reporte para la lógica de Activaciones (respaldo de calendario, programadas vs ejecutadas, filtros y selección con previsualización).
?>
<div class="ep-act-workspace hidden" id="epActWorkspace">
	<div class="ep-act-grid-layout">

		<!-- ================= COLUMNA IZQUIERDA ================= -->
		<section class="ep-act-col-left">
			<div class="ep-act-sec-header">
				<h3 class="ep-act-sec-title">
					<span class="ep-act-dot"></span>
					<span id="epActTituloLabel">ACTIVACIONES</span>
				</h3>
				<p class="ep-act-sec-sub">Sube el respaldo gráfico principal y define los objetivos.</p>
			</div>

			<!-- Referencia: la foto del calendario es obligatoria -->
			<div class="ep-act-req">
				<span class="ep-act-req-txt"><?= ep_icon('calendar', 14) ?> Foto del calendario</span>
				<span class="ep-act-req-badge">Obligatoria</span>
			</div>

			<!-- Dropzone fotográfico con mensaje corto y directo -->
			<div class="ep-act-dropzone" id="epActDrop">
				<input type="file" id="epActCalendario" accept="image/jpeg,image/png" hidden>
				<img id="epActCalPrev" class="ep-act-prev hidden" alt="Vista previa del calendario">
				<div class="ep-act-drop-vacio" id="epActDropVacio">
					<div class="ep-act-drop-icon">
						<?= ep_icon('camera', 24) ?>
					</div>
					<strong class="ep-act-drop-txt">Haz clic o arrastra la foto del calendario aquí</strong>
					<span class="ep-act-drop-hint">Formatos JPG o PNG (máx. 10MB)</span>
				</div>
				<button type="button" class="ep-act-quitar-foto hidden" id="epActQuitarFoto">Quitar imagen</button>
			</div>
			<!-- Foto del calendario ampliada: un clic en cualquier parte la cierra -->
			<div class="ep-act-zoom hidden" id="epActZoom" role="dialog" aria-modal="true" aria-label="Calendario ampliado">
				<img id="epActZoomImg" alt="Calendario ampliado">
				<button type="button" class="ep-act-zoom-cerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			</div>

			<!-- KPIs: Programados (campo abierto) y Ejecutado (contador automático con % respecto a programados) -->
			<div class="ep-act-kpis">
				<div class="ep-act-kpi-card">
					<label class="ep-act-kpi-label" for="epActProgramados">Programados</label>
					<div class="ep-act-kpi-input-wrap">
						<input type="number" id="epActProgramados" class="ep-act-kpi-input" min="1" placeholder="0">
						<span class="ep-act-kpi-unit">unidades</span>
					</div>
				</div>
				<div class="ep-act-kpi-card">
					<label class="ep-act-kpi-label">Ejecutado</label>
					<div class="ep-act-kpi-val-wrap">
						<span class="ep-act-kpi-val" id="epActEjecutadosVal">0</span>
						<span class="ep-act-pct-badge" id="epActPorcentajeBadge">0%</span>
					</div>
				</div>
			</div>

			<!-- Comentarios que salen en la diapositiva del calendario (uno por línea) -->
			<div class="ep-act-coment">
				<label class="ep-act-kpi-label" for="epActComentarios">Comentarios del reporte <span class="ep-act-opcional">(opcional)</span></label>
				<textarea id="epActComentarios" class="ep-input" rows="2" maxlength="600" placeholder="Un comentario por línea, máximo 5"></textarea>
			</div>

			<!-- Caja de Seleccionados con lista sincronizada en vivo -->
			<div class="ep-act-seleccionados-box">
				<div class="ep-act-sel-head">
					<div class="ep-act-sel-title-wrap">
						<span class="ep-act-sel-title">Seleccionados</span>
						<span class="ep-act-sel-count-pill" id="epActSelBadge">0 ítems</span>
					</div>
					<button type="button" class="ep-act-sel-limpiar" id="epActLimpiarSel">Limpiar</button>
				</div>
				<div class="ep-act-sel-lista" id="epActSelLista">
					<div class="ep-act-sel-vacio" id="epActSelVacio">
						<span>Marca los registros del lado derecho para sumarlos a este reporte.</span>
					</div>
				</div>
			</div>
		</section>

		<!-- ================= COLUMNA DERECHA ================= -->
		<section class="ep-act-col-right">
			<!-- Barra de filtros: FECHA / RANGO | PROMOTOR | CANAL -->
			<div class="ep-act-filter-bar">
				<!-- Filtro Fecha / Rango -->
				<div class="ep-act-filter-col ep-act-filter-fecha">
					<div class="ep-act-filter-label-row">
						<label class="ep-act-filter-label" id="epActFechaLabel">Fecha</label>
						<div class="ep-act-fecha-actions">
							<div class="ep-act-fecha-pills" role="group" aria-label="Modo de selección de fecha">
								<button type="button" class="ep-act-pill-btn active" id="epActPillUnica" data-modo="unica">Única</button>
								<button type="button" class="ep-act-pill-btn" id="epActPillRango" data-modo="rango">Rango</button>
							</div>
							<button type="button" class="ep-act-limpiar-fecha-btn hidden" id="epActLimpiarFechaBtn" title="Limpiar filtro de fecha">Limpiar</button>
						</div>
					</div>
					<div class="ep-act-fecha-inputs-wrap" id="epActFechaWrap">
						<div class="ep-act-fecha-input-box" id="epActFechaDesdeBox">
							<span class="ep-act-fecha-icon"><?= ep_icon('calendar', 13) ?></span>
							<input type="date" id="epActFechaDesde" class="ep-act-input-date" title="Fecha" placeholder="dd/mm/aaaa">
						</div>
						<span class="ep-act-fecha-sep hidden" id="epActFechaSep">-</span>
						<div class="ep-act-fecha-input-box hidden" id="epActFechaHastaBox">
							<input type="date" id="epActFechaHasta" class="ep-act-input-date" title="Fecha fin" placeholder="dd/mm/aaaa">
						</div>
					</div>
				</div>

				<!-- Filtro Promotor con dropdown buscador por tipeo -->
				<div class="ep-act-filter-col ep-act-filter-promotor">
					<label class="ep-act-filter-label" for="epActPromotorDisplay">Promotor</label>
					<div class="ep-act-combo-wrap" id="epActPromotorCombo">
						<button type="button" class="ep-act-combo-trigger" id="epActPromotorDisplay">
							<span class="ep-act-combo-text" id="epActPromotorTexto">Todos</span>
							<?= ep_icon('chevron', 12) ?>
						</button>
						<div class="ep-act-combo-menu hidden" id="epActPromotorMenu">
							<div class="ep-act-combo-search-wrap">
								<?= ep_icon('search', 13) ?>
								<input type="text" id="epActPromotorSearch" class="ep-act-combo-search" placeholder="Escribe para buscar..." autocomplete="off">
							</div>
							<div class="ep-act-combo-list" id="epActPromotorList">
								<button type="button" class="ep-act-combo-item selected" data-value="todos">Todos los promotores</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Filtro Canal -->
				<div class="ep-act-filter-col ep-act-filter-canal">
					<label class="ep-act-filter-label" for="epActCanalSelect">Canal</label>
					<select id="epActCanalSelect" class="ep-act-filter-select">
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
				<div class="ep-act-dual-rows" id="epActRegistrosLista">
					<div class="ep-act-loading-state">
						<?= ep_icon('clock', 18) ?>
						<span>Cargando registros de activaciones...</span>
					</div>
				</div>
			</div>
		</section>

	</div>
</div>
