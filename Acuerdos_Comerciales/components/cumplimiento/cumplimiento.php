<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$aniosDisponibles = listar_anios_disponibles_cumplimiento($mysqli);

$js_v = @filemtime(__DIR__.'/../../assets/js/cumplimiento.js') ?: time();
?>
<!-- Cumplimiento de Cuota (2026-08-30) — mismo patrón que Seguimiento de
     Equipo: esta página solo arma el shell (header, filtros, contenedores
     vacíos); todo el contenido con datos lo llena assets/js/cumplimiento.js
     al cargar, vía fetch a getters/cumplimiento_listar.php. -->
<div class="ac-cumpl" id="ac-cumpl">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Cumplimiento de Cuota</h1>
			<p class="ac-page-subtitle">Resultado real por asesor, cliente y categoría — leído directo del Excel que Jabonería Wilson devuelve con la venta ya cargada.</p>
		</div>
		<!-- "Subir Excel" ahora deja elegir el formato (2026-08-31, mismo
		     mecanismo que "Descargar Excel" en Historial — .ac-repo-exportar,
		     cero CSS/animación nueva). Con la pastilla de Vista en Directo o
		     Distribuidor, el botón salta el picker (ver cumplimiento.js). -->
		<div class="ac-repo-exportar" id="cumpl-subir-wrap">
			<button type="button" class="ac-btn-primary ac-btn-inline ac-repo-exportar-btn" id="cumpl-subir-btn">
				<span class="material-symbols-outlined">upload_file</span>
				Subir Excel
			</button>
			<div class="ac-repo-exportar-opciones-outer">
				<div class="ac-repo-exportar-opciones">
					<button type="button" class="ac-repo-exportar-opcion" id="cumpl-subir-directo">
						<span class="material-symbols-outlined">store</span>
						Directo
					</button>
					<button type="button" class="ac-repo-exportar-opcion" id="cumpl-subir-distribuidor">
						<span class="material-symbols-outlined">local_shipping</span>
						Distribuidor
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Vista por canal (2026-08-31) — misma pastilla que ya usa Historial de
	     Acuerdos, mismo criterio: filtra la lista Y decide qué formato de
	     Excel acepta "Subir Excel" (ver cumplimiento.js). -->
	<div class="ac-seg-periodo" style="margin-bottom: var(--space-sm);">
		<span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--color-on-surface-variant);">Vista</span>
		<div class="ac-seg-pill-group" id="cumpl-canal-group">
			<button type="button" class="ac-seg-pill ac-seg-pill-activo" data-canal="total">Total</button>
			<button type="button" class="ac-seg-pill" data-canal="directo">Directo</button>
			<button type="button" class="ac-seg-pill" data-canal="distribuidor">Distribuidor</button>
		</div>
	</div>

	<div class="ac-seg-periodo">
		<div class="ac-seg-pill-group" id="cumpl-trimestre-group">
			<button type="button" class="ac-seg-pill ac-seg-pill-activo" data-trimestre="0">Todos</button>
			<button type="button" class="ac-seg-pill" data-trimestre="1">Q1</button>
			<button type="button" class="ac-seg-pill" data-trimestre="2">Q2</button>
			<button type="button" class="ac-seg-pill" data-trimestre="3">Q3</button>
			<button type="button" class="ac-seg-pill" data-trimestre="4">Q4</button>
		</div>
		<select class="ac-select ac-seg-anio ac-select-bonito-auto" id="cumpl-anio">
			<option value="0">Todos los años</option>
			<?php foreach ($aniosDisponibles as $a): ?>
				<option value="<?= $a ?>"><?= $a ?></option>
			<?php endforeach; ?>
		</select>
		<div class="ac-input-wrap ac-cumpl-buscar-wrap">
			<span class="material-symbols-outlined">search</span>
			<input type="text" class="ac-input" id="cumpl-buscar" placeholder="Buscar asesor o cliente...">
		</div>
	</div>

	<div class="ac-resumen-stats" id="cumpl-stats">
		<div class="ac-stat-tile">
			<span class="ac-stat-label">Clientes evaluados</span>
			<span class="ac-stat-value" id="cumpl-stat-clientes">—</span>
		</div>
		<div class="ac-stat-tile">
			<span class="ac-stat-label">Ganan la categoría</span>
			<span class="ac-stat-value" id="cumpl-stat-ganan" style="color:#1e9e5a;">—</span>
		</div>
		<div class="ac-stat-tile">
			<span class="ac-stat-label">No ganan</span>
			<span class="ac-stat-value" id="cumpl-stat-no-ganan" style="color:#93000a;">—</span>
		</div>
		<!-- Tarjeta oculta a pedido explícito del usuario (2026-08-31) —
		     el <span id="cumpl-stat-promedio"> se queda para que
		     cumplimiento.js le siga escribiendo el valor sin romper nada,
		     solo se dejó de mostrar la tarjeta entera. .ac-resumen-stats
		     usa auto-fit, así que las otras 3 tarjetas se acomodan solas
		     sin dejar un hueco. -->
		<div class="ac-stat-tile" style="display:none;">
			<span class="ac-stat-label">Cumplimiento promedio</span>
			<span class="ac-stat-value" id="cumpl-stat-promedio">—</span>
		</div>
	</div>

	<section class="ac-card">
		<div class="ac-card-header">
			<h3>Resultado por asesor</h3>
		</div>
		<div class="ac-cumpl-col-header">
			<div>Categoría</div>
			<div>Cumplimiento</div>
			<div>Venta real</div>
			<div>Cuota</div>
			<div>Gana categoría</div>
			<div>Gana total</div>
			<div>Rebate ganado</div>
			<div></div>
		</div>
		<div id="cumpl-lista">
			<div class="ac-seg-cargando">Cargando...</div>
		</div>
	</section>
