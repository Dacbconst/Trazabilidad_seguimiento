<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$js_v = @filemtime(__DIR__.'/../../assets/js/repositorios.js') ?: time();
?>
<div class="ac-repo" id="ac-repo-lista">
	<div class="ac-users-header ac-repo-header">
		<div>
			<h1 class="ac-page-title">Repositorios</h1>
			<p class="ac-page-subtitle">Catálogos de referencia para autocompletar y bloquear campos del Acta.</p>
		</div>
	</div>

	<div class="ac-repo-tabs">
		<button type="button" class="ac-repo-tab active" id="repo-tab-rebate" data-tipo="rebate">
			<span class="material-symbols-outlined">percent</span>
			Rebate
			<span class="ac-repo-tab-count" id="repo-tab-rebate-count">—</span>
		</button>
		<button type="button" class="ac-repo-tab" id="repo-tab-participacion" data-tipo="participacion">
			<span class="material-symbols-outlined">view_column</span>
			Participación de Percha
			<span class="ac-repo-tab-count" id="repo-tab-participacion-count">—</span>
		</button>
		<button type="button" class="ac-repo-tab" id="repo-tab-cuotas" data-tipo="cuotas">
			<span class="material-symbols-outlined">request_quote</span>
			Cuotas Trimestrales
			<span class="ac-repo-tab-count" id="repo-tab-cuotas-count">—</span>
		</button>
	</div>

	<section class="ac-card">
		<div class="ac-repo-filtros">
			<div class="ac-input-wrap">
				<span class="material-symbols-outlined">search</span>
				<input type="text" class="ac-input" id="repo-buscar" placeholder="Buscar...">
			</div>
			<div class="ac-repo-actions">
				<!-- "Exportar" se transforma in-place en 2 opciones (CSV/Excel) al
				     hacer click, sin abrir modal ni dropdown flotante — pedido
				     explícito 2026-08-24 ("no quiero otra ventanita, usa
				     animaciones"). Truco de grid-template-columns 0fr->1fr para
				     que el ancho se anime solo, sin medir nada por JS. -->
				<div class="ac-repo-exportar" id="repo-exportar-wrap">
					<button type="button" class="ac-btn-outline ac-btn-inline ac-repo-exportar-btn" id="repo-exportar-btn">
						<span class="material-symbols-outlined">download</span>
						Exportar
					</button>
					<div class="ac-repo-exportar-opciones-outer">
						<div class="ac-repo-exportar-opciones">
							<a class="ac-repo-exportar-opcion" id="repo-exportar-csv" href="getters/repositorio_exportar.php?tipo=rebate&formato=csv" target="_blank">
								<span class="material-symbols-outlined">description</span>
								CSV
							</a>
							<a class="ac-repo-exportar-opcion" id="repo-exportar-xlsx" href="getters/repositorio_exportar.php?tipo=rebate&formato=xlsx" target="_blank">
								<span class="material-symbols-outlined">grid_on</span>
								Excel
							</a>
						</div>
					</div>
				</div>
				<!-- Solo visible en la pestaña Cuotas (assets/js/repositorios.js
				     alterna .hidden en activarTab()) — cola de clientes del Excel
				     que no matchearon solos contra el maestro, ver
				     getters/cuotas_pendientes_asignar.php. -->
				<button type="button" class="ac-btn-outline ac-btn-inline hidden" id="repo-pendientes-abrir">
					<span class="material-symbols-outlined">person_search</span>
					Pendientes de Asignar
					<span class="ac-repo-tab-count" id="repo-pendientes-count">—</span>
				</button>
				<!-- Solo visible en la pestaña Cuotas — "¿a quién le estoy mandando
				     qué Actas?" (2026-08-25, pedido explícito), ver
				     getters/cuotas_resumen.php. -->
				<button type="button" class="ac-btn-outline ac-btn-inline hidden" id="repo-resumen-abrir">
					<span class="material-symbols-outlined">bar_chart</span>
					Resumen
				</button>
				<!-- Solo visible en Rebate/Participación (2026-08-25, borrado
				     lógico — regla base, ver datos/repositorios_schema.sql):
				     Cuotas ya tiene su propio mecanismo de reactivar
				     (`estado='descartada'`), no se duplica acá. Ver
				     getters/repositorio_eliminados.php/_reactivar.php. -->
				<button type="button" class="ac-btn-outline ac-btn-inline" id="repo-eliminados-abrir">
					<span class="material-symbols-outlined">restore_from_trash</span>
					Eliminados
				</button>
				<button type="button" class="ac-btn-primary ac-btn-inline" id="repo-subir-abrir">
					<span class="material-symbols-outlined">upload_file</span>
					Subir Archivo
				</button>
			</div>
		</div>

		<!-- Paginación arriba Y abajo de la tabla (2026-08-25, pedido explícito:
		     "tengo que bajar para poder cambiar de página" — con la tabla
		     llena, los controles de abajo quedan fuera de vista). Misma pareja
		     info+botones duplicada arriba, siempre en sincro con la de abajo —
		     ver renderPaginacion() en repositorios.js, que ahora escribe en
		     las 2 a la vez en vez de una sola. -->
		<div class="ac-pagination ac-pagination-top" id="repo-paginacion-top" data-pagina="1" data-total-paginas="1">
			<p class="ac-pagination-info" id="repo-paginacion-info-top">Cargando...</p>
			<div class="ac-pagination-btns" id="repo-paginacion-btns-top"></div>
		</div>

		<div class="ac-table-scroll">
			<table class="ac-table" id="repo-tabla">
				<thead id="repo-tabla-head"></thead>
				<tbody id="repo-tabla-body">
					<tr><td class="ac-table-empty">Cargando...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="ac-pagination" id="repo-paginacion" data-pagina="1" data-total-paginas="1">
			<p class="ac-pagination-info" id="repo-paginacion-info">Cargando...</p>
			<div class="ac-pagination-btns" id="repo-paginacion-btns"></div>
		</div>
	</section>
</div>

<!-- Modal "Subir Archivo": 2 pasos en el mismo modal — 1) elegir el Excel,
     2) previsualización EDITABLE de lo que se va a guardar (el usuario puede
     corregir cualquier campo antes de confirmar) — recién ahí se guarda de
     verdad. getters/repositorio_previsualizar_excel.php (paso 1) nunca toca
     la base; getters/repositorio_guardar.php (paso 2) es el único que
     escribe. Sin resaltado de errores en la tabla a propósito (pedido
     explícito del usuario, 2026-08-24) — los campos son simples inputs
     editables, sin bordes rojos ni mensajes de validación por celda. -->
