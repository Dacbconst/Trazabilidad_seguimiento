<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$anioActual = (int) date('Y');
$anios      = range($anioActual - 1, $anioActual + 1);

$js_v = @filemtime(__DIR__.'/../../assets/js/liquidacion.js') ?: time();
?>
<div class="ac-historial" id="ac-liquidacion-lista">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Liquidación</h1>
			<p class="ac-page-subtitle">Subí el Excel de JW para calcular el rebate y la visibilidad reales contra las Actas.</p>
		</div>
		<div class="ac-btn-group">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="liq-actualizar" title="Actualizar">
				<span class="material-symbols-outlined">refresh</span> Actualizar
			</button>
			<button type="button" class="ac-btn-primary ac-btn-inline" id="liq-abrir-subir">
				<span class="material-symbols-outlined">upload_file</span> Subir Excel
			</button>
		</div>
	</div>

	<section class="ac-card">
		<div class="ac-table-scroll">
			<table class="ac-table" id="liq-tabla">
				<thead>
					<tr>
						<th>Canal</th>
						<th>Período</th>
						<th>Archivo</th>
						<th class="ac-text-center">Estado</th>
						<th class="ac-text-right">Filas</th>
						<th class="ac-text-right">Pendientes</th>
						<th class="ac-text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="liq-tabla-body">
					<tr><td colspan="7" class="ac-table-empty">Cargando...</td></tr>
				</tbody>
			</table>
		</div>
	</section>
</div>

<!-- Vista de Pendientes de Asignar de una importación puntual. -->
<div class="ac-historial-preview hidden" id="ac-liquidacion-pendientes">
	<div class="ac-acuerdo-preview-bar no-print">
		<button type="button" class="ac-btn-outline" id="liq-volver-lista">
			<span class="material-symbols-outlined">arrow_back</span> Volver a Liquidación
		</button>
	</div>
	<section class="ac-card">
		<div class="ac-card-header">
			<h3>Pendientes de Asignar</h3>
			<p class="ac-field-hint">Elegí el cliente correcto para cada fila que no se pudo vincular a una Acta automáticamente.</p>
		</div>
		<div class="ac-table-scroll">
			<table class="ac-table" id="liq-pendientes-tabla">
				<thead>
					<tr>
						<th>Tabla</th>
						<th>CEDI / Distribuidor</th>
						<th>Cliente / Nombre (Excel)</th>
						<th>Estado</th>
						<th>Resolver</th>
					</tr>
				</thead>
				<tbody id="liq-pendientes-body"></tbody>
			</table>
		</div>
	</section>
</div>

