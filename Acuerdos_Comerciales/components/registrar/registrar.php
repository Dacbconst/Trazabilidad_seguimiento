<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$anioActual = (int) date('Y');
$anios      = range($anioActual - 1, $anioActual + 2);

// Canal del usuario logueado: se deriva EN VIVO de su `supervisor` contra
// repositorio_locales_supervisores_cliente, nunca se guarda (ver
// canalDeSupervisor() en functions.php). null (sin supervisor asignado) se
// trata como 'directo' por defecto — no bloquea el formulario.
$canalUsuario = canalDeSupervisor($mysqli, $_SESSION['supervisor'] ?? null) ?: 'directo';

$js_v = @filemtime(__DIR__.'/../../assets/js/registrar.js') ?: time();
?>
<div class="ac-acuerdo">
	<div class="ac-users-header ac-acuerdo-header">
		<div>
			<h1 class="ac-page-title">Registrar Acuerdo PDV</h1>
			<p class="ac-page-subtitle">Gestión de acuerdos de desarrollo de negocios para el canal <?= $canalUsuario === 'distribuidor' ? 'distribuidor' : 'directo' ?>.</p>
		</div>
		<span class="ac-badge ac-badge-canal-<?= $canalUsuario ?>" id="ac-canal-badge"><?= $canalUsuario === 'distribuidor' ? 'Distribuidor' : 'Canal Directo' ?></span>
	</div>

	<script>var CANAL_USUARIO = '<?= $canalUsuario ?>';</script>

	<!-- Filtros -->
	<section class="ac-card ac-acuerdo-filtros-card">
		<div class="ac-acuerdo-filtros">
			<div class="ac-field <?= $canalUsuario === 'distribuidor' ? '' : 'hidden' ?>" id="ac-empresa-field">
				<label class="ac-field-label" for="ac-empresa-search">Empresa Distribuidora</label>
				<div class="ac-combo" id="ac-empresa-combo">
					<input type="text" class="ac-select ac-combo-input" id="ac-empresa-search" placeholder="Elegir empresa..." autocomplete="off">
					<input type="hidden" id="ac-empresa" value="">
				</div>
			</div>
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
				<label class="ac-field-label" for="ac-periodo-select">Periodo del Acuerdo</label>
				<!-- Los meses se manejan en trimestres fijos (Q1-Q4), ya no rango
				     libre — pedido explícito 2026-08-18, ver CLAUDE.md. -->
				<select class="ac-select" id="ac-periodo-select">
					<option value="0" selected>Q1 (Enero - Marzo)</option>
					<option value="1">Q2 (Abril - Junio)</option>
					<option value="2">Q3 (Julio - Septiembre)</option>
					<option value="3">Q4 (Octubre - Diciembre)</option>
				</select>
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
		<button type="button" class="ac-btn-secondary ac-btn-inline" id="ac-guardar-borrador">
			<span class="material-symbols-outlined">save</span> Guardar Borrador
		</button>
		<button type="button" class="ac-btn-primary ac-btn-inline" id="ac-generar-acta">
			<span class="material-symbols-outlined">visibility</span> Previsualización
		</button>
	</div>
</div>

<!-- Modal: Previsualización del Acta. Al abrir NO se guarda nada en la base
     (getters/previsualizar_acta_pdf.php arma el PDF al vuelo desde lo que hay
     en pantalla) — recién "Generar PDF" guarda de verdad (estado='generado')
     y habilita "Descargar PDF", que en ese momento pasa a apuntar al PDF real
     persistido (getters/generar_acta_pdf.php). -->
<div class="ac-modal-overlay ac-acta-modal-overlay" id="ac-acta-modal-overlay">
	<div class="ac-modal ac-acta-modal">
		<div class="ac-acta-modal-bar no-print">
			<div class="ac-acta-zoom-controls">
				<button type="button" class="ac-icon-btn" id="ac-acta-zoom-out" title="Alejar">
					<span class="material-symbols-outlined">zoom_out</span>
				</button>
				<span class="ac-acta-zoom-label" id="ac-acta-zoom-label">100%</span>
				<button type="button" class="ac-icon-btn" id="ac-acta-zoom-in" title="Acercar">
					<span class="material-symbols-outlined">zoom_in</span>
				</button>
			</div>
			<div class="ac-acta-modal-actions">
				<button type="button" class="ac-btn-secondary ac-btn-inline" id="ac-acta-generar-pdf">
					<span class="material-symbols-outlined">picture_as_pdf</span> Generar PDF
				</button>
				<a class="ac-btn-primary ac-btn-inline ac-btn-disabled" id="ac-acta-descargar-pdf" aria-disabled="true">
					<span class="material-symbols-outlined">download</span> Descargar PDF
				</a>
			</div>
			<button type="button" class="ac-modal-close" id="ac-acta-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<iframe id="ac-acta-pdf-frame" class="ac-acta-pdf-frame" title="Vista previa del Acta"></iframe>
	</div>
</div>

<script src="assets/js/registrar.js?v=<?= $js_v ?>"></script>
