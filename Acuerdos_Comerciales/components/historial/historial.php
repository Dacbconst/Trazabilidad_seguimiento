<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$busqueda  = trim($_GET['q'] ?? '');
$mes       = (int) ($_GET['mes'] ?? 0);
$pagina    = (int) ($_GET['pg'] ?? 1);
$resultado = listar_historial_acuerdos($mysqli, $busqueda, $mes, $pagina, $_SESSION['user_id'] ?? null);
$acuerdos  = $resultado['acuerdos'];

// "Descargar Excel" (exportar_cuota_categoria.php) solo existe para canal
// Directo — el de Distribuidor todavía no está construido (ver CLAUDE.md).
// Mismo cálculo que registrar.php (canalDeSupervisor()), expuesto para que
// historial.js bloquee el click con un aviso "Próximamente" en vez de
// descargar un .xlsx vacío/con el formato equivocado.
$canalUsuario = canalDeSupervisor($mysqli, $_SESSION['supervisor'] ?? null) ?: 'directo';

$mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$js_v = @filemtime(__DIR__.'/../../assets/js/historial.js') ?: time();
?>
<div class="ac-historial" id="ac-historial-lista">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Historial de Acuerdos</h1>
			<p class="ac-page-subtitle">Gestiona y descarga los acuerdos de desarrollo de negocios generados.</p>
		</div>
		<div class="ac-btn-group">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-actualizar" title="Actualizar">
				<span class="material-symbols-outlined">refresh</span> Actualizar
			</button>
			<button type="button" class="ac-btn-secondary" id="hist-abrir-borradores">
				<span class="material-symbols-outlined">draft</span> Mis Borradores
			</button>
			<button type="button" class="ac-btn-primary ac-btn-inline" id="hist-nuevo-acuerdo">
				<span class="material-symbols-outlined">add</span>
				Nuevo Acuerdo
			</button>
		</div>
	</div>

	<section class="ac-card ac-hist-filtros-card">
		<div class="ac-hist-filtros">
			<div class="ac-input-wrap ac-hist-search-wrap">
				<span class="material-symbols-outlined">search</span>
				<input type="text" class="ac-input" id="hist-buscar" placeholder="Buscar por distribuidor..." value="<?= htmlspecialchars($busqueda) ?>">
			</div>
			<select class="ac-select ac-hist-mes" id="hist-mes">
				<option value="0">Seleccionar Mes</option>
				<?php foreach ($mesesLargos as $i => $nombre): ?>
					<option value="<?= $i + 1 ?>" <?= $mes === $i + 1 ? 'selected' : '' ?>><?= $nombre ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-buscar-btn">
				<span class="material-symbols-outlined">search</span>
				Buscar
			</button>
			<a class="ac-btn-outline ac-btn-inline" id="hist-exportar-cuota" href="getters/exportar_cuota_categoria.php" target="_blank">
				<span class="material-symbols-outlined">download</span>
				Descargar Excel
			</a>
		</div>
	</section>

	<script>var CANAL_USUARIO_HISTORIAL = '<?= $canalUsuario ?>';</script>

	<section class="ac-card">
		<div class="ac-table-scroll">
			<table class="ac-table" id="hist-tabla">
				<thead>
					<tr>
						<th>ID</th>
						<th>Distribuidor</th>
						<th>Localidad</th>
						<th class="ac-text-center">Periodo</th>
						<th class="ac-text-right">Fecha Generada</th>
						<th class="ac-text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="hist-tabla-body">
					<?php if ($acuerdos): ?>
						<?php foreach ($acuerdos as $a): ?>
							<?= renderFilaHistorial($a) ?>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="6" class="ac-table-empty">No se encontraron acuerdos.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="ac-pagination" id="hist-paginacion" data-pagina="<?= $resultado['pagina'] ?>" data-total-paginas="<?= $resultado['total_paginas'] ?>">
			<p class="ac-pagination-info" id="hist-paginacion-info">
				Mostrando <strong><?= count($acuerdos) ?></strong> de <strong><?= $resultado['total'] ?></strong> acuerdos
			</p>
			<div class="ac-pagination-btns" id="hist-paginacion-btns"></div>
		</div>
	</section>
</div>

<!-- Detalle de un acuerdo del historial: mismo PDF real que genera Registrar
     (getters/generar_acta_pdf.php), no una segunda maqueta HTML a mantener
     sincronizada. -->
<div class="ac-historial-preview hidden" id="ac-historial-preview">
	<div class="ac-acuerdo-preview-bar no-print">
		<button type="button" class="ac-btn-outline" id="hist-volver-lista">
			<span class="material-symbols-outlined">arrow_back</span> Volver al Historial
		</button>
		<a class="ac-btn-primary ac-btn-inline" id="hist-descargar-pdf" target="_blank">
			<span class="material-symbols-outlined">download</span> Descargar / Imprimir PDF
		</a>
	</div>
	<iframe id="hist-pdf-frame" class="ac-acta-pdf-frame" title="Vista previa del Acta"></iframe>
</div>

<!-- Modal: Mis Borradores — borradores propios (creado_por = usuario de la
     sesión, ver listar_borradores_usuario() en functions.php). "Continuar
     editando" cambia a la pestaña Registrar y carga el borrador ahí (ver
     window.acRegistrarCargarBorrador en registrar.js). -->
<div class="ac-modal-overlay" id="hist-borradores-modal-overlay">
	<div class="ac-modal ac-borradores-modal">
		<div class="ac-modal-header">
			<h3>Mis Borradores</h3>
			<button type="button" class="ac-modal-close" id="hist-borradores-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-borradores-modal-body">
			<div class="ac-table-scroll">
			<table class="ac-table">
				<thead>
					<tr><th>Documento</th><th>Distribuidor</th><th>Periodo</th><th>Actualizado</th><th></th></tr>
				</thead>
				<tbody id="hist-borradores-body"></tbody>
			</table>
			</div>
		</div>
	</div>
</div>

<script src="assets/js/historial.js?v=<?= $js_v ?>"></script>