</div>

<!-- Modal de subida, 2 pasos: elegir archivo + año, después previsualizar
     antes de confirmar — mismo patrón exacto que Repositorios (Cuotas
     Trimestrales), incluida la barra de carga real vía XHR. -->
<div class="ac-modal-overlay" id="cumpl-subir-modal-overlay">
	<div class="ac-modal ac-repo-subir-modal">
		<div class="ac-modal-header">
			<h3>Subir Excel de Cumplimiento</h3>
			<button type="button" class="ac-modal-close" id="cumpl-subir-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>

		<div class="ac-modal-body" id="cumpl-subir-paso-elegir">
			<p class="ac-field-hint">Sube el mismo Excel que se descarga desde Historial ("Descargar Excel"), ya completado con la venta real y la cartera. No hace falta calcular nada: se lee directo el resultado que el propio archivo ya calculó.</p>
			<div class="ac-dropzone" id="cumpl-dropzone">
				<span class="material-symbols-outlined">upload_file</span>
				<p class="ac-dropzone-title">Arrastra tu Excel acá o haz click para elegirlo</p>
				<p class="ac-dropzone-sub">.xlsx</p>
			</div>
			<div class="ac-progreso-carga hidden" id="cumpl-subir-progreso">
				<p class="ac-progreso-carga-texto" id="cumpl-subir-progreso-texto">Subiendo…</p>
				<div class="ac-progreso-carga-track">
					<div class="ac-progreso-carga-fill" id="cumpl-subir-progreso-fill"></div>
				</div>
			</div>
			<input type="file" id="cumpl-archivo-input" accept=".xlsx" hidden>
		</div>

		<div class="ac-modal-body hidden" id="cumpl-subir-paso-preview">
			<div class="ac-archivo-chip">
				<span class="material-symbols-outlined">description</span>
				<div>
					<div class="ac-archivo-chip-nombre" id="cumpl-preview-nombre-archivo">—</div>
					<div class="ac-archivo-chip-detalle" id="cumpl-preview-cantidad">—</div>
				</div>
			</div>
			<!-- El Excel no trae el año (solo el trimestre, inferido del propio
			     archivo) — lo elige el superdesarrollador acá antes de guardar. -->
			<div class="ac-field" id="cumpl-preview-anio-wrap">
				<label class="ac-field-label" for="cumpl-preview-anio">Año de este trimestre</label>
				<input type="number" class="ac-input" id="cumpl-preview-anio" style="max-width:140px;">
			</div>
			<p class="ac-field-hint">Así vamos a guardar estos datos.</p>
			<!-- Rojo (.ac-alert-error) solo si hubo errores reales, ámbar
			     (.ac-alert-warning) si son solo avisos — la clase de color la
			     decide JS en cada guardado. -->
			<div class="hidden" id="cumpl-preview-errores"></div>
			<div class="ac-table-scroll ac-preview-table-scroll">
				<table class="ac-table ac-preview-table" id="cumpl-preview-tabla">
					<thead id="cumpl-preview-tabla-head"></thead>
					<tbody id="cumpl-preview-tabla-body"></tbody>
				</table>
			</div>
		</div>

		<div class="ac-modal-footer" id="cumpl-subir-footer-elegir">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="cumpl-subir-cancelar">Cancelar</button>
		</div>
		<div class="ac-modal-footer hidden" id="cumpl-subir-footer-preview">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="cumpl-subir-atras">Atrás</button>
			<button type="button" class="ac-btn-primary ac-btn-inline" id="cumpl-subir-guardar">
				<span class="material-symbols-outlined">save</span>
				Confirmar y guardar
			</button>
		</div>
	</div>
</div>

<script src="assets/js/cumplimiento.js?v=<?= $js_v ?>"></script>
