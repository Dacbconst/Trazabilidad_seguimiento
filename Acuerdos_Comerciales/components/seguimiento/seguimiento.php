<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$aniosDisponibles = listar_anios_disponibles_equipo($mysqli);

$js_v = @filemtime(__DIR__.'/../../assets/js/seguimiento.js') ?: time();
?>
<!-- Seguimiento de Equipo (2026-08-27, rediseño con Claude Design) — esta
     página solo arma el shell (header, filtros, contenedores vacíos); todo
     el contenido con datos (números, lista de Equipo, detalle) lo llena
     assets/js/seguimiento.js al cargar, vía fetch a los getters — mismo
     patrón que ya usa la campanita de alertas del header (index.php),
     necesario acá porque cambiar de filtro/buscar tiene que sentirse
     instantáneo, sin ida y vuelta al servidor por cada click. -->
<div class="ac-seguimiento" id="ac-seguimiento">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Seguimiento de Equipo</h1>
			<p class="ac-page-subtitle">Seguimiento de Actas generadas y su estado de firma, por miembro del equipo comercial.</p>
		</div>
		<div class="ac-btn-group">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="seg-actualizar" title="Actualizar">
				<span class="material-symbols-outlined">refresh</span> <span class="ac-btn-text">Actualizar</span>
			</button>
		</div>
		<div class="ac-seg-periodo">
			<div class="ac-seg-pill-group" id="seg-trimestre-group">
				<button type="button" class="ac-seg-pill ac-seg-pill-activo" data-trimestre="0">Todos</button>
				<button type="button" class="ac-seg-pill" data-trimestre="1">Q1</button>
				<button type="button" class="ac-seg-pill" data-trimestre="2">Q2</button>
				<button type="button" class="ac-seg-pill" data-trimestre="3">Q3</button>
				<button type="button" class="ac-seg-pill" data-trimestre="4">Q4</button>
			</div>
			<select class="ac-select ac-seg-anio ac-select-bonito-auto" id="seg-anio">
				<!-- "Todos los años" siempre primera opción y seleccionada por
				     default (mismo criterio que hist-anio en Historial) — sin
				     esto, el <select> arrancaba en el primer año real de la
				     lista, excluyendo en silencio cualquier Acta de años
				     anteriores desde la primera carga, sin que el usuario
				     pudiera volver a "todos" desde la UI. -->
				<option value="0">Todos los años</option>
				<?php foreach ($aniosDisponibles as $a): ?>
					<option value="<?= $a ?>"><?= $a ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<!-- Filtro único de estado: Todas / Firmadas / Pendientes / Vencidas.
	     Controla a la vez la lista de Equipo y el detalle — un solo
	     mecanismo, no dos haciendo cosas parecidas (feedback explícito del
	     usuario sobre la versión anterior). Contadores en 0 hasta que carga
	     el JSON real. -->
	<div class="ac-seg-filtros" id="seg-filtros">
		<button type="button" class="ac-seg-filtro ac-seg-filtro-activo" data-filtro="todas" data-color="#00288e">
			<span class="ac-seg-filtro-head"><span class="material-symbols-outlined">description</span>Todas</span>
			<span class="ac-seg-filtro-valor" data-valor="todas">0</span>
		</button>
		<button type="button" class="ac-seg-filtro" data-filtro="firmadas" data-color="#1e9e5a">
			<span class="ac-seg-filtro-head"><span class="material-symbols-outlined">task_alt</span>Firmadas</span>
			<span class="ac-seg-filtro-valor" data-valor="firmadas">0</span>
		</button>
		<button type="button" class="ac-seg-filtro" data-filtro="pendientes" data-color="#d98c1f">
			<span class="ac-seg-filtro-head"><span class="material-symbols-outlined">schedule</span>Pendientes</span>
			<span class="ac-seg-filtro-valor" data-valor="pendientes">0</span>
		</button>
		<button type="button" class="ac-seg-filtro" data-filtro="vencidas" data-color="#ba1a1a">
			<span class="ac-seg-filtro-head"><span class="material-symbols-outlined">event_busy</span>Vencidas</span>
			<span class="ac-seg-filtro-valor" data-valor="vencidas">0</span>
		</button>
	</div>
	<p class="ac-seg-viendo">
		<span class="material-symbols-outlined">visibility</span>
		Vista actual: <strong id="seg-viendo-texto">Todas las Actas</strong> — <span id="seg-criterio-texto">ordenadas por cantidad de Actas generadas</span>
	</p>

	<div class="ac-seg-grid">
		<section class="ac-card ac-seg-equipo-card">
			<div class="ac-seg-equipo-header">
				<div class="ac-seg-equipo-titulo">
					<span class="material-symbols-outlined">group</span>
					<h3>Equipo</h3>
					<span class="ac-seg-orden" id="seg-orden-texto">Por cantidad de Actas</span>
				</div>
				<div class="ac-input-wrap ac-seg-buscar-wrap">
					<span class="material-symbols-outlined">search</span>
					<input type="text" class="ac-input" id="seg-buscar" placeholder="Buscar usuario...">
				</div>
			</div>
			<div id="seg-equipo-lista">
				<div class="ac-seg-cargando">Cargando equipo...</div>
			</div>
		</section>

		<section class="ac-card ac-seg-detalle-card" id="seg-detalle-card">
			<div class="ac-seg-cargando">Cargando...</div>
		</section>
	</div>
