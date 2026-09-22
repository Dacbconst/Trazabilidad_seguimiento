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
			<!-- Texto dinámico por pestaña (activarTab() en repositorios.js) — Cuotas Trimestrales no es un catálogo de
			     referencia como Rebate/Participación, es el mecanismo para asignar Actas Precargadas de forma masiva,
			     y el subtítulo genérico no lo decía (pedido explícito 2026-09-15). -->
			<p class="ac-page-subtitle" id="repo-subtitulo">Catálogos de referencia para autocompletar y bloquear campos del Acta.</p>
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
		<!-- Indicador deslizante (2026-09-18) — posición/ancho calculados en JS,
		     ver posicionarIndicadorTab() en repositorios.js. -->
		<div class="ac-repo-tabs-indicador" id="repo-tabs-indicador"></div>
	</div>

	<section class="ac-card">
		<div class="ac-repo-filtros">
			<div class="ac-input-wrap">
				<span class="material-symbols-outlined">search</span>
				<input type="text" class="ac-input" id="repo-buscar" placeholder="Buscar...">
			</div>
			<div class="ac-repo-actions">
				<!-- Oculto a pedido explícito (2026-09-17) — mismo criterio que
				     "Eliminados"/"Pendientes de Asignar": mecanismo intacto
				     (CSV/Excel, animación expand-in-place), solo se saca de la
				     vista. Para reactivarlo: sacar la clase `hidden`. -->
				<div class="ac-repo-exportar hidden" id="repo-exportar-wrap">
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
				<!-- Oculto a pedido explícito; mecanismo intacto por si se retoma. -->
				<button type="button" class="ac-btn-outline ac-btn-inline hidden" id="repo-eliminados-abrir">
					<span class="material-symbols-outlined">restore_from_trash</span>
					Eliminados
				</button>
				<!-- .xlsx en blanco con columnas del importador — Rebate/Participación: link directo,
				     Href en activarTab(). -->
				<a class="ac-btn-outline ac-btn-inline" id="repo-plantilla-descargar" href="getters/repositorio_plantilla.php?tipo=rebate" target="_blank">
					<span class="material-symbols-outlined">file_download</span>
					Descargar Formato
				</a>
				<!-- Solo Cuotas — Directo y Distribuidor tienen columnas distintas (ver
				     includes/repositorio_import.php), así que hace falta elegir cuál antes
				     de descargar. Mismo patrón expand-in-place que "Exportar" (2026-08-24). -->
				<div class="ac-repo-exportar hidden" id="repo-plantilla-cuotas-wrap">
					<button type="button" class="ac-btn-outline ac-btn-inline ac-repo-exportar-btn" id="repo-plantilla-cuotas-btn">
						<span class="material-symbols-outlined">file_download</span>
						Descargar Formato
					</button>
					<div class="ac-repo-exportar-opciones-outer">
						<div class="ac-repo-exportar-opciones">
							<a class="ac-repo-exportar-opcion" id="repo-plantilla-cuotas-directo" href="getters/repositorio_plantilla.php?tipo=cuotas&canal=directo" target="_blank">
								<span class="material-symbols-outlined">storefront</span>
								Directo
							</a>
							<a class="ac-repo-exportar-opcion" id="repo-plantilla-cuotas-distribuidor" href="getters/repositorio_plantilla.php?tipo=cuotas&canal=distribuidor" target="_blank">
								<span class="material-symbols-outlined">local_shipping</span>
								Distribuidor
							</a>
						</div>
					</div>
				</div>
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
			<p class="ac-field-hint">El archivo actualiza los registros que coincidan y agrega los nuevos. No borra el resto del repositorio.</p>
			<!-- Elegir canal ANTES del archivo (2026-09-21, pedido explícito, solo Cuotas): el canal real igual se sigue detectando solo del Excel (repositorio_parsear_cuotas()) — esto es la intención de quien sube, para avisar de entrada si el archivo no coincide en vez de que se entere recién en la previsualización. Mismos íconos storefront/local_shipping que ya usa "Descargar Formato" para Directo/Distribuidor, tarjetas grandes en vez de pastillas chicas porque acá es una elección obligatoria que bloquea el resto del paso, no un filtro secundario. Oculto para Rebate/Participación (assets/js/repositorios.js). -->
			<div class="ac-field hidden" id="repo-subir-canal-wrap">
				<label class="ac-field-label">¿Vas a subir Directo o Distribuidor?</label>
				<div class="ac-canal-picker" id="repo-subir-canal-group">
					<button type="button" class="ac-canal-picker-opcion" data-canal="directo">
						<span class="material-symbols-outlined ac-canal-picker-icono">storefront</span>
						<span class="ac-canal-picker-texto">
							<span class="ac-canal-picker-titulo">Directo</span>
				
						</span>
						<span class="material-symbols-outlined ac-canal-picker-check">check_circle</span>
					</button>
					<button type="button" class="ac-canal-picker-opcion" data-canal="distribuidor">
						<span class="material-symbols-outlined ac-canal-picker-icono">local_shipping</span>
						<span class="ac-canal-picker-texto">
							<span class="ac-canal-picker-titulo">Distribuidor</span>
						
						</span>
						<span class="material-symbols-outlined ac-canal-picker-check">check_circle</span>
					</button>
				</div>
			</div>
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
				<!-- Año, movido acá (2026-09-18, pedido explícito: "aprovecha ese espacio
				     vacío que deja en su fila") — antes vivía en su propia fila suelta,
				     entre el banner de trimestre y el de resumen. El Excel de Cuotas no
				     trae el año (solo el trimestre, inferido del propio archivo por
				     repositorio_parsear_cuotas()), lo elige el superdesarrollador acá
				     antes de guardar. Oculto para Rebate/Participación (assets/js/repositorios.js). -->
				<div class="ac-field ac-archivo-chip-anio hidden" id="repo-preview-anio-wrap">
					<label class="ac-field-label" for="repo-preview-anio">Año de este trimestre</label>
					<input type="number" class="ac-input" id="repo-preview-anio">
				</div>
			</div>
			<!-- Trimestre (y canal) detectado en el archivo de Cuotas — bien visible a propósito
			     (2026-09-15, pedido explícito: el aviso chico de antes, "(Directo, Q2)" dentro del
			     detalle del archivo, no se notaba). Oculto para Rebate/Participación
			     (assets/js/repositorios.js). -->
			<div class="ac-cuotas-trimestre-banner hidden" id="repo-preview-trimestre-banner">
				<span class="material-symbols-outlined">event</span>
				<div class="ac-cuotas-trimestre-banner-texto">
					<span class="ac-cuotas-trimestre-banner-label">Se están subiendo datos del trimestre</span>
					<span class="ac-cuotas-trimestre-banner-valor" id="repo-preview-trimestre-valor">—</span>
				</div>
				<!-- Canal detectado en el archivo (2026-09-16, pedido explícito): mismo estilo de badge que ya usa Registrar para el canal del usuario logueado, así se reconoce de un vistazo antes de confirmar el guardado. -->
				<span class="ac-badge" id="repo-preview-canal-badge">—</span>
			</div>
			<!-- Resumen de asignación de ESTE archivo (2026-09-17) — reemplaza el modal
			     "Resumen" separado (panorama histórico global, poco relevante para "qué
			     va a pasar si subo esto ahora"). Movido debajo del banner de trimestre
			     (2026-09-18, pedido explícito: "ponlo abajo de [el banner de] Se están
			     subiendo datos del trimestre"). Calculado en vivo sobre estadosPreview,
			     ver renderPreviewResumen() en repositorios.js. Oculto para Rebate/
			     Participación y hasta que termine de resolver (mismo criterio que el
			     resto de esta sección). -->
			<div class="ac-resumen-asignacion hidden" id="repo-preview-resumen">
				<div class="ac-resumen-asignacion-titulo">
					<span class="material-symbols-outlined">groups</span>
					<span id="repo-preview-resumen-titulo">—</span>
				</div>
				<div class="ac-resumen-asignacion-chips" id="repo-preview-resumen-chips"></div>
			</div>
			<p class="ac-field-hint">Así vamos a guardar estos datos. Podés corregir cualquier campo antes de confirmar.</p>
			<!-- Rojo (.ac-alert-error) solo si hubo errores reales, ámbar
			     (.ac-alert-warning) si son solo avisos — la clase de color la
			     decide JS en cada guardado, ver mostrarErroresPreview(). -->
			<div class="hidden" id="repo-preview-errores"></div>
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
			<p class="ac-field-hint">El nombre del cliente en el Excel no coincidió con un único cliente del maestro. Elige uno de los candidatos, busca el pos_id correcto a mano, o descarta la fila si es un error de tipeo.</p>
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
			<h3>Resumen de Cuotas Trimestrales</h3>
			<button type="button" class="ac-modal-close" id="repo-resumen-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-modal-body">
			<div class="ac-resumen-stats" id="repo-resumen-stats"></div>
			<div class="ac-resumen-chart-wrap">
				<p class="ac-resumen-chart-title">Usuarios con cuenta y supervisores sin cuenta todavía</p>
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
			<p class="ac-field-hint">Filas borradas de este repositorio. Se pueden reactivar en cualquier momento, no se pierde el dato.</p>
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
