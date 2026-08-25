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
				<button type="button" class="ac-btn-primary ac-btn-inline" id="repo-subir-abrir">
					<span class="material-symbols-outlined">upload_file</span>
					Subir Archivo
				</button>
			</div>
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

<script src="assets/js/repositorios.js?v=<?= $js_v ?>"></script>