</div>

<!-- Ver Acta Firmada, solo lectura: mismo componente de 2 paneles de Historial (Acta Generada + Acta Firmada), sin los botones de subir — acá es para comparar sin abrir pestañas nuevas, nunca para reemplazar el archivo. Ver abrirFirmaSoloLectura() en seguimiento.js. -->
<div class="ac-modal-overlay ac-firma-modal-overlay" id="seg-firma-modal-overlay">
	<div class="ac-modal ac-firma-modal">
		<div class="ac-firma-modal-bar no-print">
			<h3 class="ac-firma-modal-title" id="seg-firma-modal-title">Acta Firmada</h3>
			<button type="button" class="ac-modal-close" id="seg-firma-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-firma-modal-body">
			<div class="ac-firma-panel">
				<p class="ac-firma-panel-label">Acta Generada</p>
				<iframe id="seg-firma-original-frame" class="ac-firma-panel-frame" title="Acta generada"></iframe>
				<div class="ac-firma-canvas-wrap hidden" id="seg-firma-original-canvas-wrap">
					<canvas id="seg-firma-original-canvas"></canvas>
					<div class="ac-firma-canvas-estado" id="seg-firma-original-canvas-estado"></div>
				</div>
				<button type="button" class="ac-icon-btn ac-firma-panel-ampliar" id="seg-firma-ampliar-original" title="Ampliar">
					<span class="material-symbols-outlined">open_in_full</span>
				</button>
			</div>
			<div class="ac-firma-panel">
				<p class="ac-firma-panel-label">Acta Firmada</p>
				<div class="ac-firma-preview-area" id="seg-firma-preview-area"></div>
				<button type="button" class="ac-icon-btn ac-firma-panel-ampliar hidden" id="seg-firma-ampliar-firmada" title="Ampliar">
					<span class="material-symbols-outlined">open_in_full</span>
				</button>
				<!-- Zoom con rueda del mouse o los botones, ver aplicarZoomFirmada() en seguimiento.js. -->
				<div class="ac-firma-zoom-controls hidden" id="seg-firma-zoom-controls">
					<button type="button" class="ac-icon-btn" id="seg-firma-zoom-out" title="Alejar">
						<span class="material-symbols-outlined">zoom_out</span>
					</button>
					<span class="ac-firma-zoom-label" id="seg-firma-zoom-label">100%</span>
					<button type="button" class="ac-icon-btn" id="seg-firma-zoom-in" title="Acercar">
						<span class="material-symbols-outlined">zoom_in</span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="assets/js/seguimiento.js?v=<?= $js_v ?>"></script>
