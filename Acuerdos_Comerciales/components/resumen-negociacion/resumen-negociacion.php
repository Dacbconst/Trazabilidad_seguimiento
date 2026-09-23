<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$aniosDisponibles = listar_anios_disponibles_equipo($mysqli);

$js_v = @filemtime(__DIR__.'/../../assets/js/resumen-negociacion.js') ?: time();
?>
<!-- Resumen de Negociación (2026-09-22) — mismo patrón que Seguimiento de Equipo (shell vacío, assets/js/resumen-negociacion.js llena todo vía fetch), pero cuenta ACUERDOS con al menos 1 línea de cada tipo (Rebate/Cabeceras/Rumas/Perchas), no filas. -->
<div class="ac-seguimiento" id="ac-negociacion">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Resumen de Negociación</h1>
			<p class="ac-page-subtitle">Cuántos Acuerdos negociaron cada tipo de espacio (Rebate/Cabeceras/Rumas/Perchas), por miembro del equipo comercial.</p>
		</div>
		<div class="ac-btn-group">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="neg-actualizar" title="Actualizar">
				<span class="material-symbols-outlined">refresh</span> <span class="ac-btn-text">Actualizar</span>
			</button>
		</div>
		<div class="ac-seg-periodo">
			<div class="ac-seg-pill-group" id="neg-trimestre-group">
				<button type="button" class="ac-seg-pill ac-seg-pill-activo" data-trimestre="0">Todos</button>
				<button type="button" class="ac-seg-pill" data-trimestre="1">Q1</button>
				<button type="button" class="ac-seg-pill" data-trimestre="2">Q2</button>
				<button type="button" class="ac-seg-pill" data-trimestre="3">Q3</button>
				<button type="button" class="ac-seg-pill" data-trimestre="4">Q4</button>
			</div>
			<select class="ac-select ac-seg-anio ac-select-bonito-auto" id="neg-anio">
				<option value="0">Todos los años</option>
				<?php foreach ($aniosDisponibles as $a): ?>
					<option value="<?= $a ?>"><?= $a ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<!-- 5 tarjetas KPI — solo lectura (nunca reordenan/filtran Equipo al hacer click), pero SÍ cambian de foco: equipo completo por default, o el usuario seleccionado en la lista (ver actualizarKpis() en resumen-negociacion.js). Mismo look que los KPI de Cumplimiento de Cuota (.ac-hist-stat-static). -->
	<p class="ac-neg-kpis-foco">Mostrando: <strong id="neg-kpis-quien">Equipo completo</strong>
		<button type="button" class="ac-neg-kpis-reset hidden" id="neg-kpis-reset">
			<span class="material-symbols-outlined">group</span> Ver equipo completo
		</button>
	</p>
	<div class="ac-neg-kpis" id="neg-kpis">
		<div class="ac-hist-stat ac-hist-stat-static">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">description</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Total Acuerdos</p>
				<p class="ac-stat-value" data-kpi="total">—</p>
			</span>
		</div>
		<div class="ac-hist-stat ac-hist-stat-static ac-hist-stat-ok">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">percent</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Rebate</p>
				<p class="ac-stat-value" data-kpi="rebate">—</p>
			</span>
		</div>
		<div class="ac-hist-stat ac-hist-stat-static ac-hist-stat-warn">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">view_agenda</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Cabeceras</p>
				<p class="ac-stat-value" data-kpi="cabeceras">—</p>
			</span>
		</div>
		<div class="ac-hist-stat ac-hist-stat-static ac-hist-stat-purple">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">stacks</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Rumas</p>
				<p class="ac-stat-value" data-kpi="rumas">—</p>
			</span>
		</div>
		<div class="ac-hist-stat ac-hist-stat-static ac-hist-stat-bad">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">shelves</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Perchas</p>
				<p class="ac-stat-value" data-kpi="perchas">—</p>
			</span>
		</div>
	</div>

	<div class="ac-seg-grid">
		<section class="ac-card ac-seg-equipo-card">
			<div class="ac-seg-equipo-header">
				<div class="ac-seg-equipo-titulo">
					<span class="material-symbols-outlined">group</span>
					<h3>Equipo</h3>
					<span class="ac-seg-orden">Por cantidad de Acuerdos</span>
				</div>
				<div class="ac-input-wrap ac-seg-buscar-wrap">
					<span class="material-symbols-outlined">search</span>
					<input type="text" class="ac-input" id="neg-buscar" placeholder="Buscar usuario...">
				</div>
			</div>
			<div id="neg-equipo-lista">
				<div class="ac-seg-cargando">Cargando equipo...</div>
			</div>
		</section>

		<section class="ac-card ac-seg-detalle-card" id="neg-detalle-card">
			<div class="ac-seg-cargando">Cargando...</div>
		</section>
	</div>
</div>

<script src="assets/js/resumen-negociacion.js?v=<?= $js_v ?>"></script>
