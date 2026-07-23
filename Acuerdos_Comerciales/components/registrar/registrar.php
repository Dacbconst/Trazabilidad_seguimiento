<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$anioActual = (int) date('Y');
$anios      = range($anioActual - 1, $anioActual + 2);

$js_v = @filemtime(__DIR__.'/../../assets/js/registrar.js') ?: time();
?>
<div class="ac-acuerdo">
	<div class="ac-users-header">
		<h1 class="ac-page-title">Registrar Acuerdo PDV</h1>
		<p class="ac-page-subtitle">Gestión de acuerdos de desarrollo de negocios para el canal directo.</p>
	</div>

	<!-- Filtros -->
	<section class="ac-card ac-acuerdo-filtros-card">
		<div class="ac-acuerdo-filtros">
			<div class="ac-field">
				<label class="ac-field-label" for="ac-distribuidor-search">Distribuidor</label>
				<div class="ac-combo" id="ac-distribuidor-combo">
					<input type="text" class="ac-select ac-combo-input" id="ac-distribuidor-search" placeholder="Buscar distribuidor..." autocomplete="off">
					<input type="hidden" id="ac-distribuidor" value="">
				</div>
			</div>
			<div class="ac-field">
				<label class="ac-field-label">Localidad</label>
				<div class="ac-input ac-input-readonly" id="ac-localidad">—</div>
			</div>
			<div class="ac-field">
				<label class="ac-field-label">Periodo del Acuerdo</label>
				<div class="ac-month-picker" id="ac-month-picker">
					<button type="button" class="ac-select ac-month-picker-trigger" id="ac-month-picker-trigger">
						<span id="ac-selected-range-text">Seleccionar período</span>
						<span class="material-symbols-outlined">calendar_month</span>
					</button>
					<div class="ac-month-picker-popover hidden" id="ac-month-picker-popover">
						<div class="ac-month-grid" id="ac-month-grid"></div>
						<div class="ac-month-picker-footer">
							<span class="ac-field-hint">Seleccione inicio y fin</span>
							<button type="button" class="ac-link-btn" id="ac-clear-range">Limpiar</button>
						</div>
					</div>
				</div>
			</div>
			<div class="ac-field">
				<label class="ac-field-label" for="ac-anio">Año</label>
				<select class="ac-select" id="ac-anio">
					<?php foreach ($anios as $a): ?>
						<option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="ac-field">
				<label class="ac-field-label">Meses Incluidos</label>
				<div class="ac-input ac-input-readonly" id="ac-months-display">Sin selección</div>
			</div>
		</div>
	</section>

	<p class="ac-form-msg" id="ac-form-msg"></p>

	<!-- 1. Meta de Compras -->
	<section class="ac-card ac-acuerdo-section">
		<div class="ac-card-header ac-card-header-split">
			<div class="ac-card-header-title">
				<span class="material-symbols-outlined">shopping_cart</span>
				<h3>1. Meta de Compras en Dólares</h3>
			</div>
			<button type="button" class="ac-btn-secondary" id="ac-add-purchase-row">
				<span class="material-symbols-outlined">add</span> Agregar Fila
			</button>
		</div>
		<div class="ac-table-scroll" id="ac-purchase-container">
			<table class="ac-table ac-table-acuerdo" id="ac-purchase-table">
				<thead id="ac-purchase-head"></thead>
				<tbody id="ac-purchase-body"></tbody>
				<tfoot id="ac-purchase-foot"></tfoot>
			</table>
		</div>
	</section>

	<!-- 3. Visibilidad y Espacios -->
	<div class="ac-acuerdo-section-title">
		<span class="material-symbols-outlined">visibility</span>
		<h2>3. Visibilidad y Espacios</h2>
	</div>

	<!-- 3.a Cabeceras -->
	<section class="ac-card ac-acuerdo-section">
		<div class="ac-card-header ac-card-header-split">
			<h3>3.a. Extravisibilidad: Cabeceras</h3>
			<button type="button" class="ac-btn-secondary" id="ac-add-cabecera-row">
				<span class="material-symbols-outlined">add</span> Agregar Fila
			</button>
		</div>
		<div class="ac-table-scroll" id="ac-cabeceras-container">
			<table class="ac-table ac-table-acuerdo ac-table-bordered" id="ac-cabeceras-table">
				<thead id="ac-cabeceras-head"></thead>
				<tbody id="ac-cabeceras-body"></tbody>
			</table>
		</div>
	</section>

	<!-- 3.b Rumas -->
	<section class="ac-card ac-acuerdo-section">
		<div class="ac-card-header ac-card-header-split">
			<h3>3.b. Espacio: Rumas</h3>
			<button type="button" class="ac-btn-secondary" id="ac-add-ruma-row">
				<span class="material-symbols-outlined">add</span> Agregar Fila
			</button>
		</div>
		<div class="ac-acuerdo-rumas-layout">
			<div class="ac-table-scroll" id="ac-rumas-container">
				<table class="ac-table ac-table-acuerdo ac-table-bordered" id="ac-rumas-table">
					<thead id="ac-rumas-head"></thead>
					<tbody id="ac-rumas-body"></tbody>
				</table>
			</div>
			<div class="ac-acuerdo-rumas-legend">
				<table class="ac-table ac-table-bordered">
					<thead>
						<tr><th colspan="2">Valor Ruma x Marca x Mes</th></tr>
						<tr><th>Marca</th><th class="ac-text-right">Valor x Mes</th></tr>
					</thead>
					<tbody id="ac-rumas-legend-body"></tbody>
				</table>
			</div>
		</div>
	</section>

	<!-- 3.c Perchas -->
	<section class="ac-card ac-acuerdo-section">
		<div class="ac-card-header ac-card-header-split">
			<h3>3.c. Espacio: Perchas</h3>
			<button type="button" class="ac-btn-secondary" id="ac-add-percha-row">
				<span class="material-symbols-outlined">add</span> Agregar Fila
			</button>
		</div>
		<div class="ac-table-scroll" id="ac-perchas-container">
			<table class="ac-table ac-table-acuerdo ac-table-bordered" id="ac-perchas-table">
				<thead id="ac-perchas-head"></thead>
				<tbody id="ac-perchas-body"></tbody>
			</table>
		</div>
		<p class="ac-field-hint ac-acuerdo-percha-hint">El máximo de perchas por marca es 5.</p>
	</section>

	<!-- Footer -->
	<div class="ac-acuerdo-footer">
		<button type="button" class="ac-btn-primary ac-btn-inline" id="ac-generar-acta">
			<span class="material-symbols-outlined">description</span> Generar Acta
		</button>
	</div>
</div>

<!-- Modal: Vista previa del Acta generada — el iframe muestra el PDF real
     (mismo endpoint que "Descargar"), así el preview es EXACTO al PDF, no una
     segunda maqueta HTML que hay que mantener sincronizada a mano. -->
<div class="ac-modal-overlay ac-acta-modal-overlay" id="ac-acta-modal-overlay">
	<div class="ac-modal ac-acta-modal">
		<div class="ac-acta-modal-bar no-print">
			<a class="ac-btn-primary ac-btn-inline" id="ac-acta-descargar-pdf" target="_blank">
				<span class="material-symbols-outlined">download</span> Descargar / Imprimir PDF
			</a>
			<button type="button" class="ac-modal-close" id="ac-acta-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<iframe id="ac-acta-pdf-frame" class="ac-acta-pdf-frame" title="Vista previa del Acta"></iframe>
	</div>
</div>

<script src="assets/js/registrar.js?v=<?= $js_v ?>"></script>