<!-- Vista de Resumen de Pagos de una importación puntual. -->
<div class="ac-historial-preview hidden" id="ac-liquidacion-resumen">
	<div class="ac-acuerdo-preview-bar no-print">
		<button type="button" class="ac-btn-outline" id="liq-resumen-volver">
			<span class="material-symbols-outlined">arrow_back</span> Volver a Liquidación
		</button>
		<a class="ac-btn-primary ac-btn-inline" id="liq-resumen-exportar" href="#" target="_blank">
			<span class="material-symbols-outlined">download</span> Exportar a Excel
		</a>
	</div>
	<section class="ac-card">
		<div class="ac-card-header">
			<h3>Resumen de Pagos</h3>
			<p class="ac-field-hint" id="liq-resumen-subtitulo"></p>
		</div>

		<div class="ac-resumen-stats" id="liq-resumen-stats"></div>

		<!-- Filtro de Período (2026-08-20): antes esta pantalla mostraba UNA
		     sola importación (un solo Excel subido) — ahora junta TODAS las
		     importaciones completadas del canal, así que hace falta un
		     filtro de período para poder acotar (o ver todo junto). A
		     diferencia de CEDI/Estado (que filtran en el cliente sobre lo ya
		     cargado), Trimestre/Año SÍ piden datos de nuevo al servidor,
		     porque cambian QUÉ importaciones se incluyen, no solo qué filas
		     se muestran de las ya cargadas. -->
		<div class="ac-resumen-filtros">
			<div class="ac-field ac-field-inline">
				<label class="ac-field-label" for="liq-resumen-filtro-trimestre">Período</label>
				<select class="ac-select" id="liq-resumen-filtro-trimestre">
					<option value="0">Todos los períodos</option>
					<option value="1">Q1 (Ene-Mar)</option>
					<option value="2">Q2 (Abr-Jun)</option>
					<option value="3">Q3 (Jul-Sep)</option>
					<option value="4">Q4 (Oct-Dic)</option>
				</select>
			</div>
			<div class="ac-field ac-field-inline">
				<label class="ac-field-label" for="liq-resumen-filtro-anio">Año</label>
				<select class="ac-select" id="liq-resumen-filtro-anio">
					<option value="0">Todos</option>
					<?php foreach ($anios as $a): ?>
						<option value="<?= $a ?>"><?= $a ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="ac-field ac-field-inline">
				<label class="ac-field-label" for="liq-resumen-filtro-cedi" id="liq-resumen-filtro-cedi-label">CEDI / Distribuidor</label>
				<select class="ac-select" id="liq-resumen-filtro-cedi">
					<option value="">Todos</option>
				</select>
			</div>
			<div class="ac-field ac-field-inline">
				<label class="ac-field-label" for="liq-resumen-filtro-estado">Estado</label>
				<select class="ac-select" id="liq-resumen-filtro-estado">
					<option value="">Todos</option>
					<option value="ok">OK</option>
					<option value="revisar">Revisar</option>
				</select>
			</div>
		</div>

		<div class="ac-resumen-chart-wrap">
			<p class="ac-resumen-chart-title">Top 10 (cliente × período) por total</p>
			<div id="liq-resumen-chart"></div>
		</div>

		<div class="ac-table-scroll">
			<table class="ac-table" id="liq-resumen-tabla">
				<thead>
					<tr>
						<th>CEDI / Distribuidor</th>
						<th>Cliente</th>
						<th>Período</th>
						<th>Acta</th>
						<th class="ac-text-right">Volumen</th>
						<th class="ac-text-right">Visibilidad</th>
						<th class="ac-text-right">Total</th>
						<th class="ac-text-center">Estado</th>
					</tr>
				</thead>
				<tbody id="liq-resumen-body"></tbody>
			</table>
		</div>
	</section>
</div>

<!-- Modal: Subir Excel de Liquidación. -->
<div class="ac-modal-overlay" id="liq-subir-modal-overlay">
	<div class="ac-modal">
		<div class="ac-modal-header">
			<h3>Subir Excel de Liquidación</h3>
			<button type="button" class="ac-modal-close" id="liq-subir-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<form id="liq-form-subir" class="ac-form" style="padding:0 var(--space-lg) var(--space-lg);">
			<div class="ac-field">
				<label class="ac-field-label" for="liq-canal">Canal</label>
				<select class="ac-select" id="liq-canal" name="canal" required>
					<option value="directa">Directa</option>
					<option value="distribuidor">Distribuidor</option>
				</select>
			</div>
			<div class="ac-field">
				<label class="ac-field-label" for="liq-anio">Año</label>
				<select class="ac-select" id="liq-anio" name="anio" required>
					<?php foreach ($anios as $a): ?>
						<option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="ac-field">
				<label class="ac-field-label" for="liq-archivo">Archivo Excel (.xlsx)</label>
				<input type="file" class="ac-input" id="liq-archivo" name="archivo" accept=".xlsx" required>
				<p class="ac-field-hint">Tiene que traer la hoja de cuota/venta/rebate y la de visibilidad, en el formato que ya usa JW. El período (qué meses cubre) se detecta solo, leyendo las columnas del archivo — no hace falta elegirlo a mano.</p>
			</div>
			<!-- Barra de carga real (2026-08-24, mismo arreglo que Repositorios,
			     ver CLAUDE.md "Módulo Repositorios" y assets/js/repositorios.js):
			     subida vía XHR para poder mostrar el % real de un Excel pesado,
			     no un fetch() que no lo expone. -->
			<div class="ac-progreso-carga hidden" id="liq-subir-progreso">
				<p class="ac-progreso-carga-texto" id="liq-subir-progreso-texto">Subiendo…</p>
				<div class="ac-progreso-carga-track">
					<div class="ac-progreso-carga-fill" id="liq-subir-progreso-fill"></div>
				</div>
			</div>
			<button class="ac-btn-primary" type="submit" id="liq-subir-submit">Procesar Excel</button>
		</form>
	</div>
</div>

<script src="assets/js/liquidacion.js?v=<?= $js_v ?>"></script>