<div class="ac-modal-overlay" id="repo-subir-modal-overlay">
	<div class="ac-modal ac-repo-subir-modal">
		<div class="ac-modal-header">
			<h3 id="repo-subir-modal-titulo">Subir Archivo</h3>
			<button type="button" class="ac-modal-close" id="repo-subir-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>

		<div class="ac-modal-body" id="repo-subir-paso-elegir">
			<p class="ac-field-hint">El archivo actualiza los registros que coincidan y agrega los que sean nuevos — no borra el resto del repositorio.</p>
			<div class="ac-dropzone" id="repo-dropzone">
				<span class="material-symbols-outlined">upload_file</span>
				<p class="ac-dropzone-title">Arrastra tu Excel acá o hacé click para elegirlo</p>
				<p class="ac-dropzone-sub">.xlsx</p>
			</div>
			<!-- Sin subida en curso por default — aparece recién mientras se sube
			     un archivo (assets/js/repositorios.js, subida real vía XHR para
			     poder mostrar el % real, no un fetch() que no lo expone). -->
			<div class="ac-progreso-carga hidden" id="repo-subir-progreso">
				<p class="ac-progreso-carga-texto" id="repo-subir-progreso-texto">Subiendo…</p>
				<div class="ac-progreso-carga-track">
					<div class="ac-progreso-carga-fill" id="repo-subir-progreso-fill"></div>
				</div>
			</div>
			<input type="file" id="repo-archivo-input" accept=".xlsx" hidden>
		</div>

		<div class="ac-modal-body hidden" id="repo-subir-paso-preview">
			<div class="ac-archivo-chip">
				<span class="material-symbols-outlined">description</span>
				<div>
					<div class="ac-archivo-chip-nombre" id="repo-preview-nombre-archivo">—</div>
					<div class="ac-archivo-chip-detalle" id="repo-preview-cantidad">—</div>
				</div>
			</div>
			<!-- El Excel de Cuotas no trae el año (solo el trimestre, inferido del
			     propio archivo por repositorio_parsear_cuotas()) — lo elige el
			     superdesarrollador acá antes de guardar. Oculto para Rebate/
			     Participación (assets/js/repositorios.js). -->
			<div class="ac-field hidden" id="repo-preview-anio-wrap">
				<label class="ac-field-label" for="repo-preview-anio">Año de este trimestre</label>
				<input type="number" class="ac-input" id="repo-preview-anio" style="max-width:140px;">
			</div>
			<p class="ac-field-hint">Así vamos a guardar estos datos. Podés corregir cualquier campo antes de confirmar.</p>
			<div class="ac-alert-error hidden" id="repo-preview-errores"></div>
			<div class="ac-table-scroll ac-preview-table-scroll">
				<table class="ac-table ac-preview-table" id="repo-preview-tabla">
					<thead id="repo-preview-tabla-head"></thead>
					<tbody id="repo-preview-tabla-body"></tbody>
				</table>
			</div>
		</div>

		<div class="ac-modal-footer" id="repo-subir-footer-elegir">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="repo-subir-cancelar">Cancelar</button>
		</div>
		<div class="ac-modal-footer hidden" id="repo-subir-footer-preview">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="repo-subir-atras">Atrás</button>
			<button type="button" class="ac-btn-primary ac-btn-inline" id="repo-subir-guardar">
				<span class="material-symbols-outlined">save</span>
				Guardar
			</button>
		</div>
	</div>
</div>

<!-- "Pendientes de Asignar" — solo pestaña Cuotas (ver botón
     repo-pendientes-abrir arriba): filas cuyo cliente del Excel no
     matchea de forma única contra el maestro (resolverPosIdCliente(),
     includes/functions.php). Mismo concepto visual que la pantalla
     homónima de Liquidación (assets/js/liquidacion.js), reusa el ancho de
     .ac-borradores-modal (lista simple, no necesita el ancho de la
     previsualización de Excel). -->
<div class="ac-modal-overlay" id="repo-pendientes-modal-overlay">
	<div class="ac-modal ac-borradores-modal">
		<div class="ac-modal-header">
			<h3>Pendientes de Asignar</h3>
			<button type="button" class="ac-modal-close" id="repo-pendientes-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-modal-body">
			<p class="ac-field-hint">El nombre del cliente en el Excel no matcheó solo con ningún cliente único del maestro — elegí uno de los candidatos, buscá el pos_id correcto a mano, o descartá la fila si es un error de tipeo.</p>
			<div class="ac-table-scroll">
				<table class="ac-table" id="repo-pendientes-tabla">
					<thead>
						<tr>
							<th>Cliente (Excel)</th>
							<th>CEDI</th>
							<th>Categoría</th>
							<th>Período</th>
							<th class="ac-text-right">Montos</th>
							<th>Asignar cliente</th>
						</tr>
					</thead>
					<tbody id="repo-pendientes-body">
						<tr><td colspan="6" class="ac-table-empty">Cargando...</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- "Resumen" — solo pestaña Cuotas (ver botón repo-resumen-abrir arriba):
     panorama general (getters/cuotas_resumen.php) + gráfico de barras por
     usuario, mismo patrón visual ya construido y probado en Liquidación
     ("Resumen de Pagos", ver assets/js/liquidacion.js) — tarjetas de stat +
     barras en HTML/CSS puro (no SVG, ver esa misma lección documentada en
     CLAUDE.md). -->
<div class="ac-modal-overlay" id="repo-resumen-modal-overlay">
	<div class="ac-modal ac-acta-modal">
		<div class="ac-modal-header">
			<h3>Resumen — Cuotas Trimestrales</h3>
			<button type="button" class="ac-modal-close" id="repo-resumen-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-modal-body">
			<div class="ac-resumen-stats" id="repo-resumen-stats"></div>
			<div class="ac-resumen-chart-wrap">
				<p class="ac-resumen-chart-title">A quién le corresponden — usuarios con cuenta y supervisores sin cuenta todavía</p>
				<div id="repo-resumen-chart"></div>
			</div>
			<div id="repo-resumen-choque" class="ac-choque-wrap hidden"></div>
		</div>
	</div>
</div>

<!-- "Eliminados" — Rebate/Participación (2026-08-25, pedido explícito tras
     descubrir que "Eliminar" era un DELETE físico sin vuelta atrás: "si por
     error borro algo, ¿cómo lo recupero?"). Filtro de fecha (desde/hasta,
     sobre `eliminado_en`) para "filtrar rápido el día" — botón Reactivar
     por fila, ver getters/repositorio_eliminados.php/_reactivar.php. -->
<div class="ac-modal-overlay" id="repo-eliminados-modal-overlay">
	<div class="ac-modal ac-borradores-modal">
		<div class="ac-modal-header">
			<h3>Eliminados</h3>
			<button type="button" class="ac-modal-close" id="repo-eliminados-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-modal-body">
			<p class="ac-field-hint">Filas borradas de este repositorio — se pueden reactivar en cualquier momento, no se pierde el dato.</p>
			<div class="ac-repo-filtros" style="padding:0 0 var(--space-md);">
				<div class="ac-field ac-field-inline">
					<label class="ac-field-label" for="repo-eliminados-desde">Borrado desde</label>
					<input type="date" class="ac-input" id="repo-eliminados-desde">
				</div>
				<div class="ac-field ac-field-inline">
					<label class="ac-field-label" for="repo-eliminados-hasta">Borrado hasta</label>
					<input type="date" class="ac-input" id="repo-eliminados-hasta">
				</div>
				<button type="button" class="ac-btn-outline ac-btn-inline" id="repo-eliminados-buscar">
					<span class="material-symbols-outlined">search</span>
					Filtrar
				</button>
			</div>
			<div class="ac-table-scroll">
				<table class="ac-table" id="repo-eliminados-tabla">
					<thead id="repo-eliminados-tabla-head"></thead>
					<tbody id="repo-eliminados-body">
						<tr><td class="ac-table-empty">Cargando...</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script src="assets/js/repositorios.js?v=<?= $js_v ?>"></script>
